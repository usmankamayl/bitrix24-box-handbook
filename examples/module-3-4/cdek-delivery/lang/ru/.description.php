<?php
/**
 * Языковые константы для службы доставки СДЭК
 */

$MESS['CDEK_DELIVERY_NAME'] = 'Доставка СДЭК';
$MESS['CDEK_DELIVERY_DESC'] = 'Доставка через службу СДЭК с автоматическим расчетом стоимости';

$MESS['CDEK_PROFILE_COURIER'] = 'Курьером до двери';
$MESS['CDEK_PROFILE_COURIER_DESC'] = 'Доставка курьером по указанному адресу';

$MESS['CDEK_PROFILE_PICKUP'] = 'Самовывоз из пункта выдачи';
$MESS['CDEK_PROFILE_PICKUP_DESC'] = 'Получение заказа в пункте выдачи СДЭК';

$MESS['CDEK_ACCOUNT'] = 'Account (Client ID)';
$MESS['CDEK_ACCOUNT_DESC'] = 'Идентификатор клиента из личного кабинета СДЭК';

$MESS['CDEK_PASSWORD'] = 'Secure password (Client Secret)';
$MESS['CDEK_PASSWORD_DESC'] = 'Секретный ключ из личного кабинета СДЭК';

$MESS['CDEK_MODE'] = 'Режим работы';
$MESS['CDEK_MODE_TEST'] = 'Тестовый';
$MESS['CDEK_MODE_LIVE'] = 'Боевой';

$MESS['CDEK_FROM_CITY'] = 'Город отправки';
$MESS['CDEK_FROM_CITY_DESC'] = 'Город, из которого осуществляется отправка';

$MESS['CDEK_FROM_CODE'] = 'Код города отправки';
$MESS['CDEK_FROM_CODE_DESC'] = 'Код города в системе СДЭК (например, 44 для Москвы, 137 для Санкт-Петербурга)';

$MESS['CDEK_CACHE_TIME'] = 'Время кеширования (минуты)';
$MESS['CDEK_CACHE_TIME_DESC'] = 'Сколько минут кешировать результаты расчета (0 = не кешировать)';

$MESS['CDEK_EXTRA_CHARGE'] = 'Наценка на доставку (%)';
$MESS['CDEK_EXTRA_CHARGE_DESC'] = 'Процент наценки на стоимость доставки от СДЭК (0 = без наценки)';
