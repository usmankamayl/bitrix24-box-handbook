<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\Security\Sign\BadSignatureException;
use Bitrix\Main\Security\Sign\Signer;
use Bitrix\Main\Web\Json;

Loc::loadMessages(__FILE__);

/**
 * study:feedback — форма обратной связи без кеша.
 *
 * Порядок проверок на POST (именно в этом порядке, а не «как получилось»):
 *   1. CSRF: check_bitrix_sessid()
 *   2. honeypot: тихо отвечаем «спасибо», письмо не шлём
 *   3. подпись времени заполнения: бот не может подделать, человек не ждёт
 *   4. ограничение частоты по сессии
 *   5. типы, длины, формат полей
 *   6. капча (если включена) — последней, чтобы не тратить её на заведомо
 *      неверные данные
 *   7. отправка почтового события и проверка результата
 *
 * Компонент не кешируется вообще: результат зависит от конкретного запроса.
 */
class StudyFeedbackComponent extends CBitrixComponent
{
    private const SALT_TIME = 'study.feedback.time';

    private const MAX_LEN = [
        'name' => 100,
        'email' => 254,
        'phone' => 32,
        'message' => 4000,
    ];

    /** @var array<string,string> */
    private array $formData = [];

    /** @var array<string,string> */
    private array $errors = [];

    public function onPrepareComponentParams($arParams): array
    {
        $arParams['EMAIL_TO'] = trim((string)($arParams['EMAIL_TO'] ?? ''));

        $eventName = strtoupper(trim((string)($arParams['EVENT_NAME'] ?? 'STUDY_FEEDBACK')));
        $arParams['EVENT_NAME'] = preg_match('/^[A-Z0-9_]{1,255}$/', $eventName)
            ? $eventName
            : 'STUDY_FEEDBACK';

        $arParams['USE_CAPTCHA'] = ($arParams['USE_CAPTCHA'] ?? 'Y') === 'Y';
        $arParams['MIN_FILL_TIME'] = max(0, (int)($arParams['MIN_FILL_TIME'] ?? 3));
        $arParams['MAX_FILL_TIME'] = max(60, (int)($arParams['MAX_FILL_TIME'] ?? 3600));
        $arParams['THROTTLE_SECONDS'] = max(0, (int)($arParams['THROTTLE_SECONDS'] ?? 30));

        return $arParams;
    }

    public function executeComponent(): void
    {
        if ($this->arParams['EMAIL_TO'] === '' || !filter_var($this->arParams['EMAIL_TO'], FILTER_VALIDATE_EMAIL)) {
            ShowError(Loc::getMessage('STUDY_FEEDBACK_ERR_EMAIL_TO'));
            return;
        }

        $this->arResult['SUCCESS'] = false;
        $this->arResult['ERRORS'] = [];
        $this->arResult['FORM_DATA'] = [];
        $this->arResult['TIME_TOKEN'] = $this->makeTimeToken();

        $request = Context::getCurrent()->getRequest();

        if ($request->isPost() && $request->getPost('study_feedback') === 'Y') {
            $this->processPost();

            if ($this->isAjax()) {
                $this->sendJson();
                return;
            }
        }

        if ($this->arParams['USE_CAPTCHA']) {
            $this->arResult['CAPTCHA_CODE'] = (string)$this->getApplication()->CaptchaGetCode();
        }

        $this->arResult['FORM_DATA'] = $this->formData;
        $this->arResult['ERRORS'] = $this->errors;

        $this->includeComponentTemplate();
    }

    private function processPost(): void
    {
        $request = Context::getCurrent()->getRequest();

        // 1. CSRF. Без этой проверки любая внешняя страница может отправить
        // форму от имени залогиненного пользователя.
        if (!check_bitrix_sessid()) {
            $this->errors['SESSID'] = Loc::getMessage('STUDY_FEEDBACK_ERR_SESSID');
            return;
        }

        $this->formData = $this->readFormData();

        // 2. Honeypot: поле скрыто в разметке. Заполнено — бот.
        // Отвечаем нейтрально и НЕ подсказываем, какое поле сработало.
        if ($this->formData['_website'] !== '') {
            $this->arResult['SUCCESS'] = true;
            $this->formData = [];
            return;
        }

        // 3. Подпись времени: бот приходит быстрее MIN_FILL_TIME и не умеет
        // подписать метку, потому что ключ живёт в сессии/на сервере.
        if (!$this->checkTimeToken((string)$this->formData['_ts'])) {
            $this->errors['FORM'] = Loc::getMessage('STUDY_FEEDBACK_ERR_FORM');
            return;
        }

        // 4. Ограничение частоты: одна отправка в THROTTLE_SECONDS на сессию.
        if (!$this->checkThrottle()) {
            $this->errors['FORM'] = Loc::getMessage('STUDY_FEEDBACK_ERR_THROTTLE');
            return;
        }

        // 5. Поля.
        $this->validateFields();
        if ($this->errors !== []) {
            return;
        }

        // 6. Капча — после валидации полей.
        if ($this->arParams['USE_CAPTCHA'] && !$this->checkCaptcha()) {
            $this->errors['CAPTCHA'] = Loc::getMessage('STUDY_FEEDBACK_ERR_CAPTCHA');
            return;
        }

        // 7. Почта.
        if (!$this->sendMail()) {
            $this->errors['FORM'] = Loc::getMessage('STUDY_FEEDBACK_ERR_SEND');
            return;
        }

        $this->touchThrottle();
        $this->arResult['SUCCESS'] = true;
        $this->formData = [];
    }

