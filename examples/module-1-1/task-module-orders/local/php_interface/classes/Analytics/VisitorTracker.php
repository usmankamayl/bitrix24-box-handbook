<?php

/**
 * Трекер первого визита.
 *
 * Путь в проекте: /local/php_interface/classes/Analytics/VisitorTracker.php
 * Подписка на событие: /local/php_interface/init.php
 *
 * Задача: если у клиента нет cookie-метки, поставить её и записать визит.
 * Лог — JSONL (одна строка = один объект), каталог /local/logs/visitors.
 */

namespace MyCompany\Analytics;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Web\Cookie;

final class VisitorTracker
{
    private const COOKIE_NAME = 'mycompany_visited';
    private const COOKIE_LIFETIME = 2592000; // 30 дней
    private const LOG_SUBDIR = '/local/logs/visitors';
    private const MAX_FIELD_LENGTH = 512;

    public static function track(): void
    {
        $context = Context::getCurrent();
        $request = $context->getRequest();

        // getCookie возвращает null, если cookie нет. Значение 'Y' — уже визит.
        if ($request->getCookie(self::COOKIE_NAME) !== null) {
            return;
        }

        self::markAsVisited($context);
        self::logVisit($request);
        self::writeJournalEntry();
    }

    /**
     * @param Context $context
     */
    private static function markAsVisited($context): void
    {
        $request = $context->getRequest();

        $cookie = new Cookie(self::COOKIE_NAME, 'Y', time() + self::COOKIE_LIFETIME);
        $cookie->setPath('/');
        $cookie->setHttpOnly(true);
        $cookie->setSecure($request->isHttps());

        // Домен намеренно не задаём: браузер привяжет cookie к текущему хосту.
        // setDomain($_SERVER['HTTP_HOST']) — дыра: HTTP_HOST приходит от клиента,
        // запрос с подменённым Host ставит cookie на чужой домен.

        $context->getResponse()->addCookie($cookie);
    }

    /**
     * @param \Bitrix\Main\HttpRequest $request
     */
    private static function logVisit($request): void
    {
        $data = [
            // Unix time — единственное представление, не зависящее от таймзоны.
            'ts' => time(),
            'ip' => (string)$request->getRemoteAddress(),
            'ua' => self::cut((string)$request->getUserAgent()),
            'referer' => self::cut((string)$request->getServer('HTTP_REFERER')),
            'page' => self::cut((string)$request->getRequestUri()),
            'utm' => [
                'source' => self::cut((string)$request->getQuery('utm_source')),
                'medium' => self::cut((string)$request->getQuery('utm_medium')),
                'campaign' => self::cut((string)$request->getQuery('utm_campaign')),
            ],
        ];

        // JSON_INVALID_UTF8_SUBSTITUTE: заголовки приходят в произвольной
        // кодировке, без флага json_encode вернёт false и строка потеряется.
        $line = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($line === false) {
            return;
        }

        $dir = Application::getDocumentRoot() . self::LOG_SUBDIR;

        // Двойная проверка is_dir — гонка двух параллельных запросов:
        // mkdir может вернуть false, потому что каталог уже создал сосед.
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $file = $dir . '/' . gmdate('Y-m-d') . '.log';

        // JSONL: без запятой между объектами, иначе файл не читается ничем.
        // FILE_APPEND | LOCK_EX — иначе параллельная запись перемешает строки.
        file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function writeJournalEntry(): void
    {
        global $USER;

        // OnPageStart срабатывает в начале пролога. Гарантии, что $USER уже
        // авторизован, из кода не следует: если признак важен — проверь
        // порядок в prolog_before.php своей версии или переноси запись на OnEpilog.
        $isAuthorized = is_object($USER) && $USER->IsAuthorized();

        \CEventLog::Add([
            'SEVERITY' => 'INFO',
            'AUDIT_TYPE_ID' => $isAuthorized ? 'MYCOMPANY_FIRST_VISIT_AUTH' : 'MYCOMPANY_FIRST_VISIT_ANON',
            'MODULE_ID' => 'main',
            'DESCRIPTION' => 'Первый визит',
            'ITEM_ID' => $isAuthorized ? (int)$USER->GetID() : '',
        ]);
    }

    private static function cut(string $value): string
    {
        return mb_substr($value, 0, self::MAX_FIELD_LENGTH);
    }
}
