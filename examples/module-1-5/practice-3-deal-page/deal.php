<?php
/**
 * Задача 3 — решение: публичная страница сделки.
 * Копировать в /deal.php (корень сайта).
 *
 * Показывает: 404 до шапки, свойства страницы после шапки (отложенный вывод),
 * хлебные крошки и безопасный редирект по белому списку.
 */
declare(strict_types=1);

/**
 * Возвращает безопасный относительный URL или null.
 * Всё, что не прошло проверку, отбрасывается — молча.
 */
function dealSafeBackUrl(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }

    $raw = trim($raw);

    if ($raw === '') {
        return null;
    }

    // Управляющие символы, backslash и закодированный CRLF — сразу отказ.
    // \evil.com и /%0d%0aLocation: — классические обходы.
    if (preg_match('~[\x00-\x1f\x7f\\\\]~', $raw) !== 0) {
        return null;
    }
    if (preg_match('~%0[ad]~i', $raw) !== 0) {
        return null;
    }

    // Protocol-relative: //evil.com браузер трактует как внешний хост.
    if (str_starts_with($raw, '//')) {
        return null;
    }

    $parts = parse_url($raw);

    // Абсолютные URL (со схемой или хостом) не принимаем вообще.
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return null;
    }

    if (!str_starts_with($raw, '/')) {
        return null;
    }

    // Белый список префиксов: только свои разделы.
    foreach (['/crm/', '/deal.php'] as $prefix) {
        if (str_starts_with($raw, $prefix)) {
            return $raw;
        }
    }

    return null;
}

$rawId = $_GET['id'] ?? '';

// ?id[]=1 приходит массивом. В PHP 8 ctype_digit(массив) — TypeError,
// а (string) на массиве — warning «Array to string conversion».
// Поэтому проверяем тип до приведения.
// Длина ограничена: ID в Bitrix — int(11), больше 10 цифр быть не может,
// а (int) на 20-значном числе молча насыщается до PHP_INT_MAX.
$dealId = (
    is_string($rawId)
    && $rawId !== ''
    && strlen($rawId) <= 10
    && ctype_digit($rawId)
) ? (int)$rawId : 0;

$deal = null;
$crmMissing = false;

if ($dealId > 0) {
    if (!\Bitrix\Main\Loader::includeModule('crm')) {
        // Публичная страница: 404 скрыл бы поломку конфигурации.
        // Отсутствующий модуль — это 500, а не «сделки нет».
        $crmMissing = true;
    } else {
        $deal = \Bitrix\Crm\DealTable::getById($dealId)->fetch();

        // Сигнатура CheckReadPermission($id, $userPermissions = null, $categoryId = null)
        // стабильна в CRM 20.x–24.x; второй и третий аргументы опциональны.
        if ($deal && !\CCrmDeal::CheckReadPermission($dealId)) {
            $deal = null;
        }
    }
}

// Код помечаем ДО шапки: CHTTP::SetStatus отправляет заголовок и запоминает
// статус (композит кеширует только 200). ERROR_404 в актуальном ядре не
// проверяется — оставлен для совместимости с модулями, которые его читают.
// 500 не кешируем вообще — статус выставляем, ERROR_404 не трогаем.
if ($crmMissing) {
    CHTTP::SetStatus('500 Internal Server Error');
} elseif ($deal === null) {
    define('ERROR_404', 'Y');
    CHTTP::SetStatus('404 Not Found');
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');

// Редирект — после шапки: до пролога не загружены ни $APPLICATION, ни CHTTP,
// а LocalRedirect — часть ядра. Заголовки при этом ещё не отправлены,
// потому что вывод буферизован (см. теорию, п. 8).
$rawBack = $_GET['backurl'] ?? null;

// strict_types: передача массива в ?string — TypeError, то есть 500.
$backUrl = is_string($rawBack) ? dealSafeBackUrl($rawBack) : null;

if ($backUrl !== null && $deal !== null) {
    LocalRedirect($backUrl);
}

if ($crmMissing) {
    $APPLICATION->SetTitle('Ошибка конфигурации');
    $APPLICATION->SetPageProperty('robots', 'noindex, nofollow');
    ?>
    <h1>Страница временно недоступна</h1>
    <p>Модуль CRM не установлен. Обратитесь к администратору портала.</p>
    <?php
} elseif ($deal === null) {
    $APPLICATION->SetTitle('Сделка не найдена');
    $APPLICATION->SetPageProperty('robots', 'noindex, nofollow');
    ?>
    <h1>Сделка не найдена</h1>
    <p>Сделка не существует или у вас нет прав на её просмотр.</p>
    <?php
} else {
    $title = 'Сделка #' . (int)$deal['ID'] . ': ' . (string)$deal['TITLE'];

    $APPLICATION->SetTitle($title);
    $APPLICATION->SetPageProperty(
        'description',
        'Сделка #' . (int)$deal['ID'] . ' в стадии ' . (string)$deal['STAGE_ID']
    );
    $APPLICATION->AddChainItem('CRM', '/crm/');
    $APPLICATION->AddChainItem('Сделка #' . (int)$deal['ID']);
    ?>
    <h1><?= htmlspecialcharsbx($title) ?></h1>
    <dl>
        <dt>Стадия</dt>
        <dd><?= htmlspecialcharsbx((string)$deal['STAGE_ID']) ?></dd>
        <dt>Сумма</dt>
        <dd><?= htmlspecialcharsbx((string)$deal['OPPORTUNITY']) ?></dd>
    </dl>
    <?php
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
