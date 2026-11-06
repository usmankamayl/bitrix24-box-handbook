<?php
/**
 * Демонстрация автозагрузки классов D7.
 *
 * Файл: /local/modules/mycompany.hello/lib/Helper.php
 * Namespace: Mycompany\Hello (соответствует MODULE_ID mycompany.hello)
 *
 * Автозагрузка работает после Loader::includeModule('mycompany.hello'):
 * include.php модуля регистрирует namespace Mycompany\Hello на каталог lib/
 * явным вызовом Loader::registerNamespace(). Регистрация в коде модуля, а не
 * в init.php проекта, — потому что namespace принадлежит модулю.
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
        ];
    }
}
