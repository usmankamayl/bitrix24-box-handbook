<?php
/**
 * Курсорная пагинация (keyset) vs OFFSET — сравнение и реализация.
 *
 * Senior-проблема: классическая пагинация через OFFSET
 * на таблице в 1 млн записей становится медленной
 * к странице 5000.
 *
 * Решение: "after ID" — курсорная пагинация.
 * LIMIT 20 без OFFSET, с WHERE id > last_seen_id.
 */

namespace Vendor\Shop\Pagination;

// ============================================================
// ВАРИАНТ 1: OFFSET — простой, но медленный на больших данных
// ============================================================

class OffsetPaginator
{
    public function getPage(int $page, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        // На странице 5000: LIMIT 100000, 20
        // Читает 100000 строк, отбрасывает, отдаёт 20
        $res = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y'],
            false,
            [
                'iNumPage'  => $page,
                'nPageSize' => $perPage,
            ],
            ['ID', 'NAME', 'PREVIEW_TEXT']
        );

        $items = [];
        while ($item = $res->GetNext()) {
            $items[] = $item;
        }

        return [
            'items'      => $items,
            'total'      => $res->NavRecordCount,
            'page'       => $page,
            'per_page'   => $perPage,
            'total_pages'=> $res->NavPageCount,
        ];
    }
}


// ============================================================
// ВАРИАНТ 2: Курсорная пагинация — быстрая на любой глубине
// ============================================================

class CursorPaginator
{
    private string $cursorColumn = 'ID'; // колонка для курсора

    /**
     * Получить страницу после указанного курсора.
     *
     * @param int|null $cursor  ID последнего элемента предыдущей страницы
     * @param int      $perPage элементов на странице
     * @return array{items: array, next_cursor: int|null, has_more: bool}
     */
    public function getPageAfter(?int $cursor, int $perPage = 20): array
    {
        $filter = ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y'];

        // Курсор: "покажи следующие после ID=12345"
        if ($cursor) {
            $filter['>' . $this->cursorColumn] = $cursor;
        }

        // LIMIT без OFFSET — читает ровно $perPage+1 строк
        // +1 чтобы понять, есть ли следующая страница
        $res = \CIBlockElement::GetList(
            [$this->cursorColumn => 'ASC'],
            $filter,
            false,
            ['nTopCount' => $perPage + 1],
            ['ID', 'NAME', 'PREVIEW_TEXT']
        );

        $items = [];
        while ($item = $res->GetNext()) {
            $items[] = $item;
        }

        // Определяем, есть ли ещё страницы
        $hasMore = count($items) > $perPage;
        if ($hasMore) {
            array_pop($items); // убираем "лишний" элемент
        }

        return [
            'items'       => $items,
            'next_cursor' => $hasMore ? end($items)[$this->cursorColumn] : null,
            'has_more'    => $hasMore,
        ];
    }

    /**
     * Итератор для обхода ВСЕХ записей курсором.
     * Идеально для экспорта, импорта, индексации — без нагрузки на БД.
     */
    public function iterateAll(int $batchSize = 1000): \Generator
    {
        $cursor = null;

        while (true) {
            $result = $this->getPageAfter($cursor, $batchSize);

            foreach ($result['items'] as $item) {
                yield $item;
            }

            if (!$result['has_more']) {
                break;
            }
            $cursor = $result['next_cursor'];
        }
    }
}


// ============================================================
// Курсорная пагинация для API — в реальном проекте
// ============================================================

class ApiCursorPaginator
{
    /**
     * REST API формат:
     *   GET /api/products?cursor=eyJpZCI6MTIzfQ&limit=20
     *
     * Курсор — base64 JSON, чтобы не светить внутренние ID
     * и поддерживать сложные курсоры (несколько колонок).
     */

    public function encodeCursor(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    public function decodeCursor(string $cursor): array
    {
        $decoded = json_decode(base64_decode($cursor), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getPage(string $cursorStr, int $limit = 20): array
    {
        $cursor = $this->decodeCursor($cursorStr);

        $filter = ['IBLOCK_ID' => 5, 'ACTIVE' => 'Y'];

        // Составной курсор: (ID > X) или (ID = X AND sort > Y)
        // для сортировки по нескольким полям
        if (!empty($cursor['last_id'])) {
            $filter['>ID'] = $cursor['last_id'];
        }

        $res = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            $filter,
            false,
            ['nTopCount' => $limit + 1],
            ['ID', 'NAME', 'PRICE']
        );

        $items = [];
        while ($item = $res->GetNext()) {
            $items[] = $item;
        }

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        return [
            'data'      => $items,
            'meta'      => [
                'has_more'    => $hasMore,
                'next_cursor' => $hasMore
                    ? $this->encodeCursor(['last_id' => end($items)['ID']])
                    : null,
            ],
        ];
    }
}


// ============================================================
// БЕНЧМАРК — сравнение на большой таблице
// ============================================================

function benchmarkPagination(): void
{
    $page = 5000;  // "глубокая" страница

    // OFFSET — замеряем время
    $start = microtime(true);
    $res = \CIBlockElement::GetList(
        ['ID' => 'ASC'],
        ['IBLOCK_ID' => 5],
        false,
        ['iNumPage' => $page, 'nPageSize' => 20],
        ['ID']
    );
    while ($res->GetNext()) {}
    $offsetTime = microtime(true) - $start;

    // Курсор — то же самое, но через WHERE
    $start = microtime(true);
    // Симулируем: "на странице 5000, последний ID был 99980"
    $res = \CIBlockElement::GetList(
        ['ID' => 'ASC'],
        ['IBLOCK_ID' => 5, '>ID' => 99980],
        false,
        ['nTopCount' => 20],
        ['ID']
    );
    while ($res->GetNext()) {}
    $cursorTime = microtime(true) - $start;

    printf("Page %d:\n", $page);
    printf("  OFFSET: %.4fs\n", $offsetTime);
    printf("  Cursor: %.4fs\n", $cursorTime);
    printf("  Speedup: %.1fx\n", $offsetTime / $cursorTime);
}
