# Модуль 1.4: События (Event Handlers)

## Теория

### Что такое событие в Bitrix

Событие — это точка в коде ядра или модуля, где вызываются зарегистрированные
обработчики. Механизм реализует паттерн «Наблюдатель» и решает главную задачу
продукта: расширять поведение, не трогая `/bitrix/`, иначе обновление снесёт
правки.

Событие описывается тремя вещами: **модуль-источник**, **имя события**,
**параметры**. Например, `main` / `OnAfterUserAdd` передаёт массив полей
пользователя, а `main` / `OnEpilog` не передаёт ничего.

Ключевое, что нужно понять до любого кода: **в Bitrix три разных механизма
событий, и у них разные контракты обработчика**. Ошибка «взял пример из
интернета» почти всегда означает, что механизм не тот.

### Как устроен диспетчер

Диспетчер один — `\Bitrix\Main\EventManager`, синглтон
(`EventManager::getInstance()`). Внутри — массив `handlers[МОДУЛЬ][СОБЫТИЕ]`,
куда попадают подписки из двух источников:

1. **из базы** — таблица `b_module_to_module` (постоянные подписки);
2. **из кода текущего хита** — вызовы `addEventHandler*()`.

При первом обращении к событию обработчики из БД загружаются и кэшируются в
managed cache; рантайм-обработчики дописываются в тот же массив и список
пересортировывается по `SORT` (по возрастанию). При равном `SORT` сохраняется
порядок регистрации. Отсюда два практических следствия:

- порядок выполнения зависит от данных в БД, а не только от кода;
- `addEventHandler()` из `init.php` не «перебивает» постоянные обработчики,
  а встаёт в общую очередь по `SORT`.

В каждой подписке ядро хранит **версию** (`VERSION` в `b_module_to_module`):
`1` — обработчик старого типа (позиционные аргументы, возможно по ссылке),
`2` — обработчик D7 (один аргумент — объект `\Bitrix\Main\Event`). Именно по
этому признаку диспетчер решает, что передать в callback. Поэтому вопрос «какой
метод регистрации использовать» — не стилистический, а функциональный.

### Три механизма событий

| | Старое ядро (legacy) | D7 | ORM (D7-сущности) |
|---|---|---|---|
| Кто отправляет | `ExecuteModuleEventEx()` / `GetModuleEvents()` | `new \Bitrix\Main\Event(...)->send()` | `DataManager::add/update/delete()` |
| Что получает обработчик | позиционные аргументы, часто по ссылке (`&$arFields`) | объект `\Bitrix\Main\Event` | объект `\Bitrix\Main\ORM\Event` (наследник `Main\Event`) |
| Как изменить данные | присвоить в `$arFields` (работает только при передаче по ссылке) | вернуть `EventResult` с параметрами — **если отправитель их читает** | `EventResult::modifyFields()` / `unsetFields()` |
| Как отменить операцию | `$APPLICATION->ThrowException()` + `return false` | `EventResult::ERROR` — **если отправитель смотрит результаты** | `EventResult::addError(new ORM\EntityError(...))` |
| Как регистрировать | `AddEventHandler()` (рантайм) / `RegisterModuleDependences()` (БД) | `addEventHandler()` / `registerEventHandler()` | `\Bitrix\Main\ORM\EventManager::addEventHandler($dataClass, ...)` |
| Примеры | `main:OnBeforeUserAdd`, `iblock:OnBeforeIBlockElementAdd`, `catalog:OnBeforeProductAdd` | `sale:OnSaleOrderSaved`, свои события | `OnBeforeAdd`, `OnAfterUpdate`, `OnBeforeDelete` любой сущности |

Третья колонка — не «ещё один способ подписаться на то же самое», а отдельный
слой: у ORM своя точка вызова, свои девять событий и свой `EventResult`
(`\Bitrix\Main\ORM\EventResult`, а не `\Bitrix\Main\EventResult`).

### Ключевое различие: `addEventHandler` и `addEventHandlerCompatible`

```php
// D7-событие: в обработчик придёт объект Main\Event
$eventManager->addEventHandler('sale', 'OnSaleOrderSaved', [Handler::class, 'onSaved']);

// Событие старого ядра: аргументы придут позиционно, как в старом API
$eventManager->addEventHandlerCompatible('main', 'OnAfterUserAdd', [Handler::class, 'onUserAdd']);
```

Документация формулирует это прямо: обработчики, зарегистрированные через
`addEventHandler`, получают **объект события**; чтобы получить «старые»
аргументы — нужен `addEventHandlerCompatible`. То же для постоянной
регистрации: `registerEventHandler` / `registerEventHandlerCompatible`.

Практический смысл: если повесить `addEventHandler` на `OnAfterUserAdd`,
обработчик **вызовется**, но первым аргументом придёт `Main\Event`, а не
`$arFields`. Код вида `$arFields['ID']` упадёт (в PHP 8 — `TypeError` на
сигнатуре с `array &$arFields`) или молча ничего не сделает, если типов нет.
Самый неприятный вариант — обработчик «есть», а эффекта нет.

