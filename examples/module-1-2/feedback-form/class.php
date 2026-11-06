<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Mail\Event;
use Bitrix\Main\Context;
use Bitrix\Main\Application;

/**
 * Класс компонента "Форма обратной связи"
 *
 * Функционал:
 * - Валидация полей формы
 * - Защита от спама (honeypot, время заполнения, капча)
 * - Отправка email через почтовые события Bitrix
 * - Ajax-отправка формы
 */
class FeedbackFormComponent extends CBitrixComponent
{
    /** @var array Ошибки валидации */
    protected $errors = [];

    /** @var array Данные формы */
    protected $formData = [];

    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($arParams): array
    {
        // Email получателя
        $arParams["EMAIL_TO"] = trim($arParams["EMAIL_TO"] ?? "");

        // Тема письма
        $arParams["EMAIL_SUBJECT"] = trim($arParams["EMAIL_SUBJECT"] ?? "Сообщение с сайта");

        // Использовать почтовые события
        $arParams["USE_BITRIX_MAIL_EVENT"] = ($arParams["USE_BITRIX_MAIL_EVENT"] ?? "Y") === "Y";
        $arParams["EVENT_NAME"] = trim($arParams["EVENT_NAME"] ?? "FEEDBACK_FORM");

        // Обязательные поля
        if (!is_array($arParams["REQUIRED_FIELDS"])) {
            $arParams["REQUIRED_FIELDS"] = ["NAME", "EMAIL", "MESSAGE"];
        }

        // Защита от спама
        $arParams["USE_CAPTCHA"] = ($arParams["USE_CAPTCHA"] ?? "Y") === "Y";
        $arParams["USE_HONEYPOT"] = ($arParams["USE_HONEYPOT"] ?? "Y") === "Y";
        $arParams["MIN_FILL_TIME"] = intval($arParams["MIN_FILL_TIME"] ?? 3);

        // Сообщения
        $arParams["SUCCESS_MESSAGE"] = trim($arParams["SUCCESS_MESSAGE"] ?? "Ваше сообщение отправлено!");
        $arParams["ERROR_MESSAGE"] = trim($arParams["ERROR_MESSAGE"] ?? "Ошибка отправки. Попробуйте позже.");

        // Ajax
        $arParams["USE_AJAX"] = ($arParams["USE_AJAX"] ?? "Y") === "Y";

        return $arParams;
    }

    /**
     * Проверка отправки формы
     */
    protected function isFormSubmitted(): bool
    {
        $request = Context::getCurrent()->getRequest();
        return $request->isPost() && $request->getPost("feedback_form") === "Y";
    }

    /**
     * Получение данных формы
     */
    protected function getFormData(): array
    {
        $request = Context::getCurrent()->getRequest();

        return [
            "NAME" => trim($request->getPost("name") ?? ""),
            "EMAIL" => trim($request->getPost("email") ?? ""),
            "PHONE" => trim($request->getPost("phone") ?? ""),
            "SUBJECT" => trim($request->getPost("subject") ?? ""),
            "MESSAGE" => trim($request->getPost("message") ?? ""),
            "HONEYPOT" => $request->getPost("website") ?? "", // Скрытое поле для ботов
            "FORM_TIME" => $request->getPost("form_time") ?? "",
            "CAPTCHA_WORD" => $request->getPost("captcha_word") ?? "",
            "CAPTCHA_SID" => $request->getPost("captcha_sid") ?? "",
        ];
    }

