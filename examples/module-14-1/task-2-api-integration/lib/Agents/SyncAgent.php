<?php
/**
 * Агент синхронизации данных с внешними системами
 *
 * Запускается каждые 10 минут для:
 * - Синхронизации заказов
 * - Обновления статусов
 * - Очистки старых логов
 */

namespace Acme\Integration\Agents;

use Acme\Integration\Api\CrmClient;
use Acme\Integration\Service\LogService;
use Bitrix\Main\Config\Option;

class SyncAgent
{
    /**
     * Запуск агента
     *
     * @return string Строка для следующего запуска
     */
    public static function run(): string
    {
        try {
            // Проверяем, включена ли синхронизация
            $syncEnabled = Option::get('acme.integration', 'sync_enabled', 'Y');

            if ($syncEnabled !== 'Y') {
                AddMessage2Log('SyncAgent: Синхронизация отключена', 'acme.integration');
                return '\Acme\Integration\Agents\SyncAgent::run();';
            }

            // 1. Синхронизация данных
            self::syncData();

            // 2. Очистка старых логов (раз в сутки)
            if (date('H:i') === '03:00') {
                self::cleanLogs();
            }

            AddMessage2Log('SyncAgent: Выполнено успешно', 'acme.integration');

        } catch (\Exception $e) {
            AddMessage2Log(
                'SyncAgent: Ошибка - ' . $e->getMessage(),
                'acme.integration'
            );
        }

        return '\Acme\Integration\Agents\SyncAgent::run();';
    }

    /**
     * Синхронизация данных с внешней CRM
     */
    protected static function syncData(): void
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $crmClient = new CrmClient();

        // Получаем контакты, созданные за последний час
        $contacts = \Bitrix\Crm\ContactTable::getList([
            'filter' => [
                '>DATE_CREATE' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'UF_EXTERNAL_ID' => false, // еще не синхронизированные
            ],
            'limit' => 100,
        ])->fetchAll();

        $synced = 0;

        foreach ($contacts as $contact) {
            // Отправляем во внешнюю CRM
            $result = $crmClient->createContact([
                'name' => trim($contact['NAME'] . ' ' . $contact['LAST_NAME']),
                'email' => self::getContactEmail($contact['ID']),
                'phone' => self::getContactPhone($contact['ID']),
            ]);

            if ($result->isSuccess()) {
                $data = $result->getData();

                // Сохраняем внешний ID
                \Bitrix\Crm\ContactTable::update($contact['ID'], [
                    'UF_EXTERNAL_ID' => $data['id'] ?? null,
                ]);

                $synced++;
            }
        }

        if ($synced > 0) {
            AddMessage2Log("SyncAgent: Синхронизировано контактов: {$synced}", 'acme.integration');
        }
    }

    /**
     * Получить email контакта
     *
     * @param int $contactId
     * @return string|null
     */
    protected static function getContactEmail(int $contactId): ?string
    {
        $fm = \CCrmFieldMulti::GetList(
            [],
            [
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => 'EMAIL',
            ]
        );

        if ($item = $fm->Fetch()) {
            return $item['VALUE'];
        }

        return null;
    }

    /**
     * Получить телефон контакта
     *
     * @param int $contactId
     * @return string|null
     */
    protected static function getContactPhone(int $contactId): ?string
    {
        $fm = \CCrmFieldMulti::GetList(
            [],
            [
                'ENTITY_ID' => 'CONTACT',
                'ELEMENT_ID' => $contactId,
                'TYPE_ID' => 'PHONE',
            ]
        );

        if ($item = $fm->Fetch()) {
            return $item['VALUE'];
        }

        return null;
    }

    /**
     * Очистка старых логов
     */
    protected static function cleanLogs(): void
    {
        $logger = new LogService();

        // Хранить логи 30 дней
        $days = (int)Option::get('acme.integration', 'log_retention_days', 30);

        $logger->cleanOldLogs($days);

        AddMessage2Log("SyncAgent: Удалены логи старше {$days} дней", 'acme.integration');
    }
}
