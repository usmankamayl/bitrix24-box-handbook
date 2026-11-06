<?php

require_once __DIR__ . '/EntityMapper.php';
require_once __DIR__ . '/AmoCrmClient.php';

final class SyncService
{
    public function __construct(
        private readonly EntityMapper $mapper,
        private readonly AmoCrmClient $client,
    ) {
    }

    public function syncDeal(int $bitrixDealId): void
    {
        $deal = \CCrmDeal::GetByID($bitrixDealId);

        if (!$deal) {
            // Сделку удалили между постановкой задачи в очередь и обработкой —
            // не ошибка, просто нечего синхронизировать
            return;
        }

        $payload = $this->mapDealFields($deal, $bitrixDealId);
        $hash = md5(json_encode($payload));

        $mapping = $this->mapper->find('deal', $bitrixDealId, $this->client->systemName());

        if ($mapping) {
            if ($mapping['last_synced_hash'] === $hash) {
                // Синхронизируемые поля не изменились с прошлой отправки — пропускаем,
                // экономим лимит запросов AmoCRM
                return;
            }

            $this->client->updateDeal($mapping['external_id'], $payload);
            $this->mapper->updateHash((int) $mapping['id'], $hash);
            return;
        }

        // Мэппинга нет — либо это первая синхронизация, либо прошлая попытка создания
        // упала по таймауту уже ПОСЛЕ того, как AmoCRM фактически создал сделку.
        // Поэтому сначала ищем по "якорю" (bitrix_deal_id в кастомном поле), а не
        // сразу создаём — иначе при повторной обработке задачи получим дубль.
        $found = $this->client->findDealByBitrixId($bitrixDealId);
        $externalId = $found['id'] ?? null;

        if ($externalId === null) {
            $externalId = $this->client->createDeal($payload);
        }

        $this->mapper->save('deal', $bitrixDealId, $this->client->systemName(), (string) $externalId, $hash);
    }

    private function mapDealFields(array $deal, int $bitrixDealId): array
    {
        return [
            'name' => $deal['TITLE'],
            'price' => (int) $deal['OPPORTUNITY'],
            'custom_fields_values' => [[
                'field_id' => AMOCRM_BITRIX_ID_FIELD_ID,
                'values' => [['value' => (string) $bitrixDealId]],
            ]],
        ];
    }
}