**Правило выбора:** смотрите, как событие отправляется.

- `GetModuleEvents` + `ExecuteModuleEventEx` в исходниках модуля → `*Compatible`;
- `new Event(...)` + `send()` → обычный метод;
- `DataManager` → ORM-менеджер (см. раздел про ORM).

### Сигнатуры: порядок аргументов различается

| Метод | 4-й аргумент | 5-й аргумент |
|---|---|---|
| `AddEventHandler($module, $event, $cb, $sort = 100, $fullPath = false)` | `sort` | `fullPath` |
| `EventManager::addEventHandler(..., $callback, $includeFile = false, $sort = 100)` | `includeFile` | `sort` |

Это документированное отличие: в D7 подключение файла идёт **перед**
сортировкой. Классическая ошибка при переходе — передать `100` четвёртым
аргументом в D7-метод и получить «подключаемый файл» с именем `100`.

Постоянная регистрация:

```php
// D7
$eventManager->registerEventHandler(
    $fromModuleId,   // кто отправляет
    $eventType,
    $toModuleId,     // кто подписывается (ваш модуль)
    $toClass,        // класс обработчика
    $toMethod,
    $sort = 100,
    $toPath = '',    // файловый обработчик вместо класса
    $toMethodArg = []
);

// legacy
RegisterModuleDependences($fromModuleId, $eventType, $toModuleId, $toClass, $toMethod, $sort, $toPath, $toMethodArg);
```

### Временная и постоянная регистрация

| Способ | Где живёт | Когда использовать |
|---|---|---|
| `AddEventHandler()` / `addEventHandler*()` | только текущий хит | `init.php`, локальные правки, тесты, «включаем на один запрос» |
| `RegisterModuleDependences()` / `registerEventHandler*()` | таблица `b_module_to_module` | установка модуля: подписка должна жить после хита |

Постоянную регистрацию делает **установщик модуля**, а не `init.php`:
`InstallEvents()` в `install/index.php` (или `install/step.php` / `unstep.php`,
в зависимости от шаблона модуля) при установке и `UnInstallEvents()` при
удалении. Иначе обработчик останется в БД после удаления модуля и будет
падать на отсутствующем классе.

```php
// local/modules/vendor.example/install/index.php
public function InstallEvents(): void
{
    EventManager::getInstance()->registerEventHandlerCompatible(
        'main',
        'OnAfterUserAdd',
        $this->MODULE_ID,
        \Vendor\Example\Handlers\UserHandler::class,
        'onAfterUserAdd'
    );
}

public function UnInstallEvents(): void
{
    EventManager::getInstance()->unRegisterEventHandler(
        'main',
        'OnAfterUserAdd',
        $this->MODULE_ID,
        \Vendor\Example\Handlers\UserHandler::class,
        'onAfterUserAdd'
    );
}
```

`unRegisterEventHandler()` удаляет запись из `b_module_to_module` по набору
(модуль-источник, событие, модуль-подписчик, класс, метод). Кэш списка
обработчиков чистится ядром.

### Отписка от рантайм-обработчика

`addEventHandler*()` возвращает **числовой ключ** подписки, а
`removeEventHandler()` принимает именно его:

```php
$key = $eventManager->addEventHandler('main', 'OnProlog', [Handler::class, 'onProlog']);

// позже, в этом же хите
$eventManager->removeEventHandler('main', 'OnProlog', $key);
```

Попытка передать третьим аргументом `[Handler::class, 'onProlog']` — ошибка:
сигнатура `removeEventHandler($module, $event, $iEventHandlerKey)`. Ключ живёт
только до конца хита, сохранять его в БД или между запросами бессмысленно.
Для постоянных подписок — `unRegisterEventHandler()` / `UnRegisterModuleDependences()`.

### Изменение данных

**Legacy.** Данные меняются присваиванием в массив, переданный по ссылке.
Работает только если обработчик объявлен с `&` и подписка совместимая:

```php
public static function onBeforeUserAdd(array &$arFields): void
{
    $arFields['UF_SOURCE'] = 'api';
}
```

**D7.** Обработчик возвращает `EventResult` с параметрами. Но это не
универсальный механизм: **результат читает отправитель**. Если отправитель
результаты игнорирует (большинство legacy-событий), возврат `EventResult`
ничего не изменит.

```php
$event = new \Bitrix\Main\Event('vendor.module', 'OnDocumentBuild', ['fields' => $fields]);
$event->send();

foreach ($event->getResults() as $result) {
    if ($result->getType() === \Bitrix\Main\EventResult::SUCCESS) {
        $fields = $result->getParameters()['fields'] ?? $fields;
    }
}
```

**ORM.** `\Bitrix\Main\ORM\EventResult`:

