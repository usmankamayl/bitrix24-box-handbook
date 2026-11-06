<?php

/**
 * Мост: Result → HTTP.
 *
 * Путь в проекте: /local/php_interface/classes/Api/ResultResponder.php
 *
 * Единственное место, где коды ошибок превращаются в HTTP-статусы. Сервис про
 * HTTP не знает, транспорт про коды бизнеса не знает — знает только эта таблица.
 *
 * Почему таблица, а не if/else по тексту сообщения: текст меняют, переводят и
 * сокращают; код — часть контракта API и документации.
 */

namespace MyCompany\Api;

use Bitrix\Main\Result;
use Bitrix\Main\Web\Json;

final class ResultResponder
{
    /** Код ошибки → HTTP-статус. Неизвестный код считается ошибкой валидации. */
    private const STATUS_BY_CODE = [
        'REQUEST_ID_INVALID'       => 400,
        'REQUEST_NOT_FOUND'        => 404,
        'ACCESS_DENIED'            => 404,   // чужой объект не подтверждаем
        'ALREADY_CLOSED'           => 409,
        'STATUS_TRANSITION_DENIED' => 409,
        'COMMENT_REQUIRED'         => 422,
        'COMMENT_TOO_LONG'         => 422,
        'STORE_FAILED'             => 500,
    ];

    /** Если в одном результате несколько ошибок, побеждает самый «тяжёлый» статус. */
    private const WEIGHT = [422 => 1, 400 => 2, 404 => 3, 409 => 4, 500 => 5];

    public static function sendResult(Result $result): void
    {
        if ($result->isSuccess()) {
            self::json(200, [
                'status' => 'ok',
                'data'   => $result->getData(),
            ]);
        }

        $status = 422;
        $errors = [];

        foreach ($result->getErrors() as $error) {
            $code = (string)$error->getCode();
            $mapped = self::STATUS_BY_CODE[$code] ?? 422;

            if (self::WEIGHT[$mapped] > self::WEIGHT[$status]) {
                $status = $mapped;
            }

            $item = ['code' => $code, 'message' => $error->getMessage()];

            // customData — то, из чего клиент строит UI: какое поле, сколько
            // осталось, какой статус был. Текста для этого недостаточно.
            if ($error->getCustomData() !== null) {
                $item['details'] = $error->getCustomData();
            }

            $errors[] = $item;
        }

        self::json($status, ['status' => 'error', 'errors' => $errors]);
    }

    /**
     * Непредвиденное: клиенту — код корреляции, в лог — класс, сообщение, trace.
     * Ни $e->getMessage(), ни trace в тело ответа не попадают: там имена
     * таблиц, абсолютные пути и структура кода.
     */
    public static function sendUnexpected(\Throwable $exception): void
    {
        $errorId = bin2hex(random_bytes(8));

        AddMessage2Log(
            '[' . $errorId . '] ' . get_class($exception) . ': ' . $exception->getMessage()
            . "\n" . $exception->getTraceAsString(),
            'mycompany.api'
        );

        self::json(500, [
            'status' => 'error',
            'errors' => [[
                'code'    => 'INTERNAL',
                'message' => 'Внутренняя ошибка. Код для обращения в поддержку: ' . $errorId,
            ]],
        ]);
    }

    public static function sendError(int $status, string $code, string $message): void
    {
        self::json($status, [
            'status' => 'error',
            'errors' => [['code' => $code, 'message' => $message]],
        ]);
    }

    private static function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        // Персональные данные и статусы не должны попадать в кеш и в историю.
        header('Cache-Control: no-store');

        echo Json::encode($payload, JSON_UNESCAPED_UNICODE);

        // Без exit после ответа выполнится остаток скрипта и, возможно, второй
        // JSON (см. 1.7, блок B3).
        exit;
    }
}
