<?php
// examples/module-5-3/payment-handler/pay_system_registration.php
// Регистрация кастомного PaySystem Handler.
// Размещается в /local/php_interface/include/sale_payment/acme/,
// путь прописывается в настройках ПС в админке.

use Bitrix\Main\Loader;
use Bitrix\Sale\PaySystem\Manager;

Loader::includeModule('sale');

// Путь к файлу хендлера — относительно document_root.
// Manager::includeHandler смотрит сюда и подключает класс.
Manager::includeHandler('Sale\\Handlers\\PaySystem\\AcmePayHandler');

// РЕГИСТРАЦИЯ ЧЕРЕЗ UI:
// Админка → Магазин → Настройки → Платёжные системы → Добавить.
// Поле «Обработчик» указать: Sale\Handlers\PaySystem\AcmePayHandler
// Заполнить параметры: ACME_API_KEY, ACME_SECRET, success/fail URLs.
//
// Таблица b_sale_pay_system_action создаётся автоматически при сохранении формы.
// Ручное редактирование через код не рекомендуется — обновление сломает.