```php
$result = new \Bitrix\Main\ORM\EventResult();
$result->modifyFields(['UF_CODE' => mb_strtolower(trim($fields['UF_CODE']))]);
$result->unsetFields(['UF_SOMETHING']);   // исключить поле из операции
```

`modifyFields()` меняет значения перед валидацией и записью;
`unsetFields()` выкидывает поле из `add()`/`update()`. Важно:
`modifyFields()` **заменяет** набор изменений, а не дополняет его — второй
вызов затрёт первый без ошибки. Собирайте всё в один массив.

Обработчик обязан вернуть именно `\Bitrix\Main\ORM\EventResult`. Если вернуть
другой объект (в том числе `\Bitrix\Main\EventResult`), ORM заменит его пустым
результатом, и изменения молча не применятся.

### Отмена операции

**Legacy** — исключение плюс `false`:

```php
public static function onBeforeUserAdd(array &$arFields): bool
{
    if (trim((string)($arFields['LAST_NAME'] ?? '')) === '') {
        global $APPLICATION;
        $APPLICATION->ThrowException('Необходимо указать фамилию');
        return false;
    }
    return true;
}
```

`CUser::Add()` проверяет исключение и прерывает работу; текст ошибки уходит в
`LAST_ERROR`. Ровно так же ведут себя `OnBeforeIBlockElementAdd`,
`OnBeforeProductAdd` (`return false`) и большинство legacy `OnBefore*`.

**D7** — `EventResult::ERROR`, но только там, где отправитель просматривает
`getResults()`. Пример — `sale:OnSaleOrderBeforeSaved`: ошибка в результате
прерывает сохранение заказа. Если отправитель результаты не читает,
`EventResult::ERROR` — просто запись в массиве, и операция продолжится.

Отдельно: `return false` в D7-обработчике **ничего не отменяет**. Ядро
оборачивает любой не-`EventResult` возврат в `EventResult(UNDEFINED, $value)`,
то есть `false` становится «неопределённым результатом», а не ошибкой.

**ORM** — ошибка в результате:

```php
$result = new \Bitrix\Main\ORM\EventResult();
$result->addError(new \Bitrix\Main\ORM\EntityError('Нельзя менять внешний код'));
// или точечно по полю:
$result->addError(new \Bitrix\Main\ORM\Fields\FieldError($event->getEntity()->getField('XML_ID'), 'Поле занято'));
return $result;
```

`addError()` принимает только `\Bitrix\Main\ORM\EntityError` и
`\Bitrix\Main\ORM\Fields\FieldError`; обычный `\Bitrix\Main\Error` даст
`TypeError`.

Операция прерывается, ошибка попадает в `Result` вызова `add()`/`update()`.

### Где смотреть параметры события

Единственный надёжный источник — код, который событие отправляет, и
документация под вашу версию ядра. Быстрая проверка на инсталляции:

```php
$eventManager->addEventHandlerCompatible('main', 'OnAfterUserAdd', function ($arFields) {
    \Bitrix\Main\Diag\Debug::writeToFile(
        ['params' => $arFields],
        'OnAfterUserAdd',
        '/local/logs/events.log'
    );
});
```

Для D7-события то же самое через `$event->getParameters()`. Не угадывайте имена
ключей: у `OnAfterUserAdd` это `ID`, `RESULT`, `RESULT_MESSAGE`, у
`OnAfterUserLogin` — `USER_ID`, `LOGIN`, `RESULT_MESSAGE`.

### События модуля main

| Событие | Параметры | Особенности |
|---|---|---|
| `OnBeforeUserAdd` | `&$arFields` | отмена: `ThrowException` + `false`; можно править поля |
| `OnAfterUserAdd` | `&$arFields` (`ID`, `RESULT`, `RESULT_MESSAGE`) | вызывается **после попытки** добавления: `ID > 0` — успех, иначе смотрите `RESULT_MESSAGE` |
| `OnBeforeUserUpdate` / `OnAfterUserUpdate` | `&$arFields` (в `OnAfter` — `RESULT`) | те же правила |
| `OnBeforeUserDelete` / `OnUserDelete` | `$userId` | в `OnBefore` можно запретить |
| `OnAfterUserLogin` | `&$arParams` (`USER_ID`, `LOGIN`, `RESULT_MESSAGE`) | **неуспешный вход — это `USER_ID <= 0`**, отдельного события «логин не удался» в `main` нет |
| `OnUserLogout` / `OnAfterUserLogout` | — | выход пользователя |
| `OnProlog` / `OnEpilog` / `OnPageStart` / `OnEndBufferContent` | — / `&$content` | `OnEpilog` и `OnEndBufferContent` **не выполняются** на композитных страницах |
| `OnBeforeEventSend` | `&$arFields`, `&$arTemplate` | правка письма до отправки; здесь же можно отменить отправку |

### События модуля iblock

