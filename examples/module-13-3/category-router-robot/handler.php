<?php
/**
 * Обработчик робота route_to_category.
 *
 * Робот (в отличие от custom activity) — простое действие без OUTPUT-
 * параметров для БП. Контракт ответа: вызвать bizproc.event.send
 * с результатом, OUTPUT не обязателен.
 */

require_once __DIR__ . '/crest.php';

$logFile = __DIR__ . '/robot.log';
file_put_contents($logFile, date('c') . ' ' . json_encode($_REQUEST, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

$eventToken = $_REQUEST['event_token'] ?? null;
$documentId = $_REQUEST['document_id'] ?? null; // ['crm','CCrmDocumentDeal','DEAL_123']
$properties = $_REQUEST['properties'] ?? [];

if (!$eventToken || !$documentId) {
    http_response_code(400);
    exit('no event_token / document_id');
}

// Быстрый ответ Битриксу, работу — после закрытия соединения
http_response_code(200);
header('Content-Type: application/json');
echo '{"ok":true}';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$logMessage = 'Робот завершился с ошибкой';

try {
    // document_id: ['crm', 'CCrmDocumentDeal', 'DEAL_123'] → числовой ID
    $dealId = (int) preg_replace('/^DEAL_/', '', $documentId[2] ?? '0');
    if (!$dealId) {
        throw new RuntimeException('Cannot parse deal ID from document_id');
    }

    $targetCategoryId = (int) ($properties['target_category_id'] ?? 0);
    $minOpportunity = $properties['min_opportunity'] ?? null;
    $targetSemantics = strtoupper($properties['target_stage_semantics'] ?? 'P');

    $deal = CRest::call('crm.deal.get', ['id' => $dealId]);
    if (!empty($deal['error']) || empty($deal['result'])) {
        throw new RuntimeException('crm.deal.get failed: ' . ($deal['error_description'] ?? 'no result'));
    }
    $deal = $deal['result'];

    // Встроенное условие робота: сумма сделки должна быть >= min_opportunity.
    // Можно было бы сделать штатным условием робота в настройках стадии —
    // здесь оно продублировано в коде для автономности (настройка переносится вместе с роботом).
    if ($minOpportunity !== null && $minOpportunity !== '' && (float) $deal['OPPORTUNITY'] < (float) $minOpportunity) {
        $logMessage = "Сумма {$deal['OPPORTUNITY']} < {$minOpportunity} — сделка не перенесена";
        CRest::call('bizproc.event.send', [
            'EVENT_TOKEN' => $eventToken,
            'LOG_MESSAGE' => $logMessage,
        ]);
        file_put_contents($logFile, date('c') . " skip: {$logMessage}\n", FILE_APPEND);
        exit;
    }

    // Ищем в целевой воронке стадию по СЕМАНТИКЕ, а не по жёсткому STAGE_ID —
    // иначе робот сломается, если администратор воронки переименует/перестроит стадии.
    $stages = CRest::call('crm.dealcategory.stage.list', ['id' => $targetCategoryId]);
    $targetStageId = null;
    foreach ($stages['result'] ?? [] as $stage) {
        if ($stage['SEMANTICS'] === $targetSemantics) {
            $targetStageId = $stage['STATUS_ID'];
            break;
        }
    }

    if (!$targetStageId) {
        throw new RuntimeException("No stage with semantics '{$targetSemantics}' in category {$targetCategoryId}");
    }

    // CATEGORY_ID и STAGE_ID меняются ОДНИМ вызовом — иначе сделка окажется
    // на несуществующей в новой воронке стадии и "потеряется" в канбане.
    $update = CRest::call('crm.deal.update', [
        'id' => $dealId,
        'fields' => [
            'CATEGORY_ID' => $targetCategoryId,
            'STAGE_ID' => $targetStageId,
        ],
    ]);

    if (!empty($update['error'])) {
        throw new RuntimeException('crm.deal.update failed: ' . ($update['error_description'] ?? 'unknown'));
    }

    $logMessage = "Сделка #{$dealId} перенесена в воронку {$targetCategoryId}, стадия {$targetStageId}";
} catch (Throwable $e) {
    $logMessage = 'Exception: ' . $e->getMessage();
}

// Робот не возвращает OUTPUT — просто сообщаем Битриксу, что шаг выполнен
CRest::call('bizproc.event.send', [
    'EVENT_TOKEN' => $eventToken,
    'LOG_MESSAGE' => $logMessage,
]);

file_put_contents($logFile, date('c') . " done: {$logMessage}\n", FILE_APPEND);
