<?php

/**
 * Доменный сервис закрытия заявки.
 *
 * Путь в проекте: /local/php_interface/classes/Requests/OrderStatusService.php
 *
 * Контракт: принимает нормализованные примитивы (int, string), возвращает
 * Bitrix\Main\Result. Не знает ни про HTTP, ни про $_POST, ни про шаблон —
 * поэтому вызывается и из формы, и из REST-эндпоинта, и из CLI, и из агента.
 *
 * Какой отказ — какой код:
 *
 *   REQUEST_ID_INVALID        параметр неверного типа/значения         (400)
 *   COMMENT_REQUIRED          пустой комментарий                      (422)
 *   COMMENT_TOO_LONG          длиннее MAX_COMMENT_LENGTH               (422)
 *   REQUEST_NOT_FOUND         нет заявки ИЛИ она чужая                 (404)
 *   STATUS_TRANSITION_DENIED  из текущего статуса закрыть нельзя       (409)
 *   ALREADY_CLOSED            уже закрыта — отдельный код, не ошибка БД (409)
 *   STORE_FAILED              запись не подтвердилась чтением          (500)
 *
 * Коды — часть контракта, тексты — нет: текст можно менять, переводить через
 * Loc::getMessage(), сокращать для мобильного клиента. HTTP-статусы в скобках
 * ставит эндпоинт (практика 4), а не сервис: сервис про HTTP не знает.
 */

namespace MyCompany\Requests;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;

final class OrderStatusService
{
    private const MAX_COMMENT_LENGTH = 500;

    /** Разрешённые переходы: из статуса — в какие можно. */
    private const ALLOWED_TRANSITIONS = [
        OrderTable::STATUS_NEW     => [OrderTable::STATUS_CLOSED],
        OrderTable::STATUS_IN_WORK => [OrderTable::STATUS_CLOSED],
        OrderTable::STATUS_CLOSED  => [],
    ];

    /**
     * Закрывает заявку. Повторный вызов для уже закрытой заявки возвращает
     * ALREADY_CLOSED, а не «успех»: вызывающий должен отличать «я только что
     * закрыл» от «кто-то закрыл до меня» — иначе двойная отправка письма
     * клиенту выглядит как штатная работа.
     */
    public function closeRequest(int $requestId, int $userId, string $comment): Result
    {
        $result = new Result();

        if ($requestId <= 0) {
            return $result->addError(new Error('Неверный идентификатор заявки.', 'REQUEST_ID_INVALID'));
        }

        $comment = trim($comment);
        if ($comment === '') {
            return $result->addError(new Error('Комментарий обязателен.', 'COMMENT_REQUIRED'));
        }

        if (mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            return $result->addError(new Error(
                'Комментарий длиннее ' . self::MAX_COMMENT_LENGTH . ' символов.',
                'COMMENT_TOO_LONG',
                ['max' => self::MAX_COMMENT_LENGTH, 'actual' => mb_strlen($comment)]
            ));
        }

        // getList бросает ObjectPropertyException/ArgumentException на неверный
        // запрос — это программная ошибка, её не превращаем в Result.
        $row = OrderTable::getList([
            'select' => ['ID', 'STATUS', 'AUTHOR_ID'],
            'filter' => ['=ID' => $requestId],
            'limit'  => 1,
        ])->fetch();

        if ($row === false) {
            return $result->addError(new Error('Заявка не найдена.', 'REQUEST_NOT_FOUND'));
        }

        // Чужую заявку не отличаем от несуществующей снаружи: 404 вместо 403,
        // иначе перебор ID подтверждает существование чужих объектов.
        // Разница остаётся только в логе — это нужно для разбора инцидентов.
        if ((int)$row['AUTHOR_ID'] !== $userId) {
            AddMessage2Log(
                'Попытка закрыть чужую заявку: request=' . $requestId . ' user=' . $userId,
                'mycompany.requests'
            );

            return $result->addError(new Error('Заявка не найдена.', 'REQUEST_NOT_FOUND'));
        }

        $status = (string)$row['STATUS'];

        if ($status === OrderTable::STATUS_CLOSED) {
            return $result->addError(new Error('Заявка уже закрыта.', 'ALREADY_CLOSED'));
        }

        $allowed = self::ALLOWED_TRANSITIONS[$status] ?? [];
        if (!in_array(OrderTable::STATUS_CLOSED, $allowed, true)) {
            return $result->addError(new Error(
                'Из статуса «' . $status . '» заявку закрыть нельзя.',
                'STATUS_TRANSITION_DENIED',
                ['from' => $status]
            ));
        }

        $update = OrderTable::update($requestId, [
            'STATUS'        => OrderTable::STATUS_CLOSED,
            'CLOSED_AT'     => new DateTime(),
            'CLOSE_COMMENT' => $comment,
            'UPDATED_AT'    => new DateTime(),
        ]);

        if (!$update->isSuccess()) {
            // Ошибки ORM переносим как есть: там уже есть и код, и поле.
            return $result->addErrors($update->getErrors());
        }

        // Read-back: update() по существующей строке обычно успешен, но
        // «успешен» не значит «записалось то, что мы думаем». Проверяем факт.
        $check = OrderTable::getById($requestId)->fetch();
        if ($check === false || (string)$check['STATUS'] !== OrderTable::STATUS_CLOSED) {
            return $result->addError(new Error('Не удалось подтвердить закрытие заявки.', 'STORE_FAILED'));
        }

        $result->setData([
            'id'     => $requestId,
            'status' => OrderTable::STATUS_CLOSED,
            'closed_at' => (string)$check['CLOSED_AT'],
        ]);

        return $result;
    }
}