| Событие | Параметры | Что можно |
|---|---|---|
| `OnBeforeIBlockElementAdd` | `&$arFields` | менять поля, отменять (`false`) |
| `OnAfterIBlockElementAdd` | `&$arFields` (`ID`, `RESULT`) | побочные действия; `ID` есть только при успехе |
| `OnBeforeIBlockElementUpdate` | `&$arFields` | менять поля, отменять |
| `OnAfterIBlockElementUpdate` | `&$arFields` | побочные действия |
| `OnBeforeIBlockElementDelete` | `$elementId` | отмена |
| `OnAfterIBlockElementDelete` | `$arFields` | очистка связанных данных |
| `OnBeforeIBlockSectionAdd/Update/Delete`, `OnAfterIBlockSection*` | аналогично | разделы |

Инфоблоки исторически legacy: подписка — `addEventHandlerCompatible` (или
`AddEventHandler`), обработчики получают массивы. D7-обёртки над инфоблоками
(`\Bitrix\Iblock\ElementTable`, `SectionTable`) дают **дополнительно**
ORM-события — но это другой слой и другая точка вызова: `CIBlockElement::Add`
и `ElementTable::add` — не одно и то же, и события одного не означают события
другого. Что именно доступно в вашей версии — смотрите в коде ядра
`/bitrix/modules/iblock/lib/elementtable.php` и `element.php` `[проверить]`.

### События модуля catalog

Legacy-события `CCatalogProduct` (`OnBeforeProductAdd`, `OnProductAdd`,
`OnBeforePriceAdd`, `OnPriceAdd`) **устарели**: по документации они работают,
только если в настройках модуля «Каталог» включена опция «Включить поддержку
устаревших событий». На свежей коробке они по умолчанию не стреляют — и это
частая причина «обработчик не вызывается».

Современный путь — ORM-слой каталога: сущность `\Bitrix\Catalog\ProductTable`
и модель `\Bitrix\Catalog\Model\Product`, которые пишут через `DataManager` и,
значит, отправляют ORM-события (`OnBeforeAdd`, `OnAfterUpdate` и т.д.).
Точный состав событий и момент их отправки в конкретной версии модуля
проверяйте в `/bitrix/modules/catalog/lib/producttable.php` и
`/bitrix/modules/catalog/lib/model/product.php` `[проверить]`: каталог менялся
между версиями заметнее, чем `main`.

Практическое правило: если нужно реагировать на изменение товара, сначала
проверьте, через какой API идёт запись (ORM-модель, `CIBlockElement`,
`CCatalogProduct`), и подписывайтесь на событие того слоя.

### События модуля sale

Два разных поколения, и их нельзя смешивать:

**Legacy (старый API заказов):**

- `OnSaleStatusOrder` — изменение статуса заказа;
- `OnSalePayOrder` — оплата;
- `OnSaleOrderCanceled` — отмена;
- `OnOrderSave`, `OnOrderUpdate`, `OnOrderDelete` — запись заказа старым API.

Параметры и условия вызова у каждого свои, часть событий не срабатывает при
создании объекта (например, смена статуса — это именно смена). Точные
сигнатуры — в документации модуля под вашу версию `[проверить]`.

**D7 (новый API, `\Bitrix\Sale\Order::save()`):**

| Событие | Параметры | Отмена |
|---|---|---|
| `OnSaleOrderBeforeSaved` | `ENTITY` — объект заказа, `VALUES` | да, `EventResult::ERROR` |
| `OnSaleOrderSaved` | `ENTITY`, `VALUES`, `IS_NEW` | по документации оба события могут прервать сохранение; `OnSaleOrderSaved` срабатывает уже после записи, поэтому полагаться на откат здесь нельзя |
| `OnSaleOrderEntitySaved` и события коллекций (корзина, оплаты, отгрузки) | зависят от сущности | частично |

`IS_NEW` — штатный способ отсечь «только создание» и не поймать рекурсию:
флаг `true` при первом сохранении и `false` при последующих.

```php
$eventManager->addEventHandler('sale', 'OnSaleOrderSaved', [Handler::class, 'onOrderSaved']);

public static function onOrderSaved(\Bitrix\Main\Event $event): void
{
    if (!$event->getParameter('IS_NEW')) {
        return;
    }
    /** @var \Bitrix\Sale\Order $order */
    $order = $event->getParameter('ENTITY');
    // ...
}
```

### ORM-события

Любая сущность `DataManager` отправляет девять событий. Параметры — по
документации ORM:

| Событие | Параметры | Отмена |
|---|---|---|
| `OnBeforeAdd` | `fields`, `object` | да (`EventResult`) |
| `OnAdd` | `fields`, `object` | нет |
| `OnAfterAdd` | `primary`, `fields`, `object` | нет |
| `OnBeforeUpdate` | `primary`, `fields`, `object` | да |
| `OnUpdate` | `primary`, `fields`, `object` | нет |
| `OnAfterUpdate` | `primary`, `fields`, `object` | нет |
| `OnBeforeDelete` | `primary`, `object` | да |
| `OnDelete` | `primary`, `object` | нет |
| `OnAfterDelete` | `primary`, `object` | нет |

