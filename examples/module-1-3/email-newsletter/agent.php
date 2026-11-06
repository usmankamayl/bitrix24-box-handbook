<?php
/**
 * Агент для отправки email-рассылки
 *
 * Задача: Отправлять еженедельную рассылку подписчикам с новостями
 *
 * Этот агент демонстрирует:
 * - Работу с пользователями и подписками
 * - Отправку почтовых событий
 * - Пакетную обработку данных
 * - Работу с параметрами агента
 * - Контроль лимитов отправки
 */

namespace MyCompany\Agents;

use Bitrix\Main\Mail\Event;
use Bitrix\Main\UserTable;
use Bitrix\Main\Diag\Debug;

class EmailNewsletterAgent
{
    /**
     * Максимальное количество писем за один запуск
     * (для предотвращения перегрузки почтового сервера)
     */
    const MAX_EMAILS_PER_RUN = 50;

    /**
     * ID почтового шаблона
     */
    const EMAIL_TEMPLATE = "WEEKLY_NEWSLETTER";

    /**
     * Основной метод агента
     *
     * @return string
     */
    public static function execute()
    {
        try {
            // Получаем список подписчиков
            $subscribers = self::getSubscribers();

            if (empty($subscribers)) {
                self::log("Нет подписчиков для рассылки");
                return self::getAgentName();
            }

            // Получаем контент для рассылки
            $newsletterContent = self::getNewsletterContent();

            if (empty($newsletterContent)) {
                self::log("Нет контента для рассылки");
                return self::getAgentName();
            }

            // Отправляем письма пакетами
            $sentCount = 0;
            $errorCount = 0;

            foreach ($subscribers as $subscriber) {
                // Ограничиваем количество писем за один запуск
                if ($sentCount >= self::MAX_EMAILS_PER_RUN) {
                    self::log("Достигнут лимит отправки ({$sentCount} писем). " .
                             "Остальные будут отправлены при следующем запуске.");
                    break;
                }

                // Отправляем письмо
                $result = self::sendEmail($subscriber, $newsletterContent);

                if ($result) {
                    $sentCount++;
                } else {
                    $errorCount++;
                    self::logError("Ошибка отправки письма: {$subscriber['EMAIL']}");
                }

                // Небольшая задержка между письмами (чтобы не перегрузить сервер)
                usleep(100000); // 0.1 секунды
            }

            // Логируем итоги
            self::log("Рассылка завершена. Отправлено: {$sentCount}, Ошибок: {$errorCount}");

            // Если отправили все письма, помечаем рассылку как выполненную
            if ($sentCount < self::MAX_EMAILS_PER_RUN) {
                self::markNewsletterAsSent();
            }

        } catch (\Exception $e) {
            self::logError($e->getMessage());
            self::notifyAdmin($e->getMessage());
        }

        return self::getAgentName();
    }

    /**
     * Получить список подписчиков
     *
     * @return array
     */
    private static function getSubscribers()
    {
        $subscribers = [];

        try {
            // Получаем активных пользователей с подпиской на рассылку
            $result = UserTable::getList([
                'filter' => [
                    'ACTIVE' => 'Y',
                    '=UF_NEWSLETTER_SUBSCRIBED' => 1, // Пользовательское поле подписки
                ],
                'select' => ['ID', 'EMAIL', 'NAME', 'LAST_NAME'],
                'order' => ['ID' => 'ASC']
            ]);

            while ($user = $result->fetch()) {
                // Проверяем, что у пользователя указан email
                if (!empty($user['EMAIL'])) {
                    $subscribers[] = $user;
                }
            }

        } catch (\Exception $e) {
            self::logError("Ошибка получения подписчиков: " . $e->getMessage());
        }

        return $subscribers;
    }

