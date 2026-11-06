<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\DB\SqlQueryException;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Web\HttpClient;

/**
 * Агент получения изменений из внешнего API.
 *
 * Требования задачи и как они закрыты:
 *   - тайм-ауты                 -> опции HttpClient (socketTimeout/streamTimeout);
 *   - проверка статуса и JSON   -> false от get(), HTTP-код, json_decode с бросанием;
 *   - пагинация                 -> next_cursor в ответе, цикл по страницам;
 *   - контрольная точка         -> SyncStateTable.CURSOR, пишется в одной
 *                                  транзакции с порцией данных;
 *   - идемпотентное обновление  -> UNIQUE(EXTERNAL_ID) + update по нему;
 *   - ограниченные повторы      -> HTTP_ATTEMPTS на страницу, не на весь прогон;
 *   - токены не в логах         -> токен уходит заголовком, в отчёт попадает
 *                                  только путь URL.
 */
final class ApiSyncAgent
{
    public const AGENT_NAME = '\\MyCompany\\Agents\\ApiSyncAgent::execute();';

    /** Код провайдера: строка состояния на него. */
    public const PROVIDER = 'demo.items';

    /** Модуль и код опций, где лежат адрес и токен. */
    public const OPTION_MODULE = 'mycompany.agents';
    public const OPTION_URL = 'sync_api_url';
    public const OPTION_TOKEN = 'sync_api_token';

    /** Размер страницы. */
    public const PAGE_SIZE = 50;

    /** Сколько страниц максимум за один запуск. */
    public const MAX_PAGES_PER_RUN = 5;

    /** Бюджет времени на запуск. */
    public const MAX_RUNTIME_SECONDS = 20;

    /** Попыток на одну страницу. */
    public const HTTP_ATTEMPTS = 2;

    /** Пауза между попытками, микросекунды. */
    public const HTTP_RETRY_DELAY_US = 300000;

    /** Тайм-ауты HTTP, секунды. */
    public const SOCKET_TIMEOUT = 3;
    public const STREAM_TIMEOUT = 8;

    public const REPORT_FILE = '/local/var/agents/api-sync.jsonl';

    public static function execute(): string
    {
        try {
            self::run();
        } catch (\Throwable $error) {
            error_log(
                '[ApiSyncAgent] unhandled: ' . get_class($error)
                . ': ' . $error->getMessage()
            );
        }

        return self::AGENT_NAME;
    }

    /**
     * Один цикл синхронизации.
     *
     * @return array<string, mixed>
     */
    public static function run(): array
    {
        $startedAt = microtime(true);

        $report = [
            'event' => 'api_sync',
            'provider' => self::PROVIDER,
            'started_at' => date(DATE_ATOM),
            'pages' => 0,
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'cursor_before' => '',
            'cursor_after' => '',
            'completed' => false,
            'stop_reason' => '',
            'errors' => [],
        ];

        try {
            $state = self::loadState();

            $cursor = $state['CURSOR'] === null ? '' : (string)$state['CURSOR'];
            $report['cursor_before'] = self::maskCursor($cursor);

            for ($page = 1; $page <= self::MAX_PAGES_PER_RUN; $page++) {
                if (microtime(true) - $startedAt > self::MAX_RUNTIME_SECONDS) {
                    $report['stop_reason'] = 'time_budget';
                    break;
                }

                $response = self::fetchPage($cursor);

                $report['pages']++;
                $report['fetched'] += count($response['items']);

                $nextCursor = $response['next_cursor'];

                /*
                 * Данные порции и контрольная точка пишутся одной транзакцией
                 * (storePage): либо на диске обе половины, либо ни одной.
                 * Иначе курсор уезжает вперёд без данных и порция теряется
                 * навсегда.
                 */
                $counters = self::storePage(
                    $response['items'],
                    $nextCursor,
                    (int)$state['PAGES_DONE'] + 1,
                    (int)$state['ITEMS_DONE']
                );

                $report['created'] += $counters['created'];
                $report['updated'] += $counters['updated'];
                $report['skipped'] += $counters['skipped'];

                $state['PAGES_DONE'] = (int)$state['PAGES_DONE'] + 1;
                $state['ITEMS_DONE'] = (int)$state['ITEMS_DONE']
                    + $counters['created'] + $counters['updated'];

                if ($nextCursor === null || $nextCursor === '') {
                    $report['completed'] = true;
                    $report['stop_reason'] = 'completed';
                    $cursor = '';
                    break;
                }

                $cursor = $nextCursor;
            }

            if ($report['stop_reason'] === '') {
                $report['stop_reason'] = 'page_limit';
            }

            $report['cursor_after'] = self::maskCursor($cursor);
        } catch (\Throwable $error) {
            /*
             * Ошибка сети или разбора — не повод падать: контрольная точка уже
             * сохранена на последней успешной странице, следующий запуск
             * продолжит с неё.
             */
            $report['errors'][] = get_class($error) . ': ' . $error->getMessage();
            $report['stop_reason'] = 'error';
        }

        $report['duration_seconds'] = round(microtime(true) - $startedAt, 3);

        self::publish($report);

        return $report;
    }

