<?php
/**
 * Минимальный клиент CRest для облачного Bitrix24.
 * Для реального приложения используйте официальный SDK или храните
 * токены в БД и обновляйте через refresh_token.
 */

define('C_REST_WEB_HOOK_URL', 'https://your-domain.bitrix24.ru/rest/1/XXXXXXXXXXXX/');
// или для OAuth-приложения — см. официальный пример CRest на GitHub

class CRest
{
    public static function call(string $method, array $params = [])
    {
        $url = C_REST_WEB_HOOK_URL . $method . '.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'curl', 'error_description' => $error];
        }

        return json_decode($response, true) ?? [];
    }
}