Важные детали:

- в `OnBefore*` объект (`object`) — изменяемая запись, в `On*`/`OnAfter*` —
  копия;
- `oldFields` в текущих ORM-событиях нет: чтобы получить прежние значения,
  делайте отдельный `getById()` до записи или ведите историю сами;
- у событий удаления нет `fields`: данные удаляемой записи — в `object`;
- `OnAdd`/`OnUpdate`/`OnDelete` идут **до** SQL, но отменить операцию через
  `EventResult` не могут — только `OnBefore*`.

Регистрация — через ORM-менеджер, первым аргументом имя data-класса, вторым —
константа события:

```php
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventManager;

$handlerId = EventManager::getInstance()->addEventHandler(
    \Vendor\Module\PriceTable::class,
    DataManager::EVENT_ON_BEFORE_ADD,
    static function (Event $event): \Bitrix\Main\ORM\EventResult {
        $result = new \Bitrix\Main\ORM\EventResult();
        $fields = $event->getParameter('fields');

        if (empty($fields['NAME'])) {
            $result->addError(new \Bitrix\Main\ORM\EntityError('NAME обязателен'));
        }

        return $result;
    }
);
```

Альтернатива без внешней подписки — переопределить статические методы прямо в
своём `DataManager`: `onBeforeAdd`, `onAdd`, `onAfterAdd`, `onBeforeUpdate`,
`onUpdate`, `onAfterUpdate`, `onBeforeDelete`, `onDelete`, `onAfterDelete`.
Ядро вызывает их автоматически; никакой дополнительной «конфигурации в
`getMap()`» не требуется. Подходит, когда обработчик — часть самой сущности;
для внешней бизнес-логики используйте ORM-менеджер, чтобы не смешивать слои.

### Свои события

```php
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

$event = new Event('vendor.module', 'OnPriceCalculated', ['price' => $price, 'product' => $product]);
$event->send();

foreach ($event->getResults() as $result) {
    if ($result->getType() === EventResult::SUCCESS) {
        $params = $result->getParameters();
    }
}
```

- `EventResult::SUCCESS = 1`, `ERROR = 2`, `UNDEFINED = 0`;
- метод чтения типа — `getType()`; `getResultType()` оставлен как устаревший
  алиас;
- **всегда разбирайте результаты**: несколько обработчиков — несколько
  результатов, «последний победил» без явного правила приводит к плавающему
  поведению;
- передавайте объекты, а не большие массивы: объект приходит по ссылке, и все
  обработчики видят актуальное состояние; массив копируется;
- `Event::setFilter([...])` ограничивает получателей списком модулей — событие
  не уйдёт всем подписчикам;
- отладка: `$event->turnDebugOn()` перед `send()`, в обработчиках
  `$event->addDebugInfo($data)`, после — `$event->getDebugInfo()`. В debug-режиме
  исключения обработчиков складываются в событие, а не пробрасываются наружу.

### События, исключения и транзакции

Механизм не даёт «магии транзакций». Что реально важно:

- `Event::send()` **не ловит** исключения (`sendToEventHandler` перехватывает их
  только при включённом debug и складывает в событие). Исключение из
  обработчика уходит наверх и может прервать вызывающий код;
- legacy-события вызываются прямо в теле метода: `OnAfterIBlockElementAdd`
  выполняется после `INSERT`, но до коммита внешней транзакции, если она есть;
- ORM `add()`/`update()` выполняют запись и связанные события в рамках
  соединения; вложенность и откат определяются тем, кто открыл транзакцию —
  ядро, модуль или ваш код;
- «`OnAfter*` = данные зафиксированы» — неверно. Данные уже записаны в рамках
  соединения, но транзакция может быть откачена выше по стеку. Если после
  события вы делаете необратимое действие (внешний HTTP, списание денег),
  откат уже не поможет;
- поэтому внешние вызовы внутри обработчиков, особенно внутри транзакции,
  выносятся в агенты/очередь. Долгий HTTP внутри транзакции держит блокировки и
  роняет параллельные запросы.

### Рекурсия и идемпотентность

Обновление сущности внутри её же `OnAfter*` обработчика вызывает событие
снова. Классический сценарий — «пересчитать поле и сохранить»:

```php
// плохо: бесконечная рекурсия или лавина обновлений
public static function onAfterUpdate(\Bitrix\Main\Event $event): void
{
    $id = $event->getParameter('primary')['ID'];
    \Vendor\Module\ItemTable::update($id, ['RATING' => 100]);
}
```

Что делать:

- **не писать, если значение не изменилось** — сравнить `oldFields`/`fields`;
- **лок-флаг** на время обработчика (`static $running = false;`) — минимальная
  защита в пределах хита;
