<?php
/**
 * Логирование попыток входа пользователей
 *
 * Этот файл нужно подключить в /local/php_interface/init.php:
 * require_once $_SERVER['DOCUMENT_ROOT'] . '/local/examples/user-login-logger/init.php';
 */

use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

// Регистрируем обработчики событий входа и выхода
$eventManager->addEventHandler(
    'main',
    'OnAfterUserLogin',
    'UserLoginLogger::onAfterLogin'
);

$eventManager->addEventHandler(
    'main',
    'OnUserLoginFail',
    'UserLoginLogger::onLoginFail'
);

$eventManager->addEventHandler(
    'main',
    'OnUserLogout',
    'UserLoginLogger::onLogout'
);

/**
 * Класс для логирования действий пользователей
 */
class UserLoginLogger
{
    /**
     * Путь к файлу логов
     */
    const LOG_FILE = '/local/logs/user_login.log';

    /**
     * Обработчик успешного входа пользователя
     *
     * @param array $arUser Данные пользователя
     * @return void
     */
    public static function onAfterLogin($arUser)
    {
        $userId = $arUser['user_fields']['ID'] ?? $arUser['ID'] ?? 'unknown';
        $login = $arUser['user_fields']['LOGIN'] ?? $arUser['LOGIN'] ?? 'unknown';

        $message = sprintf(
            "[%s] SUCCESS LOGIN | User ID: %s | Login: %s | IP: %s | User Agent: %s",
            date('Y-m-d H:i:s'),
            $userId,
            $login,
            self::getUserIP(),
            self::getUserAgent()
        );

        self::writeLog($message);
    }

    /**
     * Обработчик неудачной попытки входа
     *
     * @param string $login Логин пользователя
     * @param string $password Пароль (не записываем в лог!)
     * @param string $strError Текст ошибки
     * @return void
     */
    public static function onLoginFail($login, $password, $strError)
    {
        $message = sprintf(
            "[%s] FAILED LOGIN | Login: %s | Error: %s | IP: %s | User Agent: %s",
            date('Y-m-d H:i:s'),
            $login,
            $strError,
            self::getUserIP(),
            self::getUserAgent()
        );

        self::writeLog($message);
    }

    /**
     * Обработчик выхода пользователя
     *
     * @param int $userId ID пользователя
     * @return void
     */
    public static function onLogout($userId)
    {
        global $USER;

        $login = $USER->GetLogin() ?: 'unknown';

        $message = sprintf(
            "[%s] LOGOUT | User ID: %s | Login: %s | IP: %s",
            date('Y-m-d H:i:s'),
            $userId,
            $login,
            self::getUserIP()
        );

        self::writeLog($message);
    }

    /**
     * Записывает сообщение в файл лога
     *
     * @param string $message Сообщение для записи
     * @return void
     */
    private static function writeLog($message)
    {
        $logPath = $_SERVER['DOCUMENT_ROOT'] . self::LOG_FILE;
        $logDir = dirname($logPath);

        // Создаём директорию для логов, если её нет
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Записываем сообщение в файл
        file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND);

        // Ограничиваем размер файла (5 МБ)
        self::rotateLogs($logPath);
    }

    /**
     * Ротация логов при превышении размера
     *
     * @param string $logPath Путь к файлу лога
     * @return void
     */
    private static function rotateLogs($logPath)
    {
        $maxSize = 5 * 1024 * 1024; // 5 МБ

        if (file_exists($logPath) && filesize($logPath) > $maxSize) {
            $backupPath = $logPath . '.' . date('Y-m-d_H-i-s') . '.bak';
            rename($logPath, $backupPath);

            // Удаляем старые бэкапы (оставляем только последние 5)
            $logDir = dirname($logPath);
            $backups = glob($logDir . '/*.bak');

            if (count($backups) > 5) {
                usort($backups, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });

                $toDelete = array_slice($backups, 0, count($backups) - 5);
                foreach ($toDelete as $file) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Получает IP-адрес пользователя
     *
     * @return string
     */
    private static function getUserIP()
    {
        // Проверяем различные заголовки для получения реального IP
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Если несколько IP через запятую, берём первый
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Валидация IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Получает User Agent пользователя
     *
     * @return string
     */
    private static function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
}
