<?php
namespace Custom\Reviews;

use Bitrix\Main\Entity;
use Bitrix\Main\Type\DateTime;

/**
 * Таблица отзывов о товарах
 *
 * Демонстрирует:
 * - Создание ORM-класса для кастомной таблицы
 * - Различные типы полей
 * - Валидацию данных
 * - Значения по умолчанию
 */
class ReviewTable extends Entity\DataManager
{
    /**
     * Возвращает имя таблицы в базе данных
     */
    public static function getTableName()
    {
        return 'custom_reviews';
    }

    /**
     * Возвращает карту полей таблицы
     */
    public static function getMap()
    {
        return [
            // ID - первичный ключ с автоинкрементом
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID',
            ]),

            // ID товара (связь будет в следующих модулях)
            new Entity\IntegerField('PRODUCT_ID', [
                'required' => true,
                'title' => 'ID товара',
            ]),

            // ID автора отзыва
            new Entity\IntegerField('USER_ID', [
                'required' => true,
                'title' => 'ID пользователя',
            ]),

            // Имя автора (если не авторизован)
            new Entity\StringField('AUTHOR_NAME', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                },
                'title' => 'Имя автора',
            ]),

            // Email автора
            new Entity\StringField('AUTHOR_EMAIL', [
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(null, 255),
                    ];
                },
                'title' => 'Email автора',
            ]),

            // Рейтинг от 1 до 5
            new Entity\IntegerField('RATING', [
                'required' => true,
                'validation' => function() {
                    return [
                        function($value) {
                            if ($value < 1 || $value > 5) {
                                return 'Рейтинг должен быть от 1 до 5';
                            }
                            return true;
                        },
                    ];
                },
                'title' => 'Рейтинг',
            ]),

            // Текст отзыва
            new Entity\TextField('REVIEW_TEXT', [
                'required' => true,
                'validation' => function() {
                    return [
                        new Entity\Validator\Length(10, null), // минимум 10 символов
                    ];
                },
                'title' => 'Текст отзыва',
            ]),

            // Достоинства товара
            new Entity\TextField('ADVANTAGES', [
                'title' => 'Достоинства',
            ]),

            // Недостатки товара
            new Entity\TextField('DISADVANTAGES', [
                'title' => 'Недостатки',
            ]),

            // Активность отзыва (модерация)
            new Entity\BooleanField('ACTIVE', [
                'values' => ['N', 'Y'],
                'default_value' => 'N', // по умолчанию требует модерации
                'title' => 'Активен',
            ]),

            // Дата создания
            new Entity\DatetimeField('DATE_CREATE', [
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания',
            ]),

            // Дата модерации
            new Entity\DatetimeField('DATE_MODERATED', [
                'title' => 'Дата модерации',
            ]),

            // ID модератора
            new Entity\IntegerField('MODERATED_BY', [
                'title' => 'Модератор',
            ]),

            // Количество лайков
            new Entity\IntegerField('LIKES_COUNT', [
                'default_value' => 0,
                'title' => 'Количество лайков',
            ]),

            // Количество дизлайков
            new Entity\IntegerField('DISLIKES_COUNT', [
                'default_value' => 0,
                'title' => 'Количество дизлайков',
            ]),
        ];
    }

    /**
     * Обработчик перед добавлением
     * Можно использовать для дополнительной валидации или модификации данных
     */
    public static function onBeforeAdd(Entity\Event $event)
    {
        $result = new Entity\EventResult;
        $data = $event->getParameter('fields');

        // Автоматически заполняем USER_ID текущим пользователем, если не указан
        if (empty($data['USER_ID'])) {
            global $USER;
            if ($USER->IsAuthorized()) {
                $result->modifyFields(['USER_ID' => $USER->GetID()]);
            }
        }

        return $result;
    }

    /**
     * Обработчик после добавления
     * Можно использовать для отправки уведомлений, логирования и т.д.
     */
    public static function onAfterAdd(Entity\Event $event)
    {
        // Здесь можно добавить логику отправки уведомлений
        // или инвалидации кеша
    }
}