- **`IS_NEW`/`primary`** — отсекать «только создание» или «только обновление»;
- **идемпотентность**: повторный вызов с теми же входными данными не должен
  создавать вторую сущность/второе письмо. Уникальный ключ в БД и проверка
  «уже сделано» надёжнее, чем флаг в памяти;
- **циклы между обработчиками**: A обновляет сущность B, B — сущность A.
  Разрывается только явным правилом («B не трогает A»).

### Производительность

`init.php` исполняется на каждом хите, где он подключён, а обработчики — на
каждом вызове события. Отсюда:

- в подписке — только регистрация, никакой тяжёлой логики и запросов;
- проверка `Loader::includeModule()` перед подпиской на события модуля, иначе
  класс обработчика может не существовать;
- `OnEndBufferContent`/`OnEpilog` не работают на композите — код, который
  обязан выполняться всегда, туда не кладут;
- массовые операции (`updateMulti`, импорт) дергают события на каждую запись:
  обработчик с запросом на запись превращает импорт 10 000 позиций в 10 000
  запросов.

## Частые ошибки

### ❌ Ошибка 1: D7-подписка на legacy-событие

```php
// Плохо: OnAfterUserAdd отправляется старым ядром
$eventManager->addEventHandler('main', 'OnAfterUserAdd', [Handler::class, 'onUserAdd']);

public static function onUserAdd(array &$arFields): void { /* ... */ }
```

**Последствие:** обработчик получает `Main\Event`, а не массив; в PHP 8 —
`TypeError`, без типов — молчаливое отсутствие эффекта.

**Исправление:** `addEventHandlerCompatible(...)`, либо обработчик с
`\Bitrix\Main\Event $event` и работой через `getParameters()`.

### ❌ Ошибка 2: ожидание, что `EventResult` меняет данные в legacy-событии

```php
public static function onBeforeUserAdd(\Bitrix\Main\Event $event)
{
    return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, ['fields' => $fields]);
}
```

**Причина:** `CUser::Add` результаты не читает; контракт — ссылка на массив.
**Последствие:** поля не меняются, ошибок нет.
**Исправление:** совместимый обработчик и `$arFields['...'] = ...`.

### ❌ Ошибка 3: `return false` вместо исключения

```php
// Плохо: операция не отменится
$eventManager->addEventHandler('main', 'OnBeforeUserAdd', [Handler::class, 'check']);
public static function check(array &$arFields) { return false; }
```

**Причина:** legacy-ядро смотрит на `$APPLICATION->GetException()`, а не на
возврат. **Исправление:** `ThrowException()` + `return false`.

### ❌ Ошибка 4: отмена через `EventResult::ERROR` там, где результаты не читают

`EventResult::ERROR` работает только в событиях, чей отправитель перебирает
`getResults()`. В `OnSaleOrderBeforeSaved` — прерывает сохранение; в
`OnAfterUserAdd` — не делает ничего. Проверять надо по коду отправителя.

### ❌ Ошибка 5: отписка по callback

```php
$eventManager->removeEventHandler('main', 'OnProlog', [Handler::class, 'onProlog']); // не сработает
```

**Исправление:** сохранить ключ, который вернул `addEventHandler()`, и передать
его третьим аргументом. Для постоянных подписок — `unRegisterEventHandler()`.

### ❌ Ошибка 6: перепутанный порядок аргументов при переходе на D7

```php
AddEventHandler('main', 'OnProlog', $cb, false, 50);  // 4-й — sort, 5-й — путь
$eventManager->addEventHandler('main', 'OnProlog', $cb, false, 50); // 4-й — includeFile
```

Формально второй вызов валиден, но в `AddEventHandler` на том же месте стоит
другой параметр. При миграции кода аргументы переносятся осознанно.

### ❌ Ошибка 7: забыли `&` в legacy-обработчике

```php
function MyHandler($arFields) { $arFields['NAME'] = 'Test'; } // изменения потеряны
```

Без ссылки PHP получает копию массива. Плюс подпись должна совпадать с
контрактом события — иначе `TypeError` на строгой типизации.

### ❌ Ошибка 8: тяжёлая работа в обработчике

Внешний HTTP, генерация PDF, рассылка на 10 000 адресов внутри
`OnAfterUserAdd` блокируют запрос пользователя. Выносить в агенты, очередь,
`\Bitrix\Main\Application::getInstance()->addBackgroundJob()` (если доступно в
вашей версии) или собственную очередь.

### ❌ Ошибка 9: проверка прав администратора по `in_array(1, CUser::GetUserGroup())`

ID группы администраторов зависит от инсталляции, а принадлежность к группе —
не то же самое, что право на операцию. Для запрета удаления проверяйте
`$USER->IsAdmin()` или конкретное право через `\Bitrix\Main\Access` /
`$USER->CanDoOperation()`, а хардкод `1` оставляйте только в учебных примерах.

