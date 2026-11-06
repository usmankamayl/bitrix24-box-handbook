<?php
// examples/module-5-3/delivery-service/delivery_registration.php
// Регистрация службы доставки — через админку или миграцию.

use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\Services\Manager;

Loader::includeModule('sale');

// Подключаем класс — Bitrix сам создаст запись в b_sale_delivery_srv
// когда админ сохранит форму настройки службы.
Manager::getHandlersList(); // триггерит include handlers

// Программная регистрация (если нужна миграция, а не ручная настройка):
// Manager::add() / Manager::update() с CLASS_NAME = Acme\Sale\Delivery\AcmeDeliveryService
// и TYPE = 'ORDER' или 'PROFILE' в зависимости от типа.

// Ограничения — через UI или API:
// \Bitrix\Sale\Delivery\Restrictions\Manager::add([
//     'SERVICE_ID' => $deliveryId,
//     'CLASS_NAME' => '\Bitrix\Sale\Delivery\Restrictions\ByLocation',
//     'PARAMS'     => ['LOCATION' => '0000073738'], // код местоположения Мск
// ]);
