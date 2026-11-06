# Модуль 1.4: События (Event Handlers)

## Теория

### Что такое события в Bitrix?

События (Events) — это механизм, позволяющий выполнять пользовательский код в определённые моменты работы системы без изменения ядра Bitrix. Это реализация паттерна "Наблюдатель" (Observer).

### Основные концепции

**1. Событие (Event)** — точка в коде системы, где вызываются зарегистрированные обработчики.

**2. Обработчик события (Event Handler)** — функция или метод класса, который выполняется при наступлении события.

**3. Регистрация обработчика** — связывание функции с событием через файл `init.php` или таблицу `b_module_to_module`.

### Типы событий

#### 1. События модулей (Module Events)
Вызываются методом `ExecuteModuleEvent`:
```php
foreach (GetModuleEvents("main", "OnBeforeUserAdd", true) as $arEvent) {
    ExecuteModuleEventEx($arEvent, [&$arFields]);
}
```

#### 2. События D7 (EventManager)
Современный способ работы с событиями:
```php
$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler(
    'main',
    'OnAfterUserAdd',
    ['MyClass', 'onAfterUserAddHandler']
);
```

### Регистрация обработчиков

#### Способ 1: В файле `/local/php_interface/init.php`

```php
<?php
use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();

// Регистрация обработчика
$eventManager->addEventHandler(
    'main',              // Модуль
    'OnAfterUserAdd',    // Событие
    ['MyHandlers', 'onUserAdd']  // [Класс, Метод]
);
```

#### Способ 2: Через таблицу событий (устаревший способ)

В `/local/php_interface/init.php`:
```php
AddEventHandler("main", "OnBeforeUserUpdate", "MyOnBeforeUserUpdate");

function MyOnBeforeUserUpdate(&$arFields) {
    // Код обработчика
}
```

### Популярные события системы

#### События пользователей (main)
- `OnBeforeUserAdd` — перед добавлением пользователя
- `OnAfterUserAdd` — после добавления пользователя
- `OnBeforeUserUpdate` — перед обновлением данных пользователя
- `OnAfterUserUpdate` — после обновления
- `OnBeforeUserDelete` — перед удалением пользователя
- `OnAfterUserDelete` — после удаления
- `OnUserLogin` — при входе пользователя
- `OnUserLogout` — при выходе пользователя

#### События инфоблоков (iblock)
- `OnBeforeIBlockElementAdd` — перед добавлением элемента
- `OnAfterIBlockElementAdd` — после добавления элемента
- `OnBeforeIBlockElementUpdate` — перед обновлением элемента
- `OnAfterIBlockElementUpdate` — после обновления элемента
- `OnBeforeIBlockElementDelete` — перед удалением элемента
- `OnAfterIBlockElementDelete` — после удаления элемента

#### События каталога (catalog)
- `OnBeforeProductAdd` — перед добавлением товара
- `OnProductAdd` — после добавления товара
- `OnBeforePriceAdd` — перед добавлением цены
- `OnPriceAdd` — после добавления цены

#### События заказов (sale)
- `OnSaleOrderSaved` — при сохранении заказа
- `OnSaleStatusOrder` — при изменении статуса заказа
- `OnSalePayOrder` — при оплате заказа
- `OnSaleOrderCanceled` — при отмене заказа

### Структура обработчика

```php
class MyHandlers
{
    /**
     * Обработчик добавления пользователя
     * 
     * @param \Bitrix\Main\Event $event
     * @return void
     */
    public static function onUserAdd(\Bitrix\Main\Event $event)
    {
        $userId = $event->getParameter('ID');
        $fields = $event->getParameter('FIELDS');
        
        // Ваша логика
        // Например, отправка уведомления
        // или добавление пользователя в группу
    }
}
```

### Изменение данных в обработчиках

#### OnBefore* события (можно изменить данные)

```php
public static function onBeforeUserAdd(\Bitrix\Main\Event $event)
{
    $fields = $event->getParameter('fields');
    
    // Изменяем данные
    $fields['UF_CUSTOM_FIELD'] = 'default_value';
    
    // Возвращаем изменённые данные
    $result = new \Bitrix\Main\EventResult(
        \Bitrix\Main\EventResult::SUCCESS,
        ['fields' => $fields]
    );
    
    return $result;
}
```

