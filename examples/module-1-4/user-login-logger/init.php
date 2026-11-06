<?php

/**
 * Задача 1: логирование попыток входа и выхода пользователей.
 *
 * Подключение в /local/php_interface/init.php:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/user-login-logger/init.php';
 *
 * Почему Compatible: OnAfterUserLogin, OnUserLogout и OnAfterUserLogout
 * отправляются старым ядром (GetModuleEvents + ExecuteModuleEventEx) и передают
 * позиционные аргументы, а OnAfterUserLogin — ещё и по ссылке. Подписка через
 * addEventHandler() отдала бы в обработчик объект Main\Event.
 */

use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

$eventManager->addEventHandlerCompatible(
    'main',
    'OnAfterUserLogin',
    [UserLoginLogger::class, 'onAfterLogin']
);

$eventManager->addEventHandlerCompatible(
    'main',
    'OnUserLogout',
    [UserLoginLogger::class, 'onLogout']
);

/**
 * Пишет строки в лог-файл с простой ротацией.
 *
 * Что здесь важно понять, а не скопировать:
 * - пароль не логируется ни при каком исходе, даже если ядро его передало;
 * - IP берётся из REMOTE_ADDR через Context, а не из X-Forwarded-For:
 *   этот заголовок подставляет клиент, и доверять ему без своего прокси нельзя;
 * - запись идёт с LOCK_EX, иначе два параллельных хита перемешают строки.
 */
final class UserLoginLogger
{
    private const LOG_DIR = '/local/logs';
    private const LOG_FILE = 'user_login.log';
    private const MAX_SIZE = 5 * 1024 * 1024;

    /**
     * Обработчик main::OnAfterUserLogin.
     *
     * Контракт события: array &$arParams, где
     *   USER_ID         — ID пользователя или 0 при неудаче;
     *   LOGIN           — введённый логин;
     *   PASSWORD        — пароль или его md5 (не логируем);
     *   RESULT_MESSAGE  — true при успехе либо массив ['TYPE', 'MESSAGE'].
     *
     * Неуспешный вход — это USER_ID <= 0. Отдельного события
     * «логин не удался» в модуле main нет.
     */
    public static function onAfterLogin(array &$arParams): void
    {
        $userId = (int)($arParams['USER_ID'] ?? 0);
        $login = (string)($arParams['LOGIN'] ?? '');

        if ($userId > 0) {
            self::write(sprintf(
                'LOGIN OK | user_id=%d | login=%s | ip=%s | ua=%s',
                $userId,
                self::sanitize($login),
                self::getIp(),
                self::getUserAgent()
            ));

            return;
        }

        $message = $arParams['RESULT_MESSAGE'] ?? '';
        if (is_array($message)) {
            $message = (string)($message['MESSAGE'] ?? '');
        } elseif ($message === true) {
            $message = '';
        }

        self::write(sprintf(
            'LOGIN FAIL | login=%s | reason=%s | ip=%s | ua=%s',
            self::sanitize($login),
            self::sanitize((string)$message),
            self::getIp(),
            self::getUserAgent()
        ));
    }

    /**
     * Обработчик main::OnUserLogout — параметр один: ID пользователя.
     */
    public static function onLogout($userId): void
    {
        self::write(sprintf(
            'LOGOUT | user_id=%d | ip=%s',
            (int)$userId,
            self::getIp()
        ));
    }

    private static function write(string $line): void
    {
        $path = self::getLogPath();
        $entry = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $line);

        if (@file_put_contents($path, $entry, FILE_APPEND | LOCK_EX) === false) {
            // Лог не должен ломать авторизацию: молча выходим.
            return;
        }

        self::rotate($path);
    }

    private static function getLogPath(): string
    {
        $dir = $_SERVER['DOCUMENT_ROOT'] . self::LOG_DIR;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // /local/ лежит внутри DOCUMENT_ROOT и доступен по HTTP.
        // Закрываем каталог логов на уровне веб-сервера (Apache).
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }

        return $dir . '/' . self::LOG_FILE;
    }

    private static function rotate(string $path): void
    {
        if (!is_file($path) || filesize($path) < self::MAX_SIZE) {
            return;
        }

        @rename($path, $path . '.' . date('Y-m-d_His') . '.bak');

        $backups = glob($path . '.*.bak') ?: [];
        if (count($backups) <= 5) {
            return;
        }

        usort($backups, static fn(string $a, string $b): int => filemtime($a) <=> filemtime($b));

        foreach (array_slice($backups, 0, count($backups) - 5) as $old) {
            @unlink($old);
        }
    }

    private static function getIp(): string
    {
        return (string)\Bitrix\Main\Context::getCurrent()->getRequest()->getRemoteAddress();
    }

    private static function getUserAgent(): string
    {
        return self::sanitize((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 200);
    }

    /**
     * Лог — текстовый файл построчного формата: убираем переводы строк,
     * чтобы подставленный логин не создал фальшивую запись.
     */
    private static function sanitize(string $value, int $maxLength = 100): string
    {
        $value = str_replace(["\r", "\n", '|'], [' ', ' ', '/'], $value);

        return mb_substr($value, 0, $maxLength);
    }
}
