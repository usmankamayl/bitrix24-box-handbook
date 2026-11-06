<?php
/**
 * Агент для отправки еженедельного дайджеста новостей
 *
 * Запускается раз в неделю (например, каждое воскресенье)
 * Собирает новости за неделю и отправляет подписчикам
 */

namespace Local\Agents;

use Bitrix\Main\Mail\Event;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

class WeeklyDigestAgent
{
    /** @var int ID инфоблока с новостями */
    const NEWS_IBLOCK_ID = 1; // ИЗМЕНИТЬ НА СВОЙ

    /** @var int Максимальное количество новостей в дайджесте */
    const MAX_NEWS_COUNT = 5;

    /**
     * Основной метод агента
     *
     * @return string Строка для следующего запуска
     */
    public static function sendWeeklyDigest()
    {
        if (!Loader::includeModule('iblock')) {
            \AddMessage2Log('Iblock module not loaded', 'weekly_digest', 'ERROR');
            return self::getAgentName();
        }

        // Получаем новости за неделю
        $news = self::getWeekNews();

        if (empty($news)) {
            \AddMessage2Log('No news found for weekly digest', 'weekly_digest');
            return self::getAgentName();
        }

        // Получаем список подписчиков
        $subscribers = self::getSubscribers();

        if (empty($subscribers)) {
            \AddMessage2Log('No subscribers found', 'weekly_digest');
            return self::getAgentName();
        }

        // Отправляем дайджест каждому подписчику
        $sentCount = 0;
        foreach ($subscribers as $subscriber) {
            if (self::sendDigestToUser($subscriber, $news)) {
                $sentCount++;
            }
        }

        \AddMessage2Log(
            "Weekly digest sent to {$sentCount} subscribers",
            'weekly_digest'
        );

        return self::getAgentName();
    }

    /**
     * Получить новости за неделю
     *
     * @return array Массив новостей
     */
    private static function getWeekNews(): array
    {
        $weekAgo = new DateTime();
        $weekAgo->add('-7 days');

        $result = [];

        $newsQuery = ElementTable::getList([
            'filter' => [
                'IBLOCK_ID' => self::NEWS_IBLOCK_ID,
                'ACTIVE' => 'Y',
                '>=DATE_CREATE' => $weekAgo
            ],
            'select' => ['ID', 'NAME', 'PREVIEW_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PAGE_URL', 'DATE_CREATE'],
            'order' => ['DATE_CREATE' => 'DESC'],
            'limit' => self::MAX_NEWS_COUNT
        ]);

        while ($news = $newsQuery->fetch()) {
            // Получаем картинку
            $imageUrl = '';
            if ($news['PREVIEW_PICTURE']) {
                $file = \CFile::GetFileArray($news['PREVIEW_PICTURE']);
                if ($file) {
                    $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . $file['SRC'];
                }
            }

            // Получаем свойства (категория, теги и т.д.)
            $properties = self::getElementProperties($news['ID']);

            $result[] = [
                'ID' => $news['ID'],
                'TITLE' => $news['NAME'],
                'PREVIEW' => $news['PREVIEW_TEXT'],
                'IMAGE' => $imageUrl,
                'URL' => $news['DETAIL_PAGE_URL'],
                'DATE' => $news['DATE_CREATE'],
                'CATEGORY' => $properties['CATEGORY'] ?? 'Новости'
            ];
        }

        return $result;
    }

    /**
     * Получить свойства элемента
     *
     * @param int $elementId ID элемента
     * @return array Свойства
     */
    private static function getElementProperties(int $elementId): array
    {
        $result = [];

        $properties = \CIBlockElement::GetProperty(
            self::NEWS_IBLOCK_ID,
            $elementId,
            [],
            ['CODE' => 'CATEGORY']
        );

        while ($prop = $properties->Fetch()) {
            if ($prop['VALUE']) {
                $result[$prop['CODE']] = $prop['VALUE'];
            }
        }

        return $result;
    }