    /**
     * Получить контент для рассылки (новости за неделю)
     *
     * @return array|null
     */
    private static function getNewsletterContent()
    {
        try {
            // Получаем новости за последнюю неделю
            $weekAgo = new \DateTime('-7 days');

            $arFilter = [
                'IBLOCK_ID' => 1, // ID инфоблока новостей
                'ACTIVE' => 'Y',
                '>=DATE_CREATE' => $weekAgo->format('d.m.Y H:i:s'),
            ];

            $arSelect = ['ID', 'NAME', 'PREVIEW_TEXT', 'DETAIL_PAGE_URL'];

            $rsNews = \CIBlockElement::GetList(
                ['DATE_CREATE' => 'DESC'],
                $arFilter,
                false,
                ['nTopCount' => 5], // Берем топ-5 новостей
                $arSelect
            );

            $news = [];
            while ($arNews = $rsNews->Fetch()) {
                $news[] = [
                    'TITLE' => $arNews['NAME'],
                    'PREVIEW' => $arNews['PREVIEW_TEXT'],
                    'URL' => 'https://' . SITE_SERVER_NAME . $arNews['DETAIL_PAGE_URL'],
                ];
            }

            return !empty($news) ? [
                'NEWS' => $news,
                'DATE' => date('d.m.Y'),
            ] : null;

        } catch (\Exception $e) {
            self::logError("Ошибка получения контента: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Отправить письмо подписчику
     *
     * @param array $subscriber - данные подписчика
     * @param array $content - контент рассылки
     * @return bool
     */
    private static function sendEmail($subscriber, $content)
    {
        try {
            // Формируем данные для шаблона письма
            $arFields = [
                'EMAIL' => $subscriber['EMAIL'],
                'USER_NAME' => trim($subscriber['NAME'] . ' ' . $subscriber['LAST_NAME']),
                'NEWSLETTER_DATE' => $content['DATE'],
                'NEWS_LIST' => self::formatNewsForEmail($content['NEWS']),
            ];

            // Отправляем через почтовое событие
            Event::send([
                'EVENT_NAME' => self::EMAIL_TEMPLATE,
                'LID' => SITE_ID,
                'C_FIELDS' => $arFields,
            ]);

            return true;

        } catch (\Exception $e) {
            self::logError("Ошибка отправки письма {$subscriber['EMAIL']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Форматировать новости для вставки в письмо
     *
     * @param array $news
     * @return string
     */
    private static function formatNewsForEmail($news)
    {
        $html = '';

        foreach ($news as $item) {
            $html .= '<div style="margin-bottom: 20px;">';
            $html .= '<h3 style="margin: 0 0 10px 0;">' . htmlspecialchars($item['TITLE']) . '</h3>';
            $html .= '<p style="margin: 0 0 10px 0;">' . htmlspecialchars($item['PREVIEW']) . '</p>';
            $html .= '<a href="' . htmlspecialchars($item['URL']) . '" style="color: #0066cc;">Читать далее →</a>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Пометить рассылку как отправленную
     */
    private static function markNewsletterAsSent()
    {
        try {
            // Записываем в настройки дату последней рассылки
            \COption::SetOptionString(
                'main',
                'last_newsletter_date',
                date('Y-m-d H:i:s')
            );

        } catch (\Exception $e) {
            self::logError("Ошибка записи даты рассылки: " . $e->getMessage());
        }
    }

    /**
     * Получить имя агента
     *
     * @return string
     */
    private static function getAgentName()
    {
        return __METHOD__ . "();";
    }

    /**
     * Логирование информационных сообщений
     *
     * @param string $message
     */
    private static function log($message)
    {
        Debug::writeToFile(
            $message,
            date("Y-m-d H:i:s"),
            "/logs/newsletter_agent.log"
        );

        \CEventLog::Add([
            "SEVERITY" => "INFO",
            "AUDIT_TYPE_ID" => "NEWSLETTER_AGENT",
            "DESCRIPTION" => $message,
        ]);
    }

    /**
     * Логирование ошибок
     *
     * @param string $message
     */
    private static function logError($message)
    {
        Debug::writeToFile(
            $message,
            date("Y-m-d H:i:s") . " ERROR",
            "/logs/newsletter_agent_errors.log"
        );

        \CEventLog::Add([
            "SEVERITY" => "ERROR",
            "AUDIT_TYPE_ID" => "NEWSLETTER_AGENT",
            "DESCRIPTION" => $message,
        ]);
    }

    /**
     * Отправить уведомление администратору
     *
     * @param string $errorMessage
     */
    private static function notifyAdmin($errorMessage)
    {
        Event::send([
            'EVENT_NAME' => 'AGENT_ERROR',
            'LID' => SITE_ID,
            'C_FIELDS' => [
                'AGENT_NAME' => 'EmailNewsletterAgent',
                'ERROR_MESSAGE' => $errorMessage,
                'DATE_TIME' => date("d.m.Y H:i:s"),
            ]
        ]);
    }
}