    /**
     * Запрос одной страницы с ограниченными повторами.
     *
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    private static function fetchPage(string $cursor): array
    {
        $url = (string)Option::get(self::OPTION_MODULE, self::OPTION_URL, '');

        if ($url === '') {
            throw new \RuntimeException(
                'Не задан адрес API: опция ' . self::OPTION_MODULE . '/' . self::OPTION_URL
            );
        }

        $lastError = null;

        for ($attempt = 1; $attempt <= self::HTTP_ATTEMPTS; $attempt++) {
            try {
                $body = self::request(self::buildUrl($url, $cursor));

                return self::parsePage($body);
            } catch (\Throwable $error) {
                $lastError = $error;

                if ($attempt < self::HTTP_ATTEMPTS) {
                    /*
                     * Пауза допустима только потому, что агент работает вне
                     * HTTP-запроса посетителя (cron). На хитах так делать
                     * нельзя: это время ждёт пользователь.
                     */
                    usleep(self::HTTP_RETRY_DELAY_US);
                }
            }
        }

        throw new \RuntimeException(
            'Страница не получена за ' . self::HTTP_ATTEMPTS . ' попытки: '
            . ($lastError?->getMessage() ?? 'unknown')
        );
    }

    /**
     * HTTP-запрос. Возвращает тело ответа, при любой проблеме бросает.
     */
    private static function request(string $url): string
    {
        $http = new HttpClient([
            'socketTimeout' => self::SOCKET_TIMEOUT,
            'streamTimeout' => self::STREAM_TIMEOUT,
            'redirect' => true,
            'redirectMax' => 2,
        ]);

        $http->setHeader('Accept', 'application/json');
        $http->setHeader('User-Agent', 'MyCompany ApiSyncAgent');

        $token = (string)Option::get(self::OPTION_MODULE, self::OPTION_TOKEN, '');

        if ($token !== '') {
            $http->setHeader('Authorization', 'Bearer ' . $token);
        }

        $body = $http->get($url);

        /*
         * get() возвращает false при сетевой ошибке; пустая строка ошибкой не
         * является — её надо проверять отдельно.
         */
        if ($body === false) {
            throw new \RuntimeException(
                'HTTP-ошибка: ' . implode('; ', $http->getError())
            );
        }

        $status = (int)$http->getStatus();

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('HTTP-статус ' . $status);
        }

        if ($body === '') {
            throw new \RuntimeException('Пустой ответ API');
        }

        return $body;
    }

    /**
     * Разбор и проверка структуры ответа.
     *
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    private static function parsePage(string $body): array
    {
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('Некорректный JSON: ' . $error->getMessage());
        }

        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            throw new \RuntimeException('В ответе нет массива items');
        }

        $nextCursor = $data['next_cursor'] ?? null;

        if ($nextCursor !== null && !is_string($nextCursor)) {
            throw new \RuntimeException('next_cursor должен быть строкой или null');
        }

        return [
            'items' => array_values($data['items']),
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * Запись порции: данные и контрольная точка в одной транзакции.
     *
     * @param list<array<string, mixed>> $items
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    private static function storePage(
        array $items,
        ?string $nextCursor,
        int $pagesDone,
        int $itemsDone
    ): array {
        $counters = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            foreach ($items as $item) {
                $normalized = self::normalize($item);

                if ($normalized === null) {
                    $counters['skipped']++;
                    continue;
                }

                $outcome = self::upsert($normalized);

                if ($outcome === 'invalid') {
                    $counters['skipped']++;
                    continue;
                }

                $counters[$outcome]++;
            }

            self::saveState(
                $nextCursor,
                $pagesDone,
                $itemsDone + $counters['created'] + $counters['updated']
            );

            $connection->commitTransaction();
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();

            throw $error;
        }

        return $counters;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{external_id: string, title: string, changed_at: DateTime|null}|null
     */
    private static function normalize(array $item): ?array
    {
        if (!isset($item['id']) || !is_string($item['id']) || $item['id'] === '') {
            return null;
        }

        if (isset($item['title']) && !is_string($item['title'])) {
            return null;
        }

        $changedAt = null;

        if (isset($item['changed_at']) && is_string($item['changed_at']) && $item['changed_at'] !== '') {
            try {
                $changedAt = new DateTime($item['changed_at'], 'Y-m-d H:i:s');
            } catch (\Throwable $error) {
                return null;
            }
        }

        return [
            'external_id' => $item['id'],
            'title' => (string)($item['title'] ?? ''),
            'changed_at' => $changedAt,
        ];
    }

    /**
     * Идемпотентная запись: ищем по EXTERNAL_ID, обновляем или добавляем.
     *
     * Единственный случай гонки — два процесса одновременно вставляют один
     * EXTERNAL_ID; уникальный индекс ловит второй INSERT, и мы превращаем его
     * в update.
     *
     * @param array{external_id: string, title: string, changed_at: DateTime|null} $item
     *
     * @return string created|updated|invalid
     */
    private static function upsert(array $item): string
    {
        $existing = SyncItemTable::getList([
            'select' => ['ID'],
            'filter' => ['=EXTERNAL_ID' => $item['external_id']],
            'limit' => 1,
        ])->fetch();

        $now = new DateTime();

        if ($existing !== false) {
            $result = SyncItemTable::update((int)$existing['ID'], [
                'TITLE' => $item['title'],
                'CHANGED_AT' => $item['changed_at'],
                'SYNCED_AT' => $now,
            ]);

            return $result->isSuccess() ? 'updated' : 'invalid';
        }

        try {
            $result = SyncItemTable::add([
                'EXTERNAL_ID' => $item['external_id'],
                'TITLE' => $item['title'],
                'CHANGED_AT' => $item['changed_at'],
                'SYNCED_AT' => $now,
            ]);
        } catch (SqlQueryException $error) {
            /*
             * Параллельный процесс успел вставить строку. Обновляем её, а не
             * возвращаем ошибку: результат синхронизации один и тот же.
             */
            $existing = SyncItemTable::getList([
                'select' => ['ID'],
                'filter' => ['=EXTERNAL_ID' => $item['external_id']],
                'limit' => 1,
            ])->fetch();

            if ($existing === false) {
                throw $error;
            }

            $update = SyncItemTable::update((int)$existing['ID'], [
                'TITLE' => $item['title'],
                'CHANGED_AT' => $item['changed_at'],
                'SYNCED_AT' => $now,
            ]);

            return $update->isSuccess() ? 'updated' : 'invalid';
        }

        if (!$result->isSuccess()) {
            return 'invalid';
        }

        return 'created';
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadState(): array
    {
        $row = SyncStateTable::getById(self::PROVIDER)->fetch();

        if ($row !== false) {
            return $row;
        }

        SyncStateTable::add([
            'CODE' => self::PROVIDER,
            'CURSOR' => null,
            'PAGES_DONE' => 0,
            'ITEMS_DONE' => 0,
            'UPDATED_AT' => new DateTime(),
        ]);

        return [
            'CODE' => self::PROVIDER,
            'CURSOR' => null,
            'PAGES_DONE' => 0,
            'ITEMS_DONE' => 0,
        ];
    }

    private static function saveState(?string $cursor, int $pagesDone, int $itemsDone): void
    {
        SyncStateTable::update(self::PROVIDER, [
            'CURSOR' => $cursor,
            'PAGES_DONE' => $pagesDone,
            'ITEMS_DONE' => $itemsDone,
            'UPDATED_AT' => new DateTime(),
        ]);
    }

    private static function buildUrl(string $base, string $cursor): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';

        $url = $base . $separator . http_build_query([
            'limit' => self::PAGE_SIZE,
            'cursor' => $cursor,
        ]);

        return $url;
    }

    /**
     * Курсор в отчёт — обрезанным: полный курсор бывает подписанным токеном,
     * а токены в журналы не попадают. Сам адрес API в отчёт не пишется вовсе.
     */
    private static function maskCursor(string $cursor): string
    {
        if ($cursor === '') {
            return '(начало)';
        }

        return mb_substr($cursor, 0, 16);
    }

    /**
     * @param array<string, mixed> $report
     */
    private static function publish(array $report): void
    {
        try {
            $line = json_encode(
                $report,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_THROW_ON_ERROR
            ) . PHP_EOL;

            $path = Application::getDocumentRoot() . self::REPORT_FILE;
            $directory = dirname($path);

            if (!is_dir($directory)) {
                @mkdir($directory, 0700, true);
            }

            @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $error) {
            error_log('[ApiSyncAgent] report: ' . $error->getMessage());
        }
    }
}
