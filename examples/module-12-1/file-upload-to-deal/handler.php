<?php
/**
 * Обработчик публичной формы: принимает файл от клиента, кладёт его
 * на «Диск» приложения (в отдельную папку с версионированием и правами —
 * для архива/аудита) и одновременно прикрепляет этот же файл к таймлайну
 * сделки CRM (чтобы менеджер сразу увидел документ в карточке сделки).
 *
 * Это две разные сущности Bitrix24, поэтому файл грузится дважды из
 * одного временного файла — см. README, почему это осознанный выбор,
 * а не дублирование по ошибке.
 */

require_once __DIR__ . '/crest.php';

const APP_FOLDER_ID = 123; // ID папки на Диске, куда складываем входящие документы (см. README, как получить)
const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20 МБ — безопасный предел для base64 через REST

function redirectWithError(string $message): never
{
    header('Location: form.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithError('Недопустимый метод запроса');
}

$dealId = (int)($_POST['deal_id'] ?? 0);
if ($dealId <= 0) {
    redirectWithError('Не указан ID сделки');
}

if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    redirectWithError('Файл не был загружен');
}

$file = $_FILES['document'];

if ($file['size'] > MAX_FILE_SIZE) {
    redirectWithError('Файл превышает допустимый размер 20 МБ');
}

// Проверяем, что сделка с таким ID вообще существует — иначе файл
// улетит в CRM-пустоту и останется только на Диске без привязки
$dealCheck = CRest::call('crm.deal.get', ['id' => $dealId]);
if (empty($dealCheck['result'])) {
    redirectWithError('Сделка не найдена');
}

$fileContent = file_get_contents($file['tmp_name']);
if ($fileContent === false) {
    redirectWithError('Не удалось прочитать загруженный файл');
}

$safeName = preg_replace('/[^A-Za-zА-Яа-я0-9_.\-]/u', '_', $file['name']);
$encoded  = base64_encode($fileContent);

// 1. Кладём файл на Диск приложения — здесь работают права, версии,
//    история изменений, и файл не потеряется, даже если сделку позже удалят
$diskResult = CRest::call('disk.folder.uploadfile', [
    'id'          => APP_FOLDER_ID,
    'data'        => ['NAME' => "deal_{$dealId}_{$safeName}"],
    'fileContent' => ["deal_{$dealId}_{$safeName}", $encoded],
]);

if (!empty($diskResult['error'])) {
    redirectWithError('Ошибка загрузки на Диск: ' . ($diskResult['error_description'] ?? $diskResult['error']));
}

$diskFileId = $diskResult['result']['ID'] ?? null;

// 2. Прикрепляем файл к таймлайну сделки — менеджер видит документ
//    сразу в карточке сделки, без необходимости идти на Диск
$timelineResult = CRest::call('crm.timeline.comment.add', [
    'fields' => [
        'ENTITY_ID'   => $dealId,
        'ENTITY_TYPE' => 'deal',
        'COMMENT'     => 'Клиент загрузил документ через форму на сайте: ' . $file['name'],
        'FILES'       => [
            ['fileData' => [$safeName, $encoded]],
        ],
    ],
]);

if (!empty($timelineResult['error'])) {
    // Файл уже сохранён на Диске (см. $diskFileId) — не теряем его,
    // но сообщаем об ошибке привязки к таймлайну отдельно
    redirectWithError(
        'Файл сохранён на Диск (ID ' . $diskFileId . '), но не прикреплён к таймлайну: '
        . ($timelineResult['error_description'] ?? $timelineResult['error'])
    );
}

header('Location: form.php?success=1&deal_id=' . $dealId);
exit;
