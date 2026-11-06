<?php

/**
 * Исключение с доменным результатом внутри.
 *
 * Путь в проекте: /local/php_interface/classes/Stock/TransferFailed.php
 *
 * Зачем: внутри транзакции нельзя «вернуть Result» — транзакция останется
 * открытой, а вызывающий не узнает, что commit не состоялся. Поэтому доменный
 * отказ внутри транзакции превращается в исключение, которое несёт готовый
 * Result с кодами; на границе сервиса оно снова становится Result.
 *
 * \Exception('текст') для этого не годится: тексты не ветвятся, коды потеряны.
 */

namespace MyCompany\Stock;

use Bitrix\Main\Result;

final class TransferFailed extends \RuntimeException
{
    private Result $result;

    public function __construct(Result $result, ?\Throwable $previous = null)
    {
        $this->result = $result;

        parent::__construct(implode('; ', $result->getErrorMessages()), 0, $previous);
    }

    public function getResult(): Result
    {
        return $this->result;
    }
}
