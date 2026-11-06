<?php
/**
 * Обработчик регистрации пользователя с отправкой Welcome-письма
 *
 * Этот класс подключается к событию OnAfterUserAdd и отправляет
 * персонализированное приветственное письмо новому пользователю
 */

namespace Local\Handlers;

use Bitrix\Main\Mail\Event;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Loader;

class UserRegistrationHandler
{
    /**
     * Регистрация обработчика события
     * Вызывать в init.php или при установке модуля
     */
    public static function register()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->addEventHandler(
            'main',
            'OnAfterUserAdd',
            [self::class, 'onAfterUserAdd']
        );
    }

    /**
     * Обработчик события добавления пользователя
     *
     * @param array $fields Поля пользователя
     */
    public static function onAfterUserAdd(&$fields)
    {
        // Проверяем, что пользователь успешно создан
        if (!isset($fields['ID']) || !$fields['ID']) {
            return;
        }

        // Загружаем модуль main
        Loader::includeModule('main');

        // Получаем полные данные пользователя
        $user = \CUser::GetByID($fields['ID'])->Fetch();

        if (!$user) {
            return;
        }

        // Определяем бонусные баллы за регистрацию
        $bonusPoints = self::calculateBonusPoints($user);

        // Сохраняем бонусные баллы в профиль пользователя (если есть поле)
        if ($bonusPoints > 0) {
            self::addBonusPoints($user['ID'], $bonusPoints);
        }

        // Получаем название сайта
        $site = \CSite::GetByID($fields['LID'] ?? SITE_ID)->Fetch();
        $siteName = $site['NAME'] ?? 'Наш сайт';
        $serverName = $site['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];

        // Формируем дату регистрации
        $registerDate = new DateTime();
        $formattedDate = $registerDate->format('d.m.Y H:i');

        // Подготавливаем данные для письма
        $emailFields = [
            'USER_ID' => $user['ID'],
            'USER_NAME' => $user['NAME'] ?: 'Пользователь',
            'USER_LAST_NAME' => $user['LAST_NAME'] ?: '',
            'USER_EMAIL' => $user['EMAIL'],
            'USER_LOGIN' => $user['LOGIN'],
            'SITE_NAME' => $siteName,
            'SERVER_NAME' => $serverName,
            'REGISTER_DATE' => $formattedDate,
            'BONUS_POINTS' => $bonusPoints
        ];

        // Отправляем почтовое событие
        $result = Event::send([
            'EVENT_NAME' => 'USER_WELCOME',
            'LID' => $fields['LID'] ?? SITE_ID,
            'C_FIELDS' => $emailFields
        ]);

        // Логируем результат отправки
        if ($result->isSuccess()) {
            \AddMessage2Log(
                "Welcome email sent to user #{$user['ID']} ({$user['EMAIL']})",
                'user_registration'
            );
        } else {
            \AddMessage2Log(
                "Failed to send welcome email to user #{$user['ID']}: " . implode(', ', $result->getErrorMessages()),
                'user_registration',
                'ERROR'
            );
        }
    }

    /**
     * Расчет бонусных баллов за регистрацию
     *
     * @param array $user Данные пользователя
     * @return int Количество бонусных баллов
     */
    private static function calculateBonusPoints(array $user): int
    {
        $points = 100; // Базовые баллы

        // Дополнительные баллы, если указано имя
        if (!empty($user['NAME'])) {
            $points += 50;
        }

        // Дополнительные баллы, если указан телефон
        if (!empty($user['PERSONAL_PHONE']) || !empty($user['PERSONAL_MOBILE'])) {
            $points += 50;
        }

        // Дополнительные баллы за регистрацию в определенный период (акция)
        $currentHour = (int)date('H');
        if ($currentHour >= 10 && $currentHour <= 12) {
            $points += 100; // Утренняя акция
        }

        return $points;
    }

    /**
     * Начисление бонусных баллов пользователю
     *
     * @param int $userId ID пользователя
     * @param int $points Количество баллов
     */
    private static function addBonusPoints(int $userId, int $points)
    {
        // Если используется модуль sale, можно начислить баллы через его API
        if (Loader::includeModule('sale')) {
            try {
                \Bitrix\Sale\Internals\UserAccountTable::add([
                    'USER_ID' => $userId,
                    'CURRENCY' => 'RUB',
                    'CURRENT_BUDGET' => $points
                ]);
            } catch (\Exception $e) {
                \AddMessage2Log("Failed to add bonus points: " . $e->getMessage(), 'user_registration');
            }
        }

        // Альтернативный вариант - сохранить в пользовательском поле
        $userObj = new \CUser();
        $userObj->Update($userId, [
            'UF_BONUS_POINTS' => $points // Требуется создать пользовательское поле
        ]);
    }

    /**
     * Отправка тестового письма
     * Для проверки шаблона без регистрации пользователя
     *
     * @param string $email Email для отправки
     */
    public static function sendTestEmail(string $email)
    {
        Event::send([
            'EVENT_NAME' => 'USER_WELCOME',
            'LID' => SITE_ID,
            'C_FIELDS' => [
                'USER_ID' => 999,
                'USER_NAME' => 'Тестовый',
                'USER_LAST_NAME' => 'Пользователь',
                'USER_EMAIL' => $email,
                'USER_LOGIN' => 'test_user',
                'SITE_NAME' => 'Тестовый сайт',
                'SERVER_NAME' => $_SERVER['HTTP_HOST'],
                'REGISTER_DATE' => date('d.m.Y H:i'),
                'BONUS_POINTS' => 200
            ]
        ]);

        echo "Тестовое письмо отправлено на {$email}";
    }
}
