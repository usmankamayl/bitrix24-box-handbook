<?php
/**
 * ORM модель для товаров на складе
 *
 * Обеспечивает работу с таблицей acme_warehouse_products:
 * - Добавление, обновление, удаление товаров
 * - Валидация данных
 * - Автоматическая нормализация полей
 */

namespace Acme\Warehouse\Model;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

class ProductTable extends Entity\DataManager
{
    /**
     * Название таблицы в БД
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'acme_warehouse_products';
    }

    /**
     * Описание структуры таблицы
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            // ID товара (первичный ключ)
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID',
            ]),

            // Название товара (обязательное поле)
            new Entity\StringField('NAME', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(1, 255),
                    ];
                },
                'title' => 'Название товара',
            ]),

            // Артикул (уникальный, обязательный)
            new Entity\StringField('SKU', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(1, 50),
                    ];
                },
                'title' => 'Артикул',
            ]),

            // Количество на складе
            new Entity\IntegerField('QUANTITY', [
                'default' => 0,
                'title' => 'Количество',
            ]),

            // Цена товара
            new Entity\FloatField('PRICE', [
                'default' => 0.00,
                'scale' => 2,
                'title' => 'Цена',
            ]),

            // Дата создания записи
            new Entity\DatetimeField('CREATED_AT', [
                'default' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания',
            ]),

            // Дата последнего обновления
            new Entity\DatetimeField('UPDATED_AT', [
                'default' => function() {
                    return new DateTime();
                },
                'title' => 'Дата обновления',
            ]),
        ];
    }

    /**
     * Действия перед добавлением товара
     *
     * Выполняется автоматически перед вставкой записи в БД:
     * - Нормализация SKU (приведение к верхнему регистру)
     * - Валидация цены (не может быть отрицательной)
     * - Валидация количества (не может быть отрицательным)
     *
     * @param Entity\Event $event
     * @return Entity\EventResult
     */
    public static function onBeforeAdd(Entity\Event $event)
    {
        $result = new Entity\EventResult();
        $data = $event->getParameter('fields');

        // Нормализация артикула (приведение к верхнему регистру)
        if (!empty($data['SKU'])) {
            $data['SKU'] = strtoupper(trim($data['SKU']));
        }

        // Валидация цены
        if (isset($data['PRICE']) && $data['PRICE'] < 0) {
            $result->addError(new Entity\FieldError(
                static::getEntity()->getField('PRICE'),
                'Цена не может быть отрицательной'
            ));
        }

        // Валидация количества
        if (isset($data['QUANTITY']) && $data['QUANTITY'] < 0) {
            $result->addError(new Entity\FieldError(
                static::getEntity()->getField('QUANTITY'),
                'Количество не может быть отрицательным'
            ));
        }

        $result->modifyFields($data);

        return $result;
    }

    /**
     * Действия перед обновлением товара
     *
     * Выполняется автоматически перед обновлением записи:
     * - Обновление времени изменения (UPDATED_AT)
     * - Валидация новых значений
     *
     * @param Entity\Event $event
     * @return Entity\EventResult
     */
    public static function onBeforeUpdate(Entity\Event $event)
    {
        $result = new Entity\EventResult();
        $data = $event->getParameter('fields');

        // Автоматическое обновление времени изменения
        $data['UPDATED_AT'] = new DateTime();

        // Валидация цены
        if (isset($data['PRICE']) && $data['PRICE'] < 0) {
            $result->addError(new Entity\FieldError(
                static::getEntity()->getField('PRICE'),
                'Цена не может быть отрицательной'
            ));
        }

        // Валидация количества
        if (isset($data['QUANTITY']) && $data['QUANTITY'] < 0) {
            $result->addError(new Entity\FieldError(
                static::getEntity()->getField('QUANTITY'),
                'Количество не может быть отрицательным'
            ));
        }

        $result->modifyFields($data);

        return $result;
    }

    /**
     * Действия после добавления товара
     *
     * Можно использовать для:
     * - Логирования операций
     * - Отправки уведомлений
     * - Синхронизации с внешними системами
     *
     * @param Entity\Event $event
     */
    public static function onAfterAdd(Entity\Event $event)
    {
        $id = $event->getParameter('id');
        $fields = $event->getParameter('fields');

        // Пример: логирование
        AddMessage2Log(sprintf(
            'Товар добавлен: ID=%d, SKU=%s, NAME=%s',
            $id,
            $fields['SKU'] ?? '',
            $fields['NAME'] ?? ''
        ), 'acme.warehouse');
    }

    /**
     * Действия после удаления товара
     *
     * @param Entity\Event $event
     */
    public static function onAfterDelete(Entity\Event $event)
    {
        $id = $event->getParameter('id');

        // Пример: логирование
        AddMessage2Log(sprintf(
            'Товар удален: ID=%d',
            $id
        ), 'acme.warehouse');
    }
}