### ❌ Ошибка 10: секреты и персональные данные в логе

Параметры `OnAfterUserLogin` содержат `PASSWORD` (в открытом виде при
`PASSWORD_ORIGINAL=Y`). Логировать событие целиком нельзя: в лог попадут
пароли. Фильтруйте поля перед записью.

## Вопросы для собеседования

### Junior

**1. Что такое события в Bitrix и зачем они нужны?**
Точки расширения в ядре и модулях: код подписывается на событие и выполняется
в нужный момент без правки `/bitrix/`. Реализация паттерна «Наблюдатель»;
главная ценность — обновляемость продукта.

**2. Чем отличаются `OnBefore*` и `OnAfter*`?**
`OnBefore*` — до записи: можно изменить данные и прервать операцию.
`OnAfter*` — после записи: данные уже в БД, прервать нельзя, только побочные
действия. Контракт «как менять» и «как отменять» задаёт конкретное событие, а
не префикс.

**3. Где регистрируют обработчики?**
Рантайм — `init.php` через `EventManager`/`AddEventHandler`. Постоянно — в
установщике модуля через `registerEventHandler*()`/`RegisterModuleDependences()`,
запись в `b_module_to_module`.

**4. Что такое приоритет (`sort`)?**
Порядок вызова: меньше — раньше. По умолчанию 100. При равном `sort` — порядок
регистрации. У `AddEventHandler` `sort` — четвёртый аргумент, у D7 — пятый.

**5. Как отменить операцию в `OnBefore*`?**
Legacy: `$APPLICATION->ThrowException()` + `return false`. ORM:
`EventResult::addError(...)`. D7: `EventResult::ERROR` — если отправитель
читает результаты.

### Middle

**6. Чем `addEventHandler` отличается от `addEventHandlerCompatible`?**
Типом аргументов: первый передаёт `\Bitrix\Main\Event`, второй — позиционные
аргументы старого вызова (возможно, по ссылке). Выбор определяется тем, как
событие отправлено. То же для `registerEventHandler*`.

**7. Чем временная регистрация отличается от постоянной?**
Временная живёт в памяти до конца хита, постоянная — в `b_module_to_module`
и загружается в каждом запросе через managed cache. Постоянную ставят при
установке модуля и снимают при удалении.

**8. Как удалить обработчик?**
Рантайм: сохранить ключ из `addEventHandler()` и передать в
`removeEventHandler($module, $event, $key)`. Постоянный:
`unRegisterEventHandler($module, $event, $toModule, $class, $method)` или
`UnRegisterModuleDependences()`.

**9. Как создать своё событие?**
`new \Bitrix\Main\Event($module, $name, $params)` → `send()` → разобрать
`getResults()`. Тип результата — `getType()` (`SUCCESS`/`ERROR`/`UNDEFINED`).

**10. Как узнать, какие параметры приходят в обработчик?**
По коду отправителя и документации под свою версию; на практике — временный
обработчик с `Debug::writeToFile($event->getParameters())` или
`print_r($arFields)`.

**11. Почему обработчик может не вызываться?**
Неверный модуль/имя события; подписка D7-методом на legacy-событие (вызовется,
но упадёт); событие не отправляется этим API (`CCatalogProduct` при выключенной
поддержке устаревших событий); не подключён модуль; обработчик зарегистрирован
после вызова события; кэш подписок.

**12. Можно ли отменить операцию в `OnAfter*`?**
Нет. Данные уже записаны. Откат возможен только если вы сами открыли
транзакцию и откатываете её, и это плохая практика. Правильное место —
`OnBefore*`.

### Senior

**13. Как ядро решает, что передать в обработчик?**
В подписке хранится версия (колонка `VERSION` в `b_module_to_module`):
`1` — совместимый обработчик, получает позиционные аргументы; `2` — D7,
получает объект `Event`. Оба вида лежат в одном списке `EventManager`,
отсортированном по `SORT`.

**14. `EventResult::ERROR` — универсальная отмена?**
Нет. Отмена происходит только там, где отправитель анализирует `getResults()`.
В legacy-событиях результат игнорируется. В ORM отмена — через
`ORM\EventResult::addError()`.

**15. Как работают ORM-события?**
Девять событий на сущность (`OnBeforeAdd` … `OnAfterDelete`).
Регистрируются через `\Bitrix\Main\ORM\EventManager` с именем data-класса и
константой `DataManager::EVENT_*`, либо переопределяются статическими методами
в самом `DataManager`. `OnBefore*` может вернуть `ORM\EventResult` с
`modifyFields`, `unsetFields`, `addError`.

**16. Как предотвратить рекурсию?**
Не писать, если значение не изменилось (сравнить `oldFields`/`fields`);
static-лок; отсечение по `IS_NEW`; разрыв цикла между обработчиками; для
внешних действий — очередь вместо синхронного вызова.