    /**
     * Валидация формы
     */
    protected function validateForm(): bool
    {
        $this->errors = [];

        // 1. Проверка обязательных полей
        $this->validateRequiredFields();

        // 2. Валидация email
        if (!empty($this->formData["EMAIL"]) && !check_email($this->formData["EMAIL"])) {
            $this->errors["EMAIL"] = "Некорректный email адрес";
        }

        // 3. Проверка honeypot (скрытое поле)
        if ($this->arParams["USE_HONEYPOT"] && !empty($this->formData["HONEYPOT"])) {
            $this->errors["SPAM"] = "Обнаружена активность бота";
        }

        // 4. Проверка времени заполнения формы
        if ($this->arParams["MIN_FILL_TIME"] > 0) {
            $this->validateFillTime();
        }

        // 5. Проверка капчи
        if ($this->arParams["USE_CAPTCHA"]) {
            $this->validateCaptcha();
        }

        return empty($this->errors);
    }

    /**
     * Проверка обязательных полей
     */
    protected function validateRequiredFields(): void
    {
        $requiredFields = $this->arParams["REQUIRED_FIELDS"];

        foreach ($requiredFields as $field) {
            if (empty($this->formData[$field])) {
                $fieldNames = [
                    "NAME" => "Имя",
                    "EMAIL" => "Email",
                    "PHONE" => "Телефон",
                    "SUBJECT" => "Тема",
                    "MESSAGE" => "Сообщение",
                ];

                $this->errors[$field] = "Поле «" . ($fieldNames[$field] ?? $field) . "» обязательно для заполнения";
            }
        }
    }

    /**
     * Проверка времени заполнения формы (защита от ботов)
     */
    protected function validateFillTime(): void
    {
        if (empty($this->formData["FORM_TIME"])) {
            $this->errors["SPAM"] = "Ошибка отправки формы";
            return;
        }

        $formTime = intval($this->formData["FORM_TIME"]);
        $currentTime = time();
        $fillTime = $currentTime - $formTime;

        // Если форма заполнена слишком быстро - вероятно бот
        if ($fillTime < $this->arParams["MIN_FILL_TIME"]) {
            $this->errors["SPAM"] = "Форма заполнена слишком быстро";
        }

        // Если форма открыта слишком долго - токен истек
        if ($fillTime > 3600) { // 1 час
            $this->errors["SPAM"] = "Время сеанса истекло. Обновите страницу.";
        }
    }

    /**
     * Проверка капчи
     */
    protected function validateCaptcha(): void
    {
        if (empty($this->formData["CAPTCHA_WORD"]) || empty($this->formData["CAPTCHA_SID"])) {
            $this->errors["CAPTCHA"] = "Введите текст с картинки";
            return;
        }

        $captchaWord = $this->formData["CAPTCHA_WORD"];
        $captchaSid = $this->formData["CAPTCHA_SID"];

        $application = Application::getInstance();
        $captcha = $application->getCapture($captchaSid);

        if (!$captcha->checkCode($captchaWord, $captchaSid)) {
            $this->errors["CAPTCHA"] = "Неверный код с картинки";
        }
    }

    /**
     * Отправка email
     */
    protected function sendEmail(): bool
    {
        // Если используем почтовые события Bitrix
        if ($this->arParams["USE_BITRIX_MAIL_EVENT"]) {
            return $this->sendViaMailEvent();
        }

        // Иначе используем стандартный mail()
        return $this->sendViaPhpMail();
    }