    /**
     * Получить список подписчиков
     *
     * @return array Массив подписчиков
     */
    private static function getSubscribers(): array
    {
        $result = [];

        // Вариант 1: Все пользователи с флагом подписки
        $users = \Bitrix\Main\UserTable::getList([
            'filter' => [
                'ACTIVE' => 'Y',
                'UF_NEWSLETTER_SUBSCRIBE' => 1 // Пользовательское поле подписки
            ],
            'select' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL']
        ]);

        while ($user = $users->fetch()) {
            if ($user['EMAIL']) {
                $result[] = [
                    'ID' => $user['ID'],
                    'NAME' => trim($user['NAME'] . ' ' . $user['LAST_NAME']) ?: 'Пользователь',
                    'EMAIL' => $user['EMAIL']
                ];
            }
        }

        // Вариант 2: Из отдельной таблицы подписчиков
        // $result = self::getSubscribersFromCustomTable();

        return $result;
    }

    /**
     * Альтернативный метод получения подписчиков из кастомной таблицы
     *
     * @return array
     */
    private static function getSubscribersFromCustomTable(): array
    {
        $result = [];

        // Если у вас есть отдельная таблица подписок
        $connection = \Bitrix\Main\Application::getConnection();
        $query = $connection->query("
            SELECT id, name, email
            FROM b_newsletter_subscribers
            WHERE active = 'Y' AND unsubscribed = 'N'
        ");

        while ($row = $query->fetch()) {
            $result[] = [
                'ID' => $row['id'],
                'NAME' => $row['name'],
                'EMAIL' => $row['email']
            ];
        }

        return $result;
    }

    /**
     * Отправить дайджест пользователю
     *
     * @param array $user Данные пользователя
     * @param array $news Список новостей
     * @return bool Успешность отправки
     */
    private static function sendDigestToUser(array $user, array $news): bool
    {
        // Формируем HTML со списком новостей
        $newsHtml = self::buildNewsListHtml($news);

        // Формируем период
        $weekPeriod = self::getWeekPeriod();

        // Генерируем ссылку для отписки
        $unsubscribeLink = self::generateUnsubscribeLink($user['ID']);

        // Получаем данные сайта
        $site = \CSite::GetByID(SITE_ID)->Fetch();

        // Формируем поля для письма
        $fields = [
            'USER_NAME' => $user['NAME'],
            'USER_EMAIL' => $user['EMAIL'],
            'NEWS_LIST' => $newsHtml,
            'NEWS_COUNT' => count($news),
            'WEEK_PERIOD' => $weekPeriod,
            'UNSUBSCRIBE_LINK' => $unsubscribeLink,
            'SITE_NAME' => $site['NAME'],
            'SERVER_NAME' => $site['SERVER_NAME'] ?: $_SERVER['HTTP_HOST']
        ];

        // Отправляем письмо
        $result = Event::send([
            'EVENT_NAME' => 'WEEKLY_DIGEST',
            'LID' => SITE_ID,
            'C_FIELDS' => $fields
        ]);

        if ($result->isSuccess()) {
            \AddMessage2Log(
                "Digest sent to user #{$user['ID']} ({$user['EMAIL']})",
                'weekly_digest'
            );
            return true;
        } else {
            \AddMessage2Log(
                "Failed to send digest to #{$user['ID']}: " . implode(', ', $result->getErrorMessages()),
                'weekly_digest',
                'ERROR'
            );
            return false;
        }
    }

    /**
     * Построить HTML со списком новостей
     *
     * @param array $news Список новостей
     * @return string HTML
     */
    private static function buildNewsListHtml(array $news): string
    {
        $html = '';

        foreach ($news as $item) {
            $dateFormatted = $item['DATE']->format('d.m.Y');

            $html .= '<div class="news-item">';

            // Картинка
            if ($item['IMAGE']) {
                $html .= '<img src="' . htmlspecialchars($item['IMAGE']) . '" alt="' . htmlspecialchars($item['TITLE']) . '" class="news-image">';
            }

            // Категория
            $html .= '<div class="news-category">' . htmlspecialchars($item['CATEGORY']) . '</div>';

            // Заголовок
            $html .= '<div class="news-title">' . htmlspecialchars($item['TITLE']) . '</div>';

            // Дата
            $html .= '<div class="news-date">📅 ' . $dateFormatted . '</div>';

            // Превью
            if ($item['PREVIEW']) {
                $preview = strip_tags($item['PREVIEW']);
                $preview = mb_substr($preview, 0, 200);
                if (mb_strlen($item['PREVIEW']) > 200) {
                    $preview .= '...';
                }
                $html .= '<div class="news-preview">' . htmlspecialchars($preview) . '</div>';
            }

            // Ссылка
            $url = 'http://' . $_SERVER['HTTP_HOST'] . $item['URL'];
            $html .= '<a href="' . htmlspecialchars($url) . '" class="news-link">Читать полностью →</a>';

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Получить текстовое представление периода недели
     *
     * @return string Например: "1-7 декабря"
     */
    private static function getWeekPeriod(): string
    {
        $monthNames = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];

        $weekAgo = new \DateTime();
        $weekAgo->modify('-7 days');

        $today = new \DateTime();

        $dayStart = $weekAgo->format('j');
        $dayEnd = $today->format('j');
        $monthStart = (int)$weekAgo->format('n');
        $monthEnd = (int)$today->format('n');

        if ($monthStart === $monthEnd) {
            return "{$dayStart}-{$dayEnd} {$monthNames[$monthStart]}";
        } else {
            return "{$dayStart} {$monthNames[$monthStart]} - {$dayEnd} {$monthNames[$monthEnd]}";
        }
    }

    /**
     * Генерация ссылки для отписки
     *
     * @param int $userId ID пользователя
     * @return string Ссылка для отписки
     */
    private static function generateUnsubscribeLink(int $userId): string
    {
        // Генерируем уникальный токен
        $token = md5($userId . 'unsubscribe_salt_' . time());

        // Сохраняем токен
        \COption::SetOptionString('main', 'unsubscribe_token_' . $userId, $token);

        return "/unsubscribe/?user_id={$userId}&token={$token}";
    }

    /**
     * Имя агента для повторного запуска
     *
     * @return string
     */
    private static function getAgentName(): string
    {
        return '\\' . __CLASS__ . '::sendWeeklyDigest();';
    }

    /**
     * Установка агента
     * Запуск каждое воскресенье в 10:00
     */
    public static function install()
    {
        // Проверяем, не установлен ли уже агент
        $existingAgent = \CAgent::GetList(
            [],
            ['NAME' => self::getAgentName()]
        )->Fetch();

        if ($existingAgent) {
            echo "Агент уже установлен (ID: {$existingAgent['ID']})\n";
            return;
        }

        // Вычисляем следующее воскресенье в 10:00
        $nextSunday = new \DateTime('next Sunday');
        $nextSunday->setTime(10, 0);

        // Добавляем агент - запуск раз в неделю
        $agentId = \CAgent::AddAgent(
            self::getAgentName(),
            '',
            'N',
            604800, // 7 дней в секундах
            '',
            'Y',
            $nextSunday->format('d.m.Y H:i:s'),
            30
        );

        if ($agentId) {
            echo "✓ Агент установлен (ID: {$agentId})\n";
            echo "Первый запуск: " . $nextSunday->format('d.m.Y H:i') . "\n";
            echo "Периодичность: каждое воскресенье в 10:00\n";
        } else {
            echo "✗ Ошибка установки агента\n";
        }
    }

    /**
     * Удаление агента
     */
    public static function uninstall()
    {
        \CAgent::RemoveAgent(self::getAgentName(), '');
        echo "✓ Агент удален\n";
    }
}
