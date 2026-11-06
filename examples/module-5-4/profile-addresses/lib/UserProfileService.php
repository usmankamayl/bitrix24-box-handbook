<?php
// examples/module-5-4/profile-addresses/lib/UserProfileService.php
// Сервис управления профилями покупателя (сохранённые адреса/реквизиты).
// Работает через b_sale_user_props и PropertyValue-объекты.

namespace Acme\Sale\Profile;

use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\UserPropsTable;
use Bitrix\Sale\Internals\UserPropsValueTable;
use Bitrix\Sale\Location\LocationTable;

class UserProfileService
{
    /**
     * Список профилей пользователя по PersonType.
     *
     * @param int $userId
     * @param int $personTypeId
     * @return array — [{id, name, person_type, props: {CITY: '...', ADDRESS: '...'}}]
     */
    public static function getList(int $userId, int $personTypeId): array
    {
        Loader::includeModule('sale');

        $profiles = UserPropsTable::getList([
            'filter' => [
                'USER_ID'        => $userId,
                'PERSON_TYPE_ID' => $personTypeId,
            ],
            'order' => ['DATE_UPDATE' => 'DESC'],
        ])->fetchAll();

        $result = [];
        foreach ($profiles as $profile) {
            $props = self::loadProfileProps($profile['ID']);
            $result[] = [
                'id'           => $profile['ID'],
                'name'         => $profile['NAME'],
                'person_type'  => $profile['PERSON_TYPE_ID'],
                'date_update'  => $profile['DATE_UPDATE'],
                'props'        => $props,
            ];
        }
        return $result;
    }

    /**
     * Сохранение профиля.
     *
     * $data:
     *   'name'           => 'Дом',            — название профиля
     *   'person_type_id' => 1,                — тип плательщика
     *   'props'          => ['CITY' => 'Мск', 'ADDRESS' => 'ул. Ленина 1']
     *
     * Если передан $profileId — обновляем существующий, иначе создаём новый.
     * ВАЖНО: проверяем, что профиль принадлежит пользователю.
     */
    public static function save(int $userId, array $data, ?int $profileId = null): \Bitrix\Main\Result
    {
        Loader::includeModule('sale');
        $result = new \Bitrix\Main\Result();

        // 1. Если обновляем — проверяем владельца.
        if ($profileId) {
            $profile = UserPropsTable::getRowById($profileId);
            if (!$profile || (int)$profile['USER_ID'] !== $userId) {
                $result->addError(new \Bitrix\Main\Error('Profile not found'));
                return $result;
            }
        }

        // 2. Валидация обязательных полей.
        $personTypeId = (int)$data['person_type_id'];
        if (!$personTypeId) {
            $result->addError(new \Bitrix\Main\Error('person_type_id required'));
            return $result;
        }

        // Проверяем что свойства принадлежат этому PersonType —
        // иначе можно записать свойство юрлица в профиль физлица.
        $validProps = self::getPropsForPersonType($personTypeId);
        $props = array_intersect_key($data['props'] ?? [], $validProps);

        // Требуемые свойства — отмечены в админке IS_REQUIRED=Y.
        $required = array_filter($validProps, fn($p) => $p['IS_REQUIRED'] === 'Y');
        foreach ($required as $prop) {
            if (empty($props[$prop['CODE']])) {
                $result->addError(new \Bitrix\Main\Error("Required: {$prop['NAME']}"));
            }
        }
        if (!$result->isSuccess()) return $result;

        // 3. Сохраняем профиль.
        if ($profileId) {
            UserPropsTable::update($profileId, [
                'NAME'         => $data['name'] ?? 'Профиль',
                'DATE_UPDATE'  => new \Bitrix\Main\Type\DateTime(),
            ]);
            // Удаляем старые значения — будем перезаписывать.
            UserPropsValueTable::deleteByUserPropsId($profileId);
        } else {
            $profileId = UserPropsTable::add([
                'USER_ID'        => $userId,
                'PERSON_TYPE_ID' => $personTypeId,
                'NAME'           => $data['name'] ?? 'Профиль',
                'DATE_UPDATE'    => new \Bitrix\Main\Type\DateTime(),
            ])->getId();
        }

        // 4. Сохраняем значения свойств.
        foreach ($props as $code => $value) {
            $propMeta = $validProps[$code];
            UserPropsValueTable::add([
                'USER_PROPS_ID'    => $profileId,
                'ORDER_PROPS_ID'   => $propMeta['ID'],
                'NAME'             => $propMeta['NAME'],
                'VALUE'            => is_array($value) ? serialize($value) : (string)$value,
            ]);
        }

        $result->setData(['profile_id' => $profileId]);
        return $result;
    }