    /**
     * Отправка через почтовые события Bitrix
     */
    protected function sendViaMailEvent(): bool
    {
        $fields = [
            "NAME" => $this->formData["NAME"],
            "EMAIL" => $this->formData["EMAIL"],
            "PHONE" => $this->formData["PHONE"],
            "SUBJECT" => $this->formData["SUBJECT"],
            "MESSAGE" => $this->formData["MESSAGE"],
            "EMAIL_TO" => $this->arParams["EMAIL_TO"],
        ];

        try {
            Event::send([
                "EVENT_NAME" => $this->arParams["EVENT_NAME"],
                "LID" => SITE_ID,
                "C_FIELDS" => $fields,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->errors["SEND"] = "Ошибка отправки: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Отправка через PHP mail()
     */
    protected function sendViaPhpMail(): bool
    {
        $to = $this->arParams["EMAIL_TO"];
        $subject = $this->arParams["EMAIL_SUBJECT"];

        $message = "Имя: " . $this->formData["NAME"] . "\n";
        $message .= "Email: " . $this->formData["EMAIL"] . "\n";

        if (!empty($this->formData["PHONE"])) {
            $message .= "Телефон: " . $this->formData["PHONE"] . "\n";
        }

        if (!empty($this->formData["SUBJECT"])) {
            $message .= "Тема: " . $this->formData["SUBJECT"] . "\n";
        }

        $message .= "\nСообщение:\n" . $this->formData["MESSAGE"];

        $headers = "From: noreply@" . $_SERVER["HTTP_HOST"] . "\r\n";
        $headers .= "Reply-To: " . $this->formData["EMAIL"] . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        if (mail($to, $subject, $message, $headers)) {
            return true;
        }

        $this->errors["SEND"] = "Ошибка отправки email";
        return false;
    }

    /**
     * Обработка формы
     */
    protected function processForm(): void
    {
        // Получаем данные формы
        $this->formData = $this->getFormData();

        // Валидируем
        if (!$this->validateForm()) {
            $this->arResult["SUCCESS"] = false;
            $this->arResult["ERRORS"] = $this->errors;
            return;
        }

        // Отправляем email
        if ($this->sendEmail()) {
            $this->arResult["SUCCESS"] = true;
            $this->arResult["MESSAGE"] = $this->arParams["SUCCESS_MESSAGE"];

            // Очищаем данные формы после успешной отправки
            $this->formData = [];
        } else {
            $this->arResult["SUCCESS"] = false;
            $this->arResult["ERRORS"] = $this->errors;
        }
    }

    /**
     * Генерация новой капчи
     */
    protected function generateCaptcha(): array
    {
        $captcha = new \Bitrix\Main\Security\Sign\Captcha();
        $captchaSid = $captcha->getCode();

        return [
            "CODE" => $captchaSid,
            "URL" => "/bitrix/tools/captcha.php?captcha_sid=" . $captchaSid,
        ];
    }

    /**
     * Ajax ответ
     */
    protected function sendAjaxResponse(): void
    {
        global $APPLICATION;
        $APPLICATION->RestartBuffer();

        header('Content-Type: application/json');

        $response = [
            "success" => $this->arResult["SUCCESS"],
        ];

        if ($this->arResult["SUCCESS"]) {
            $response["message"] = $this->arResult["MESSAGE"];
        } else {
            $response["errors"] = $this->arResult["ERRORS"];

            // Если была ошибка капчи - отправляем новую
            if ($this->arParams["USE_CAPTCHA"] && isset($this->arResult["ERRORS"]["CAPTCHA"])) {
                $response["new_captcha"] = $this->generateCaptcha();
            }
        }

        echo json_encode($response);
        die();
    }

    /**
     * Основной метод выполнения компонента
     */
    public function executeComponent()
    {
        // Проверяем email получателя
        if (empty($this->arParams["EMAIL_TO"])) {
            ShowError("Не указан email получателя");
            return;
        }

        // Инициализация результата
        $this->arResult["SUCCESS"] = null;
        $this->arResult["ERRORS"] = [];
        $this->arResult["FORM_DATA"] = [];

        // Если форма отправлена
        if ($this->isFormSubmitted()) {
            $this->processForm();

            // Ajax ответ
            if ($this->arParams["USE_AJAX"]) {
                $this->sendAjaxResponse();
            }
        }

        // Генерируем капчу
        if ($this->arParams["USE_CAPTCHA"]) {
            $this->arResult["CAPTCHA"] = $this->generateCaptcha();
        }

        // Сохраняем данные формы для повторного заполнения при ошибке
        $this->arResult["FORM_DATA"] = $this->formData;

        // Время загрузки формы (для проверки времени заполнения)
        $this->arResult["FORM_TIME"] = time();

        // Подключаем шаблон
        $this->includeComponentTemplate();
    }
}
