<?php

/**
 * Валидатор данных регистрации.
 *
 * Путь в проекте: /local/php_interface/classes/Forms/RegistrationValidator.php
 *
 * Принцип, который здесь важен: валидатор не знает, откуда пришли данные —
 * из $_POST, из JSON-тела или из CLI. Он принимает массив и возвращает
 * Bitrix\Main\Result. Поэтому его можно вызвать и в странице, и в API, и в
 * юнит-тесте без поднятого ядра.
 *
 * Чего здесь сознательно нет:
 *   - htmlspecialchars/strip_tags на входе — это экранирование, а оно делается
 *     при выводе и в контексте вывода. Экранировать при сохранении значит
 *     хранить в БД «&quot;ООО &laquo;Рога&raquo;&quot;» и потом экранировать второй раз;
 *   - SQL и работы с БД — это слой репозитория;
 *   - echo — это слой вывода.
 */

namespace MyCompany\Forms;

use Bitrix\Main\Error;
use Bitrix\Main\Result;

final class RegistrationValidator
{
    private const NAME_MAX_LENGTH = 50;
    private const PHONE_PATTERN = '/^\+?[0-9]{10,15}$/';
    private const AGE_MIN = 18;
    private const AGE_MAX = 100;

    /** @var array<string, mixed> */
    private array $input;

    /**
     * @param array<string, mixed> $input сырые данные (getPost(), json_decode, CLI)
     */
    public function __construct(array $input)
    {
        $this->input = $input;
    }

    public function validate(): Result
    {
        $result = new Result();
        $data = [];

        $name = $this->stringField('name', $result);
        if ($name !== null) {
            $name = preg_replace('/\s+/u', ' ', $name);
            if (mb_strlen($name) < 2) {
                $result->addError(new Error('Имя слишком короткое: минимум 2 символа.', 'NAME_TOO_SHORT'));
            } elseif (mb_strlen($name) > self::NAME_MAX_LENGTH) {
                $result->addError(new Error(
                    'Имя слишком длинное: максимум ' . self::NAME_MAX_LENGTH . ' символов.',
                    'NAME_TOO_LONG'
                ));
            } else {
                $data['name'] = $name;
            }
        }

        $email = $this->stringField('email', $result);
        if ($email !== null) {
            $email = mb_strtolower($email);
            // filter_var, а не регулярка: валидирует по RFC, а не «примерно похоже».
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $result->addError(new Error('Некорректный email.', 'EMAIL_INVALID'));
            } else {
                $data['email'] = $email;
            }
        }

        $ageRaw = $this->input['age'] ?? null;
        if ($ageRaw === null || $ageRaw === '') {
            $result->addError(new Error('Возраст обязателен.', 'AGE_REQUIRED'));
        } elseif (!is_string($ageRaw) && !is_int($ageRaw)) {
            // Массив в поле — это ?age[]=18. Приведение (int) дало бы 1.
            $result->addError(new Error('Некорректный возраст.', 'AGE_INVALID'));
        } else {
            $age = filter_var(
                $ageRaw,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => self::AGE_MIN, 'max_range' => self::AGE_MAX]]
            );

            // filter_var отсекает '18.5', '1e3', '18abc' и выход за диапазон.
            if ($age === false) {
                $result->addError(new Error(
                    'Возраст должен быть целым числом от ' . self::AGE_MIN . ' до ' . self::AGE_MAX . '.',
                    'AGE_INVALID'
                ));
            } else {
                $data['age'] = $age;
            }
        }

        $phone = $this->stringField('phone', $result);
        if ($phone !== null) {
            $phone = preg_replace('/[\s\-()]/', '', $phone);
            if (!preg_match(self::PHONE_PATTERN, $phone)) {
                $result->addError(new Error('Телефон в формате +7XXXXXXXXXX.', 'PHONE_INVALID'));
            } else {
                $data['phone'] = $phone;
            }
        }

        if ($result->isSuccess()) {
            $result->setData($data);
        }

        return $result;
    }

    /**
     * Общая проверка строкового поля: наличие, тип, пустота.
     * Возвращает нормализованное значение или null (ошибка уже добавлена).
     */
    private function stringField(string $name, Result $result): ?string
    {
        $value = $this->input[$name] ?? null;

        if ($value === null || $value === '') {
            $result->addError(new Error('Поле «' . $name . '» обязательно.', mb_strtoupper($name) . '_REQUIRED'));
            return null;
        }

        if (!is_string($value)) {
            // ?name[]=x или {"name": {"a":1}} — не строка, дальше идти нельзя.
            $result->addError(new Error('Поле «' . $name . '» должно быть строкой.', mb_strtoupper($name) . '_INVALID'));
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            $result->addError(new Error('Поле «' . $name . '» обязательно.', mb_strtoupper($name) . '_REQUIRED'));
            return null;
        }

        return $value;
    }
}