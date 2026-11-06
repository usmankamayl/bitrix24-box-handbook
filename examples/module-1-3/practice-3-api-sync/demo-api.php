<?php

/**
 * Демонстрационный «внешний API» для practice-3-api-sync.
 *
 * Отдаёт две страницы записей и умеет ломаться — чтобы увидеть, как агент
 * ведёт себя при ошибке и что контрольная точка при этом не сдвигается.
 *
 *   GET ?limit=50&cursor=            -> страница 1, next_cursor=p2
 *   GET ?limit=50&cursor=p2          -> страница 2, next_cursor=null
 *   GET ?fail=1                      -> HTTP 503 (любая страница)
 *
 * В проекте этот файл не нужен: он существует только чтобы пример
 * запускался без доступа к настоящему внешнему сервису.
 */

header('Content-Type: application/json; charset=UTF-8');

if (($_GET['fail'] ?? '') === '1') {
    http_response_code(503);
    echo json_encode(['error' => 'service temporarily unavailable']);
    exit;
}

$cursor = isset($_GET['cursor']) ? (string)$_GET['cursor'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if ($limit < 1 || $limit > 200) {
    http_response_code(400);
    echo json_encode(['error' => 'limit out of range']);
    exit;
}

$pages = [
    '' => [
        'items' => [
            ['id' => 'ext-101', 'title' => 'Первый элемент', 'changed_at' => '2026-11-01 10:00:00'],
            ['id' => 'ext-102', 'title' => 'Второй элемент', 'changed_at' => '2026-11-02 11:30:00'],
        ],
        'next_cursor' => 'p2',
    ],
    'p2' => [
        'items' => [
            ['id' => 'ext-103', 'title' => 'Третий элемент', 'changed_at' => '2026-11-03 09:15:00'],
            /*
             * Запись без id: агент должен её пропустить и посчитать в skipped,
             * а не упасть на обработке всей страницы.
             */
            ['title' => 'Запись без идентификатора', 'changed_at' => '2026-11-03 09:20:00'],
        ],
        'next_cursor' => null,
    ],
];

if (!array_key_exists($cursor, $pages)) {
    http_response_code(400);
    echo json_encode(['error' => 'unknown cursor']);
    exit;
}

$page = $pages[$cursor];

echo json_encode([
    'items' => array_slice($page['items'], 0, $limit),
    'next_cursor' => $page['next_cursor'],
], JSON_UNESCAPED_UNICODE);