    /**
     * Удаление профиля — проверяем владельца.
     */
    public static function delete(int $userId, int $profileId): \Bitrix\Main\Result
    {
        Loader::includeModule('sale');
        $result = new \Bitrix\Main\Result();

        $profile = UserPropsTable::getRowById($profileId);
        if (!$profile || (int)$profile['USER_ID'] !== $userId) {
            $result->addError(new \Bitrix\Main\Error('Profile not found'));
            return $result;
        }

        UserPropsValueTable::deleteByUserPropsId($profileId);
        UserPropsTable::delete($profileId);
        return $result;
    }

    /**
     * Автозаполнение заказа из профиля.
     * В оформлении: покупатель выбирает "Профиль №2" —
     * свойства заказа копируются из UserPropsValue в OrderProps.
     *
     * @param int $userId
     * @param int $profileId
     * @param \Bitrix\Sale\Order $order — заказ, куда копировать
     */
    public static function applyToOrder(int $userId, int $profileId, \Bitrix\Sale\Order $order): \Bitrix\Main\Result
    {
        Loader::includeModule('sale');
        $result = new \Bitrix\Main\Result();

        // 1. Проверяем владельца профиля.
        $profile = UserPropsTable::getRowById($profileId);
        if (!$profile || (int)$profile['USER_ID'] !== $userId) {
            $result->addError(new \Bitrix\Main\Error('Profile not found'));
            return $result;
        }

        // 2. Загружаем свойства профиля.
        $props = self::loadProfileProps($profileId);

        // 3. Копируем в заказ.
        $propertyCollection = $order->getPropertyCollection();
        foreach ($propertyCollection as $prop) {
            $code = $prop->getField('CODE');
            if (isset($props[$code])) {
                $prop->setValue($props[$code]);
            }
        }

        // 4. Заказ сохранится с новыми свойствами при следующем save().
        // Мы не вызываем $order->save() здесь — это прерогатива
        // вызывающего кода (может ещё корзину менять).
        $result->setData(['applied' => array_keys($props)]);
        return $result;
    }

    /**
     * Профиль → адресные данные для автоподстановки в форму.
     * Возвращает структуру для JS: {city, address, zip, phone}.
     */
    public static function getAsFormData(int $userId, int $profileId): ?array
    {
        Loader::includeModule('sale');

        $profile = UserPropsTable::getRowById($profileId);
        if (!$profile || (int)$profile['USER_ID'] !== $userId) {
            return null;
        }

        $props = self::loadProfileProps($profileId);

        return [
            'name'    => $profile['NAME'],
            'city'    => $props['CITY']     ?? '',
            'address' => $props['ADDRESS']  ?? '',
            'zip'     => $props['ZIP']      ?? '',
            'phone'   => $props['PHONE']    ?? '',
            'email'   => $props['EMAIL']    ?? '',
        ];
    }

    // ---------- внутренние ----------

    /**
     * Значения свойств профиля: UserPropsValue → {CODE: value}.
     */
    private static function loadProfileProps(int $profileId): array
    {
        $rows = UserPropsValueTable::getList([
            'filter' => ['USER_PROPS_ID' => $profileId],
            'select' => ['NAME', 'VALUE', 'ORDER_PROPS_ID'],
        ])->fetchAll();

        // Маппим ORDER_PROPS_ID → CODE — нужно узнать код свойства.
        $propIds = array_column($rows, 'ORDER_PROPS_ID');
        $propCodes = [];
        if ($propIds) {
            $propsMeta = \Bitrix\Sale\Internals\OrderPropsTable::getList([
                'filter' => ['ID' => $propIds],
                'select' => ['ID', 'CODE', 'NAME'],
            ])->fetchAll();
            foreach ($propsMeta as $pm) {
                $propCodes[$pm['ID']] = $pm['CODE'];
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $code = $propCodes[$row['ORDER_PROPS_ID']] ?? $row['NAME'];
            $result[$code] = $row['VALUE'];
        }
        return $result;
    }

    /**
     * Свойства, которые доступны для PersonType.
     * Возвращает {CODE: {ID, NAME, IS_REQUIRED, TYPE}}.
     */
    private static function getPropsForPersonType(int $personTypeId): array
    {
        $rows = \Bitrix\Sale\Internals\OrderPropsTable::getList([
            'filter' => [
                'PERSON_TYPE_ID' => $personTypeId,
                'ACTIVE'         => 'Y',
                // Только те, что можно сохранять в профиль
                // (IS_PROFILE_* или просто пользовательские поля)
            ],
            'select' => ['ID', 'CODE', 'NAME', 'TYPE', 'IS_REQUIRED'],
        ])->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['CODE']] = $row;
        }
        return $result;
    }
}