#### Отмена операции

```php
public static function onBeforeUserDelete(\Bitrix\Main\Event $event)
{
    $userId = $event->getParameter('ID');
    
    // Проверка: запрещаем удаление администраторов
    $user = \Bitrix\Main\UserTable::getById($userId)->fetch();
    
    if ($user && in_array(1, \CUser::GetUserGroup($userId))) {
        return new \Bitrix\Main\EventResult(
            \Bitrix\Main\EventResult::ERROR,
            ['error' => 'Нельзя удалить администратора']
        );
    }
}
```

### Приоритет выполнения обработчиков

```php
$eventManager->addEventHandler(
    'main',
    'OnAfterUserAdd',
    ['MyHandlers', 'handler1'],
    false,
    100  // Приоритет (чем меньше, тем раньше выполнится)
);

$eventManager->addEventHandler(
    'main',
    'OnAfterUserAdd',
    ['MyHandlers', 'handler2'],
    false,
    200  // Этот обработчик выполнится позже
);
```

### Удаление обработчика

```php
$eventManager->removeEventHandler(
    'main',
    'OnAfterUserAdd',
    ['MyHandlers', 'onUserAdd']
);
```

### Создание собственных событий

```php
// Вызов события
$event = new \Bitrix\Main\Event(
    'mymodule',
    'OnCustomAction',
    ['data' => $someData]
);
$event->send();

// Получение результатов от обработчиков
$results = $event->getResults();
foreach ($results as $result) {
    if ($result->getType() == \Bitrix\Main\EventResult::SUCCESS) {
        $data = $result->getParameters();
    }
}
```

## Частые ошибки

### ❌ Ошибка 1: Регистрация в неправильном файле
```php
// Плохо: регистрация в произвольном файле
// /local/my_handlers.php
$eventManager->addEventHandler(...);
```

**Решение:** Регистрировать только в `/local/php_interface/init.php`

### ❌ Ошибка 2: Забыли про ссылку в OnBefore*
```php
// Плохо
AddEventHandler("main", "OnBeforeUserAdd", "MyHandler");
function MyHandler($arFields) {  // Без &
    $arFields['NAME'] = 'Test';  // Изменения не применятся!
}
```

**Решение:**
```php
function MyHandler(&$arFields) {  // Со ссылкой &
    $arFields['NAME'] = 'Test';
}
```

### ❌ Ошибка 3: Тяжёлые операции в обработчиках
```php
// Плохо
public static function onAfterUserAdd($event) {
    // Долгий запрос к внешнему API
    file_get_contents('https://slow-api.com/notify?user=' . $userId);
}
```

**Решение:** Использовать агенты или очереди для асинхронных операций.

### ❌ Ошибка 4: Не проверяют наличие модуля
```php
// Плохо
$eventManager->addEventHandler('sale', 'OnSaleOrderSaved', ...);
```

**Решение:**
```php
if (\Bitrix\Main\Loader::includeModule('sale')) {
    $eventManager->addEventHandler('sale', 'OnSaleOrderSaved', ...);
}
```

## Вопросы для собеседования

### 1. Что такое события в Bitrix и зачем они нужны?
**Ответ:** События — это механизм расширения функциональности без изменения ядра. Позволяют выполнять пользовательский код в определённые моменты работы системы (добавление пользователя, создание заказа и т.д.).

### 2. В чём разница между OnBefore* и OnAfter* событиями?
**Ответ:** 
- `OnBefore*` — выполняются до операции, можно изменить данные или отменить операцию
- `OnAfter*` — выполняются после операции, данные уже сохранены, изменить нельзя

### 3. Где регистрируются обработчики событий?
**Ответ:** В файле `/local/php_interface/init.php` через `EventManager::getInstance()->addEventHandler()` или через устаревший способ `AddEventHandler()`.

