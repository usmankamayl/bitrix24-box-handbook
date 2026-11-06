<?php
/**
 * Минимальный клиент для REST API Bitrix24 через входящий вебхук.
 *
 * В реальном проекте используй готовую библиотеку:
 * https://github.com/bitrix-tools/crest
 */

class CRest
{
    // URL входящего вебхука: создаётся в Bitrix24
    // Раздел "Разработчикам" -> "Другое" -> "Входящий вебхук"
    // Права: CRM (crm)
    const WEBHOOK_URL = 'https://your-domain.bitrix24.ru/rest/1/XXXXXXXXXXXX/';

    /**
     * Одиночный вызов REST-метода.
     *
     * @param string $method Имя метода, например 'crm.contact.list'
     * @param array  $params Параметры запроса
     * @return array Декодированный ответ ['result' => ..., 'error' => ...]
     */
    public static function call(string $method, array $params = []): array
    {
        $url = self::WEBHOOK_URL . $method . '.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['error' => 'CURL: ' . $curlErr];
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['error' => 'Bad JSON: ' . $response];
    }

    /**
     * Пакетный вызов — до 50 команд за один HTTP-запрос.
     * Экономит лимиты REST (2 запроса/сек).
     *
     * @param array $commands ['cmd_name' => 'method?param1=val1&param2=val2', ...]
     * @return array Ответ batch-вызова: result['result']['cmd_name'] = ...
     */
    public static function callBatch(array $commands): array
    {
        // Разбиваем на чанки по 50 — ограничение batch
        $chunks = array_chunk($commands, 50, true);
        $allResults = [];

        foreach ($chunks as $chunk) {
            $response = self::call('batch', [
                'halt' => 0,           // не останавливаться при ошибке одной команды
                'cmd'  => $chunk,
            ]);

            if (isset($response['result']['result'])) {
                $allResults = array_merge($allResults, $response['result']['result']);
            }

            // Пауза между батчами — защита от rate limit
            usleep(500000); // 0.5 сек
        }

        return $allResults;
    }
}
