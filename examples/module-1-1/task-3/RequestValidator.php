<?php
/**
 * Расширенный пример: класс для валидации и обработки форм
 */

namespace MyCompany\Forms;

use Bitrix\Main\Context;
use Bitrix\Main\Web\Json;

class RequestValidator
{
    private $request;
    private $errors = [];
    private $data = [];

    public function __construct()
    {
        $this->request = Context::getCurrent()->getRequest();
    }

    /**
     * Получение и валидация строкового поля
     */
    public function getString(string $fieldName, array $rules = []): ?string
    {
        $value = $this->request->get($fieldName);

        if ($value === null) {
            if (!empty($rules['required'])) {
                $this->errors[$fieldName] = "Поле {$fieldName} обязательно для заполнения";
            }
            return null;
        }

        // Очистка от HTML
        $value = strip_tags($value);
        $value = trim($value);

        // Минимальная длина
        if (isset($rules['min_length']) && mb_strlen($value) < $rules['min_length']) {
            $this->errors[$fieldName] = "Минимальная длина поля - {$rules['min_length']} символов";
            return null;
        }

        // Максимальная длина
        if (isset($rules['max_length']) && mb_strlen($value) > $rules['max_length']) {
            $this->errors[$fieldName] = "Максимальная длина поля - {$rules['max_length']} символов";
            return null;
        }

        // Регулярное выражение
        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
            $this->errors[$fieldName] = "Поле {$fieldName} имеет неверный формат";
            return null;
        }

        // XSS защита
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $this->data[$fieldName] = $value;
        return $value;
    }

    /**
     * Получение и валидация email
     */
    public function getEmail(string $fieldName, bool $required = true): ?string
    {
        $value = $this->request->get($fieldName);

        if (empty($value)) {
            if ($required) {
                $this->errors[$fieldName] = "Email обязателен для заполнения";
            }
            return null;
        }

        $value = trim($value);

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$fieldName] = "Неверный формат email";
            return null;
        }

        $this->data[$fieldName] = $value;
        return $value;
    }

    /**
     * Получение и валидация числа
     */
    public function getInt(string $fieldName, array $rules = []): ?int
    {
        $value = $this->request->get($fieldName);

        if ($value === null) {
            if (!empty($rules['required'])) {
                $this->errors[$fieldName] = "Поле {$fieldName} обязательно для заполнения";
            }
            return null;
        }

        if (!is_numeric($value)) {
            $this->errors[$fieldName] = "Поле {$fieldName} должно быть числом";
            return null;
        }

        $value = (int)$value;

        // Минимальное значение
        if (isset($rules['min']) && $value < $rules['min']) {
            $this->errors[$fieldName] = "Минимальное значение - {$rules['min']}";
            return null;
        }

        // Максимальное значение
        if (isset($rules['max']) && $value > $rules['max']) {
            $this->errors[$fieldName] = "Максимальное значение - {$rules['max']}";
            return null;
        }

        $this->data[$fieldName] = $value;
        return $value;
    }

    /**
     * Получение файла из $_FILES
     */
    public function getFile(string $fieldName, array $rules = []): ?array
    {
        $file = $this->request->getFile($fieldName);

        if (empty($file['name'])) {
            if (!empty($rules['required'])) {
                $this->errors[$fieldName] = "Файл обязателен для загрузки";
            }
            return null;
        }

        // Проверка размера
        if (isset($rules['max_size']) && $file['size'] > $rules['max_size']) {
            $maxSizeMb = round($rules['max_size'] / 1024 / 1024, 2);
            $this->errors[$fieldName] = "Максимальный размер файла - {$maxSizeMb} MB";
            return null;
        }

        // Проверка типа
        if (isset($rules['allowed_types'])) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (!in_array(strtolower($extension), $rules['allowed_types'])) {
                $allowed = implode(', ', $rules['allowed_types']);
                $this->errors[$fieldName] = "Разрешенные типы файлов: {$allowed}";
                return null;
            }
        }

        return $file;
    }

    /**
     * Проверка на наличие ошибок
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Получение ошибок
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Получение первой ошибки
     */
    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }

    /**
     * Получение всех валидированных данных
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Проверка AJAX запроса
     */
    public function isAjax(): bool
    {
        return $this->request->isAjaxRequest();
    }

    /**
     * Проверка POST запроса
     */
    public function isPost(): bool
    {
        return $this->request->isPost();
    }

    /**
     * Отправка JSON ответа
     */
    public function jsonResponse(array $data, int $httpCode = 200): void
    {
        $context = Context::getCurrent();
        $response = $context->getResponse();

        $response->addHeader('Content-Type', 'application/json');
        $response->setStatus($httpCode);
        $response->flush(Json::encode($data));

        die();
    }
}
