<?php

declare(strict_types=1);

namespace MyCompany\Agents;

use Bitrix\Main\Mail\Event;

/**
 * КОНТРОЛЬНОЕ ЗАДАНИЕ: найти дефекты.
 *
 * Разбирает очередь писем: берёт строки со статусом new и «отправляет» их.
 * Код синтаксически валиден и запускается. Дефекты — поведенческие,
 * в комментариях они не отмечены.
 *
 * Эталон — fixed.php, разбор — README.md.
 */
class QueueControlAgent
{
    public const EVENT_NAME = 'CONTROL_QUEUE_MESSAGE';

    public static function execute()
    {
        $rows = ControlQueueTable::getList([
            'filter' => ['=STATUS' => ControlQueueTable::STATUS_NEW],
            'order' => ['ID' => 'ASC'],
        ])->fetchAll();

        if ($rows === []) {
            return;
        }

        foreach ($rows as $row) {
            $id = (int)$row['ID'];

            ControlQueueTable::update($id, [
                'STATUS' => ControlQueueTable::STATUS_SENT,
                'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
            ]);

            try {
                Event::send([
                    'EVENT_NAME' => self::EVENT_NAME,
                    'LID' => defined('SITE_ID') ? SITE_ID : 's1',
                    'C_FIELDS' => [
                        'EMAIL' => (string)$row['EMAIL'],
                    ],
                ]);
            } catch (\Exception $error) {
                self::logError($id, $error->getMessage());
                sleep(30);
            }
        }

        return self::getAgentName();
    }

    private static function getAgentName(): string
    {
        return __METHOD__ . '();';
    }

    private static function logError(int $id, string $message): void
    {
        \CEventLog::Add([
            'SEVERITY' => 'ERROR',
            'AUDIT_TYPE_ID' => 'CONTROL_QUEUE_AGENT',
            'MODULE_ID' => 'main',
            'ITEM_ID' => (string)$id,
            'DESCRIPTION' => $message,
        ]);
    }
}
