<?php
/**
 * Минимальный клиент CRest для облачного Bitrix24, с поддержкой batch.
 */

define('C_REST_WEB_HOOK_URL', 'https://your-domain.bitrix24.ru/rest/1/XXXXXXXXXXXX/');

class CRest
{
    public static function call(string $method, array $params = [])
    {
        return self::request($method, $params);
    }

    /**
     * Пакетный вызов: до 50 команд за один HTTP-запрос.
     * $commands — ассоциативный массив ['имя_команды' => ['method' => ..., 'params' => [...]]]
     */
    public static function callBatch(array $commands, bool $halt = false)
    {
        $cmd = [];
        foreach ($commands as $key => $command) {
            $cmd[$key] = $command['method'] . '?' . http_build_query($command['params'] ?? []);
        }

        return self::request('batch', [
            'halt' => $halt ? 1 : 0,
            'cmd'  => $cmd,
        ]);
    }

    private static function request(string $method, array $params)
    {
        $url = C_REST_WEB_HOOK_URL . $method . '.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'curl', 'error_description' => $error];
        }
        return json_decode($response, true);
    }
}
