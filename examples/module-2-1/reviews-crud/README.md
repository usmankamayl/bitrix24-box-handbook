# Задача 1: Таблица отзывов с CRUD операциями

## Описание

Создание полноценной ORM-таблицы для хранения отзывов о товарах с демонстрацией всех базовых операций: CREATE, READ, UPDATE, DELETE.

## Что демонстрирует

- ✅ Создание класса-наследника `DataManager`
- ✅ Описание различных типов полей (Integer, String, Text, Boolean, Datetime)
- ✅ Валидация данных (ограничение длины, кастомная валидация рейтинга)
- ✅ Значения по умолчанию
- ✅ Обработчики событий (onBeforeAdd, onAfterAdd)
- ✅ Все CRUD операции с проверкой ошибок
- ✅ Фильтрация и сортировка данных
- ✅ Пагинация
- ✅ Поиск по тексту
- ✅ Агрегатные функции (COUNT, AVG)

## Структура файлов

```
reviews-crud/
├── ReviewTable.php    # ORM-класс таблицы отзывов
├── examples.php       # 20 примеров использования
├── install.sql        # SQL для создания таблицы
└── README.md          # Этот файл
```

## Установка

### 1. Создайте таблицу в БД

Выполните SQL-запрос из файла `install.sql`:

```bash
mysql -u root -p your_database < install.sql
```

Или через phpMyAdmin/Adminer скопируйте содержимое `install.sql`.

### 2. Подключите класс в autoload

**Вариант А: Через composer.json**

```json
{
    "autoload": {
        "psr-4": {
            "Custom\\Reviews\\": "local/classes/Custom/Reviews/"
        }
    }
}
```

Поместите `ReviewTable.php` в `local/classes/Custom/Reviews/ReviewTable.php`

**Вариант Б: Ручное подключение**

```php
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/Custom/Reviews/ReviewTable.php');
```

## Использование

### Базовые операции

#### Создание отзыва

```php
use Custom\Reviews\ReviewTable;

$result = ReviewTable::add([
    'PRODUCT_ID' => 100,
    'USER_ID' => 1,
    'RATING' => 5,
    'REVIEW_TEXT' => 'Отличный товар!',
    'ADVANTAGES' => 'Качество, цена',
    'ACTIVE' => 'Y',
]);

if ($result->isSuccess()) {
    echo "Отзыв создан с ID: " . $result->getId();
} else {
    print_r($result->getErrorMessages());
}
```

#### Получение отзывов

```php
// Один отзыв
$review = ReviewTable::getById(1)->fetch();

// Список с фильтром
$result = ReviewTable::getList([
    'select' => ['ID', 'RATING', 'REVIEW_TEXT'],
    'filter' => [
        '=PRODUCT_ID' => 100,
        '>=RATING' => 4,
        '=ACTIVE' => 'Y',
    ],
    'order' => ['DATE_CREATE' => 'DESC'],
    'limit' => 10,
]);

while ($review = $result->fetch()) {
    echo $review['REVIEW_TEXT'];
}
```

#### Обновление отзыва

```php
$result = ReviewTable::update(1, [
    'ACTIVE' => 'Y',
    'LIKES_COUNT' => 10,
]);

if ($result->isSuccess()) {
    echo "Отзыв обновлен";
}
```

#### Удаление отзыва

```php
$result = ReviewTable::delete(1);

if ($result->isSuccess()) {
    echo "Отзыв удален";
}
```

## Примеры из examples.php

Файл `examples.php` содержит 20 готовых примеров:

1. ✅ Простое добавление отзыва
2. ✅ Добавление с валидацией
3. ✅ Обработка ошибки валидации (рейтинг вне диапазона)
4. ✅ Получение одного отзыва по ID
5. ✅ Список всех активных отзывов
6. ✅ Фильтр по товару и рейтингу
7. ✅ Поиск по тексту отзыва
8. ✅ Пагинация (limit + offset)
9. ✅ Подсчет количества отзывов
10. ✅ Отзывы за последнюю неделю
11. ✅ Модерация отзыва (активация)
12. ✅ Увеличение счетчика лайков
13. ✅ Редактирование текста отзыва
14. ✅ Массовое обновление (одобрение всех отзывов пользователя)
15. ✅ Удаление одного отзыва
16. ✅ Удаление старых неактивных отзывов
17. ✅ Средний рейтинг товара (AVG)
18. ✅ Топ популярных отзывов (сортировка по лайкам)
19. ✅ Проверка существования отзыва
20. ✅ Статистика распределения рейтингов

## Ключевые особенности

### Валидация

```php
// Рейтинг от 1 до 5
'validation' => function() {
    return [
        function($value) {
            if ($value < 1 || $value > 5) {
                return 'Рейтинг должен быть от 1 до 5';
            }
            return true;
        },
    ];
}

// Минимальная длина текста
'validation' => function() {
    return [
        new Entity\Validator\Length(10, null),
    ];
}
```

### Значения по умолчанию

```php
// Текущая дата/время
'default_value' => function() {
    return new DateTime();
}

// Статическое значение
'default_value' => 'N'

// Числовое значение
'default_value' => 0
```

### Обработчики событий

```php
// Автозаполнение USER_ID текущим пользователем
public static function onBeforeAdd(Entity\Event $event)
{
    $result = new Entity\EventResult;
    $data = $event->getParameter('fields');

    if (empty($data['USER_ID'])) {
        global $USER;
        if ($USER->IsAuthorized()) {
            $result->modifyFields(['USER_ID' => $USER->GetID()]);
        }
    }

    return $result;
}
```

## Запуск примеров

1. Откройте в браузере: `http://ваш-сайт/examples.php`
2. Изучите вывод каждого блока
3. Измените параметры и проверьте результат

## Частые ошибки

❌ **Забыли проверить isSuccess()**
```php
// Плохо
$result = ReviewTable::add([...]);
$id = $result->getId(); // может быть ошибка!
```

✅ **Правильно**
```php
$result = ReviewTable::add([...]);
if ($result->isSuccess()) {
    $id = $result->getId();
} else {
    // обработка ошибок
}
```

❌ **Не указали обязательные поля**
```php
ReviewTable::add([
    'RATING' => 5, // забыли PRODUCT_ID, USER_ID, REVIEW_TEXT
]);
```

❌ **Неправильный формат даты**
```php
'DATE_CREATE' => '2024-01-01' // должен быть объект DateTime
```

✅ **Правильно**
```php
use Bitrix\Main\Type\DateTime;
'DATE_CREATE' => new DateTime('2024-01-01 10:00:00')
```

## Задания для самопроверки

1. Добавьте поле `IS_VERIFIED_PURCHASE` (подтвержденная покупка)
2. Создайте метод для получения топ-3 товаров по среднему рейтингу
3. Реализуйте систему жалоб на отзывы (новое поле + счетчик)
4. Добавьте поле с вложениями (фото отзыва) типа TextField (JSON)
5. Создайте метод для массовой модерации отзывов с фильтром

## Что дальше?

После изучения этой задачи переходите к:
- **Задача 2**: Статистика заказов с агрегацией
- **Задача 3**: Связь товаров и категорий через Reference