**17. Что такое идемпотентность обработчика и зачем она?**
Повторный вызов с теми же данными не должен давать новый эффект. Нужна из-за
ретраев, двойных нажатий, повторной отправки событий и рекурсии. Обеспечивается
уникальным ключом в БД, проверкой «уже обработано», `request_id`.

**18. Как события взаимодействуют с транзакциями?**
Сами по себе — никак. Обработчик исполняется в том соединении и той
транзакции, что и вызывающий код, и исключение из него прерывает вызывающий
код. `OnAfter*` не означает коммит. Внешние необратимые действия внутри
транзакции недопустимы: откат их не отменит, а блокировки держатся.

**19. Обработчик бросил исключение — что будет?**
В D7 `Event::send()` исключение пробрасывается (перехват только в debug-режиме
с записью в событие). В legacy — уходит в вызывающий метод; часть методов
ядра оборачивает вызов в try/catch, и тогда операция может продолжиться, а
исключение — попасть в лог. Поведение нужно проверять по коду отправителя.

**20. Как организовать обработчики в собственном модуле?**
Классы в `lib/` с namespace, регистрация — в `install/index.php`
(`InstallEvents`/`UnInstallEvents`), `init.php` не участвует. Один класс — одна
область (пользователи, заказы, инфоблоки). Регистрация только после
`Loader::includeModule()` нужного модуля.

**21. `OnEndBufferContent` и композитный сайт?**
Композит отдаёт страницу из кеша без выполнения пролога и эпилога, поэтому
`OnEpilog`/`OnEndBufferContent` не срабатывают. Код, который обязан
выполняться всегда, размещают в компоненте/шаблоне или в агенте.

**22. Когда вместо синхронного обработчика нужна очередь?**
Когда действие не обязано завершиться в рамках запроса: письма, интеграции,
пересчёты, генерация файлов. Критерий — «пользователь не должен ждать» плюс
«сбой не должен ломать основную операцию».

**23. Как отлаживать события в чужом коде?**
Временный обработчик с логированием `getParameters()`; `findEventHandlers()`
для проверки, что подписка видна; `Debug::writeToFile`; проверка, что событие
вообще отправляется (точка вызова в исходнике модуля); для своих событий —
`turnDebugOn()`/`getDebugInfo()`.

**24. Что делает `setFilter()` у события?**
Ограничивает список модулей-получателей: событие уйдёт только подписчикам из
указанного списка. Полезно, чтобы не дёргать чужие обработчики.

**25. Почему `addEventHandler` из `init.php` — не лучшая точка для бизнес-логики?**
`init.php` выполняется на каждом хите и не управляется версиями/установкой:
подписку нельзя включить/выключить штатно, нельзя откатить, легко получить
дубли при копировании кода между проектами. Для продукта — модуль.

## Практические задачи

1. **Логирование входов** — писать в лог успешные и неуспешные попытки входа:
   логин, IP, время, результат. Пароли не логировать. Неуспех определяется по
   `USER_ID <= 0` в `OnAfterUserLogin`.

2. **Автозаполнение символьного кода** — при добавлении/обновлении элемента
   инфоблока генерировать `CODE` транслитом из `NAME`, если код не задан, с
   проверкой уникальности внутри инфоблока.

3. **Уведомление о крупном заказе** — при создании заказа на сумму выше порога
   отправлять письмо администратору. Только на создание (`IS_NEW`), без
   блокировки оформления, без дублей.

Дополнительно (на проверку понимания):

4. Переделать задачу 3 так, чтобы письмо уходило асинхронно и не зависело от
   доступности SMTP в момент оформления.

5. Переделать задачу 2 на ORM-события сущности `\Bitrix\Iblock\ElementTable` и
   объяснить, какие случаи при этом перестанут покрываться.

**Решения задач находятся в `examples/module-1-4/`**

## Полезные ссылки

- [EventManager (D7)](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php)
- [AddEventHandler: отличие порядка аргументов от D7](https://dev.1c-bitrix.ru/api_help/main/functions/module/addeventhandler.php)
- [EventResult](https://docs.1c-bitrix.ru/api/classes/Bitrix-Main-EventResult.html)
- [События ORM и Highload-блоков](https://docs.1c-bitrix.ru/pages/modules/highloadblocks/events.html)
- [DataManager: методы `onBeforeAdd` и остальные](https://docs.1c-bitrix.ru/api/classes/Bitrix-Main-ORM-Data-DataManager.html)
- [Сохранение заказа: `OnSaleOrderBeforeSaved`, `OnSaleOrderSaved`](https://training.bitrix24.com/api_d7/bitrix/sale/events/order_saved.php)
- [События main](https://dev.1c-bitrix.ru/api_help/main/events/index.php)
- [События iblock](https://dev.1c-bitrix.ru/api_help/iblock/events/index.php)
- [События catalog](https://dev.1c-bitrix.ru/api_help/catalog/events/index.php)