### 4. Как отменить операцию в обработчике OnBefore*?
**Ответ:** Вернуть `EventResult` с типом `ERROR`:
```php
return new \Bitrix\Main\EventResult(
    \Bitrix\Main\EventResult::ERROR,
    ['error' => 'Причина отмены']
);
```

### 5. Как изменить данные перед сохранением в OnBefore* событии?
**Ответ:** Получить поля через `$event->getParameter('fields')`, изменить их и вернуть через `EventResult`:
```php
$fields = $event->getParameter('fields');
$fields['NAME'] = 'New Name';
return new \Bitrix\Main\EventResult(
    \Bitrix\Main\EventResult::SUCCESS,
    ['fields' => $fields]
);
```

### 6. Какие параметры принимает метод addEventHandler?
**Ответ:**
1. Модуль (например, 'main', 'iblock')
2. Название события (например, 'OnAfterUserAdd')
3. Callback — функция или [Класс, Метод]
4. Путь к файлу (необязательно, для автозагрузки)
5. Приоритет (необязательно, по умолчанию 100)

### 7. Назовите 5 популярных событий модуля main
**Ответ:**
- OnAfterUserAdd — после добавления пользователя
- OnBeforeUserUpdate — перед обновлением пользователя
- OnUserLogin — при входе пользователя
- OnEpilog — после выполнения страницы
- OnPageStart — в начале выполнения страницы

### 8. Как создать своё собственное событие?
**Ответ:**
```php
$event = new \Bitrix\Main\Event('mymodule', 'OnMyEvent', ['data' => $data]);
$event->send();
```

### 9. Что такое приоритет обработчика и как его установить?
**Ответ:** Приоритет определяет порядок выполнения обработчиков (чем меньше число, тем раньше выполнится). Устанавливается 5-м параметром в `addEventHandler()`:
```php
$eventManager->addEventHandler('main', 'OnAfterUserAdd', ['Class', 'method'], false, 50);
```

### 10. В чём разница между ExecuteModuleEvent и EventManager?
**Ответ:**
- `ExecuteModuleEvent` — старый API (Bitrix до D7)
- `EventManager` — новый API D7, объектно-ориентированный, поддерживает приоритеты и возврат результатов

### 11. Можно ли в OnAfter* событии изменить сохранённые данные?
**Ответ:** Нет, данные уже сохранены в базу. Но можно выполнить дополнительные действия (отправить email, обновить связанные данные и т.д.).

### 12. Какие события есть у инфоблоков?
**Ответ:**
- OnBeforeIBlockElementAdd/Update/Delete
- OnAfterIBlockElementAdd/Update/Delete
- OnBeforeIBlockSectionAdd/Update/Delete
- OnAfterIBlockSectionAdd/Update/Delete

### 13. Как получить ID созданного элемента в OnAfter* событии?
**Ответ:** Через параметр события:
```php
$elementId = $event->getParameter('ID');
```

### 14. Почему не рекомендуется выполнять долгие операции в обработчиках?
**Ответ:** Обработчики выполняются синхронно и блокируют основной процесс. Долгие операции замедляют работу всего сайта. Лучше использовать агенты или очереди.

### 15. Как удалить зарегистрированный обработчик?
**Ответ:**
```php
$eventManager->removeEventHandler('main', 'OnAfterUserAdd', ['Class', 'method']);
```

## Практические задачи

1. **Логирование действий пользователей** — записывать в файл все попытки входа (успешные и неуспешные)

2. **Автозаполнение полей** — автоматически заполнять код элемента инфоблока транслитом названия

3. **Уведомления администратора** — отправлять email администратору при создании нового заказа на сумму > 100 000 руб.

**Решения задач находятся в `examples/module-1-4/`**

## Полезные ссылки

- [Официальная документация по событиям](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2916)
- [Список событий main](https://dev.1c-bitrix.ru/api_help/main/events/index.php)
- [Список событий iblock](https://dev.1c-bitrix.ru/api_help/iblock/events/index.php)
- [EventManager API](https://dev.1c-bitrix.ru/api_d7/bitrix/main/eventmanager/index.php)
