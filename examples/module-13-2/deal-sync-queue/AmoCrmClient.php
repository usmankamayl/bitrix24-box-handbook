<?php

require_once __DIR__ . '/config.php';

final class AmoCrmClient
{
    public function systemName(): string
    {
        return 'amocrm';
    }

    public function createDeal(array $payload): string
    {
        $response = $this->request('POST', '/api/v4/leads', [$payload]);

        return (string) $response['_embedded']['leads'][0]['id'];
    }

    public function updateDeal(string $externalId, array $payload): void
    {
        $payload['id'] = (int) $externalId;
        $this->request('PATCH', '/api/v4/leads', [$payload]);
    }

    /**
     * Ищет сделку в AmoCRM по кастомному полю, в которое при создании
     * был записан ID исходной сделки Bitrix24 — используется для идемпотентного
     * восстановления после таймаута на создании (см. SyncService::sync()).
     */
    public function findDealByBitrixId(int $bitrixDealId): ?array
    {
        $query = http_build_query([
            'filter' => [
                'custom_fields_values' => [
                    [
                        'field_id' => AMOCRM_BITRIX_ID_FIELD_ID,
                        'values' => [(string) $bitrixDealId],
                    ],
                ],
            ],
        ]);

        $response = $this->request('GET', '/api/v4/leads?' . $query);
        $leads = $response['_embedded']['leads'] ?? [];

        return $leads[0] ?? null;
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init('https://' . AMOCRM_DOMAIN . $path);
        $headers = [
            'Authorization: Bearer ' . AMOCRM_ACCESS_TOKEN,
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new RuntimeException("AmoCRM API error ({$httpCode}): " . $raw);
        }

        return json_decode($raw, true) ?? [];
    }
}