    /**
     * Чтение POST: сначала типы, потом нормализация. Любое значение, которое
     * не строка, отбрасывается — иначе (int) на массиве даст 1 и warning.
     *
     * @return array<string,string>
     */
    private function readFormData(): array
    {
        $request = Context::getCurrent()->getRequest();

        $keys = ['NAME', 'EMAIL', 'PHONE', 'MESSAGE', '_website', '_ts', 'captcha_word', 'captcha_sid'];
        $data = array_fill_keys(array_map('strtolower', $keys), '');

        foreach ($keys as $key) {
            $value = $request->getPost($key);
            if (!is_string($value)) {
                continue;
            }

            $name = strtolower($key);

            if ($key === '_ts') {
                $data[$name] = $value;
                continue;
            }

            // Управляющие символы и переводы строк в однострочных полях —
            // заготовка для инъекции в заголовки письма и в лог.
            $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
            $data[$name] = trim($value);
        }

        return $data;
    }

    private function validateFields(): void
    {
        foreach (['name' => 1, 'email' => 1, 'message' => 1, 'phone' => 0] as $field => $required) {
            $value = $this->formData[$field] ?? '';

            if ($value === '') {
                if ($required) {
                    $this->errors[$field] = Loc::getMessage(
                        'STUDY_FEEDBACK_ERR_REQUIRED',
                        ['#FIELD#' => Loc::getMessage('STUDY_FEEDBACK_FIELD_' . $field)]
                    );
                }
                continue;
            }

            if (mb_strlen($value) > self::MAX_LEN[$field]) {
                $this->errors[$field] = Loc::getMessage(
                    'STUDY_FEEDBACK_ERR_TOO_LONG',
                    ['#FIELD#' => Loc::getMessage('STUDY_FEEDBACK_FIELD_' . $field)]
                );
            }
        }

        if (($this->formData['email'] ?? '') !== '' && !filter_var($this->formData['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = Loc::getMessage('STUDY_FEEDBACK_ERR_EMAIL');
        }
    }

    private function checkCaptcha(): bool
    {
        $word = $this->formData['captcha_word'] ?? '';
        $sid = $this->formData['captcha_sid'] ?? '';

        if ($word === '' || $sid === '') {
            return false;
        }

        // Штатный механизм main: генерация и проверка кода.
        return (bool)$this->getApplication()->CaptchaCheckCode($word, $sid);
    }

    private function sendMail(): bool
    {
        $fields = [
            'NAME' => $this->formData['name'],
            'EMAIL' => $this->formData['email'],
            'PHONE' => $this->formData['phone'],
            'MESSAGE' => $this->formData['message'],
            'EMAIL_TO' => $this->arParams['EMAIL_TO'],
        ];

        $result = Event::send([
            'EVENT_NAME' => $this->arParams['EVENT_NAME'],
            'LID' => SITE_ID,
            'C_FIELDS' => $fields,
        ]);

        if ($result->isSuccess()) {
            return true;
        }

        // Детали — в лог, пользователю — нейтральный текст.
        AddMessage2Log(
            'feedback: ' . implode('; ', $result->getErrorMessages()),
            'study.feedback'
        );

        return false;
    }

    private function makeTimeToken(): string
    {
        $timestamp = (string)time();

        try {
            $signature = (new Signer())->sign($timestamp, self::SALT_TIME);
        } catch (\Throwable $exception) {
            AddMessage2Log($exception->getMessage(), 'study.feedback');
            return '';
        }

        return $timestamp . '.' . $signature;
    }

    private function checkTimeToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }

        try {
            $unsigned = (new Signer())->unsign($parts[1], self::SALT_TIME);
        } catch (BadSignatureException $exception) {
            return false;
        }

        if ($unsigned !== $parts[0]) {
            return false;
        }

        $elapsed = time() - (int)$parts[0];

        // Слишком быстро — бот; слишком долго — токен устарел.
        return $elapsed >= $this->arParams['MIN_FILL_TIME']
            && $elapsed <= $this->arParams['MAX_FILL_TIME'];
    }

    private function checkThrottle(): bool
    {
        if ($this->arParams['THROTTLE_SECONDS'] === 0) {
            return true;
        }

        $last = (int)($_SESSION['STUDY_FEEDBACK_LAST'] ?? 0);

        return (time() - $last) >= $this->arParams['THROTTLE_SECONDS'];
    }

    private function touchThrottle(): void
    {
        $_SESSION['STUDY_FEEDBACK_LAST'] = time();
    }

    private function isAjax(): bool
    {
        $request = Context::getCurrent()->getRequest();

        return $request->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    private function sendJson(): void
    {
        global $APPLICATION;

        $APPLICATION->RestartBuffer();
        header('Content-Type: application/json; charset=UTF-8');

        $response = [
            'success' => (bool)$this->arResult['SUCCESS'],
        ];

        if ($this->arResult['SUCCESS']) {
            $response['message'] = (string)Loc::getMessage('STUDY_FEEDBACK_SUCCESS');
        } else {
            $response['errors'] = array_values($this->errors);

            // Новый код капчи: старый после неудачной попытки недействителен.
            if ($this->arParams['USE_CAPTCHA']) {
                $response['captcha'] = [
                    'sid' => (string)$this->getApplication()->CaptchaGetCode(),
                ];
            }
        }

        echo Json::encode($response);
        die();
    }

    private function getApplication(): \CMain
    {
        global $APPLICATION;

        return $APPLICATION;
    }
}
