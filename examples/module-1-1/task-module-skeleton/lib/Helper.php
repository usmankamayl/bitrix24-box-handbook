<?php
/**
 * Демонстрация автозагрузки классов D7.
 *
 * Файл: /local/modules/mycompany.hello/lib/Helper.php
 * Namespace: Mycompany\Hello (соответствует MODULE_ID mycompany.hello)
 *
 * Автозагрузка работает автоматически после ModuleManager::registerModule —
 * класс подхватится по namespace, без registerAutoLoadClasses.
 */

namespace Mycompany\Hello;

use Bitrix\Main\Type\DateTime;

class Helper
{
    /**
     * Приветственное сообщение.
     */
    public static function getMessage(string $name = 'Гость'): string
    {
        return sprintf('Привет, %s! Добро пожаловать в Bitrix D7.', $name);
    }

    /**
     * Информация о текущем времени — через D7 Type API, а не date().
     */
    public static function getTimeInfo(): array
    {
        $now = new DateTime();

        return [
            'timestamp' => $now->getTimestamp(),
            'date' => $now->format('d.m.Y H:i:s'),
            'timezone' => $now->getTimeZone()->getName(),
        ];
    }
}
