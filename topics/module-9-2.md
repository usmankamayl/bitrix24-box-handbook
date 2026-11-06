# Модуль 9.2: Placement API (размещение в интерфейсе)

## Теория

### Что такое Placement API

**Placement API** — это механизм, который позволяет приложениям встраиваться в конкретные места интерфейса Bitrix24. Вместо отдельной страницы приложение может появиться:
- в карточке сделки как вкладка
- в тулбаре лида как кнопка
- в списке контактов как действие
- в карточке звонка как виджет

**Плейсмент (placement)** — это именованная точка интерфейса, куда можно привязать виджет приложения.

---

### Как работает Placement API

```
Приложение регистрирует виджет через placement.bind
         ↓
Указывает: PLACEMENT, HANDLER (URL), TITLE
         ↓
Bitrix24 сохраняет привязку
         ↓
Пользователь открывает интерфейс (например, сделку)
         ↓
Bitrix24 проверяет зарегистрированные плейсменты
         ↓
Загружает iframe с HANDLER в указанную зону
         ↓
Передает параметры: AUTH_ID, PLACEMENT_OPTIONS (ID сущности)
```

---

### Основные методы Placement API

#### **placement.bind** — привязка виджета к плейсменту

```php
CRest::call('placement.bind', [
    'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
    'HANDLER' => 'https://your-domain.com/widget.php',
    'TITLE' => 'Аналитика сделки',
    'DESCRIPTION' => 'Дополнительная информация по сделке',
    'OPTIONS' => [
        'height' => 500,  // высота iframe в пикселях
        'icon' => 'https://your-domain.com/icon.png'
    ]
]);
```

**Параметры:**
- `PLACEMENT` — код плейсмента (обязательно)
- `HANDLER` — URL страницы, которая загрузится в iframe
- `TITLE` — название виджета (отображается в интерфейсе)
- `DESCRIPTION` — описание
- `OPTIONS` — дополнительные параметры (высота, иконка, ширина)

---

#### **placement.unbind** — отвязка виджета

```php
CRest::call('placement.unbind', [
    'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
    'HANDLER' => 'https://your-domain.com/widget.php'
]);
```

---

#### **placement.list** — список доступных плейсментов

```php
$result = CRest::call('placement.list');
print_r($result['result']);
```

Возвращает массив доступных плейсментов для текущего портала.

---

#### **placement.get** — список зарегистрированных виджетов

```php
$result = CRest::call('placement.get');
print_r($result['result']);
```

---

### Популярные плейсменты

#### **CRM (Сделки, Лиды, Контакты)**

| Плейсмент | Где отображается | Тип виджета |
|-----------|------------------|-------------|
| `CRM_DEAL_DETAIL_TAB` | Вкладка в карточке сделки | Вкладка |
| `CRM_DEAL_DETAIL_TOOLBAR` | Тулбар в карточке сделки | Кнопка |
| `CRM_DEAL_DETAIL_ACTIVITY` | Активность в карточке сделки | Вкладка |
| `CRM_LEAD_DETAIL_TAB` | Вкладка в карточке лида | Вкладка |
| `CRM_LEAD_DETAIL_TOOLBAR` | Тулбар в карточке лида | Кнопка |
| `CRM_CONTACT_DETAIL_TAB` | Вкладка в карточке контакта | Вкладка |
| `CRM_COMPANY_DETAIL_TAB` | Вкладка в карточке компании | Вкладка |
| `CRM_INVOICE_DETAIL_TAB` | Вкладка в карточке счета | Вкладка |

---

#### **CRM — Списки**

| Плейсмент | Где отображается | Тип виджета |
|-----------|------------------|-------------|
| `CRM_DEAL_LIST_TOOLBAR` | Тулбар в списке сделок | Кнопка |
| `CRM_DEAL_LIST_MENU` | Меню действий в списке сделок | Меню |
| `CRM_LEAD_LIST_TOOLBAR` | Тулбар в списке лидов | Кнопка |
| `CRM_CONTACT_LIST_TOOLBAR` | Тулбар в списке контактов | Кнопка |

---

#### **Задачи и проекты**

| Плейсмент | Где отображается | Тип виджета |
|-----------|------------------|-------------|
| `TASK_LIST_TOOLBAR` | Тулбар в списке задач | Кнопка |
| `TASK_VIEW_TAB` | Вкладка в карточке задачи | Вкладка |
| `TASK_VIEW_SIDEBAR` | Сайдбар в карточке задачи | Панель |
| `PROJECT_LIST_TOOLBAR` | Тулбар в списке проектов | Кнопка |

---

#### **Телефония**

| Плейсмент | Где отображается | Тип виджета |
|-----------|------------------|-------------|
| `CALL_CARD` | Карточка звонка | Виджет |
| `CALL_LIST_TOOLBAR` | Тулбар в списке звонков | Кнопка |

---

#### **Общие**

| Плейсмент | Где отображается | Тип виджета |
|-----------|------------------|-------------|
| `MENU_USER_PROFILE` | Меню профиля пользователя | Пункт меню |
| `MENU_SETTINGS` | Меню настроек | Пункт меню |
| `SONET_GROUP_TOOLBAR` | Тулбар в группе соцсети | Кнопка |

---

### Параметры, передаваемые виджету

Когда Bitrix24 загружает виджет, он передает GET-параметры:

```
https://your-domain.com/widget.php?
    AUTH_ID=abc123def456
    &AUTH_EXPIRES=3600
    &REFRESH_ID=refresh_token_here
    &member_id=portal123
    &status=Y
    &PLACEMENT=CRM_DEAL_DETAIL_TAB
    &PLACEMENT_OPTIONS={"ID":123}
    &LANG=ru
    &DOMAIN=company.bitrix24.ru
```

**Основные параметры:**

- `AUTH_ID` — временный токен для вызова REST API
- `AUTH_EXPIRES` — время жизни токена (обычно 3600 сек)
- `REFRESH_ID` — refresh_token для обновления access_token
- `member_id` — ID портала
- `PLACEMENT` — код плейсмента
- `PLACEMENT_OPTIONS` — JSON с контекстом (например, `{"ID":123}` для ID сделки)
- `LANG` — язык интерфейса
- `DOMAIN` — домен портала

---

### Пример виджета в карточке сделки

#### 1. Регистрация плейсмента

```php
// install.php — выполняется при установке приложения
CRest::call('placement.bind', [
    'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
    'HANDLER' => 'https://your-domain.com/deal-widget.php',
    'TITLE' => 'Дополнительная информация',
    'OPTIONS' => [
        'height' => 400
    ]
]);
```

#### 2. Код виджета (deal-widget.php)

```php
<?php
// Получаем параметры от Bitrix24
$placementOptions = json_decode($_GET['PLACEMENT_OPTIONS'], true);
$dealId = $placementOptions['ID'] ?? null;
$authId = $_GET['AUTH_ID'] ?? null;

if (!$dealId) {
    die('ID сделки не найден');
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Виджет сделки</title>
    <script src="//api.bitrix24.com/api/v1/"></script>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .info { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h3>Сделка #<?= htmlspecialchars($dealId) ?></h3>
    <div class="info">
        <div id="deal-info">Загрузка...</div>
    </div>

    <script>
    BX24.init(function() {
        // Получаем данные сделки через REST API
        BX24.callMethod('crm.deal.get', {id: <?= $dealId ?>}, function(result) {
            if (result.error()) {
                document.getElementById('deal-info').innerHTML = 'Ошибка: ' + result.error();
                return;
            }

            const deal = result.data();
            document.getElementById('deal-info').innerHTML = `
                <p><strong>Название:</strong> ${deal.TITLE}</p>
                <p><strong>Сумма:</strong> ${deal.OPPORTUNITY} ${deal.CURRENCY_ID}</p>
                <p><strong>Стадия:</strong> ${deal.STAGE_ID}</p>
                <p><strong>Ответственный:</strong> ID ${deal.ASSIGNED_BY_ID}</p>
            `;
        });
    });
    </script>
</body>
</html>
```

---

### Виджет типа "Кнопка" (TOOLBAR)

Если плейсмент — `*_TOOLBAR`, виджет отображается как кнопка. При клике открывается popup или sidebar с HANDLER.

```php
CRest::call('placement.bind', [
    'PLACEMENT' => 'CRM_DEAL_DETAIL_TOOLBAR',
    'HANDLER' => 'https://your-domain.com/send-telegram.php',
    'TITLE' => 'Отправить в Telegram',
    'OPTIONS' => [
        'type' => 'popup',  // или 'slider' (sidebar)
        'width' => 600,
        'height' => 400
    ]
]);
```

---

### Виджет типа "Вкладка" (TAB)

Если плейсмент — `*_TAB`, виджет отображается как отдельная вкладка в карточке.

```php
CRest::call('placement.bind', [
    'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
    'HANDLER' => 'https://your-domain.com/deal-analytics.php',
    'TITLE' => 'Аналитика',
    'OPTIONS' => [
        'height' => 600  // высота iframe
    ]
]);
```

---

### Виджет типа "Меню" (LIST_MENU)

Если плейсмент — `*_LIST_MENU`, виджет появляется в контекстном меню элементов списка.

```php
CRest::call('placement.bind', [
    'PLACEMENT' => 'CRM_DEAL_LIST_MENU',
    'HANDLER' => 'https://your-domain.com/bulk-action.php',
    'TITLE' => 'Массовое действие'
]);
```

При клике на пункт меню открывается popup с HANDLER и передается массив ID выбранных элементов:

```
https://your-domain.com/bulk-action.php?PLACEMENT_OPTIONS={"ID":[123,124,125]}
```

---

### Виджет типа "CALL_CARD"

Плейсмент `CALL_CARD` позволяет отображать виджет в карточке звонка во время входящего или исходящего вызова.

```php
CRest::call('placement.bind', [
    'PLACEMENT' => 'CALL_CARD',
    'HANDLER' => 'https://your-domain.com/call-widget.php',
    'TITLE' => 'Данные клиента'
]);
```

Передаваемые параметры:

```json
{
    "PLACEMENT_OPTIONS": {
        "PHONE_NUMBER": "+79001234567",
        "CALL_ID": "abc123",
        "CRM_ENTITY_TYPE": "LEAD",
        "CRM_ENTITY_ID": 456
    }
}
```

---

### Групповые действия (batch) через Placement

Если виджет в списке получает несколько ID, можно использовать batch-запросы:

```javascript
const dealIds = [123, 124, 125];  // из PLACEMENT_OPTIONS

BX24.callBatch({
    'deal1': ['crm.deal.get', {id: dealIds[0]}],
    'deal2': ['crm.deal.get', {id: dealIds[1]}],
    'deal3': ['crm.deal.get', {id: dealIds[2]}]
}, function(result) {
    console.log(result);
});
```

---

## Вопросы для собеседования

### Q1: Что такое Placement API?

**Ответ:**
Placement API — это механизм для встраивания виджетов приложений в конкретные места интерфейса Bitrix24 (карточки CRM, тулбары, списки). Приложение регистрирует виджет через `placement.bind`, указывает код плейсмента и URL обработчика → Bitrix24 отображает iframe с виджетом в нужном месте.

---

### Q2: Какие типы плейсментов существуют?

**Ответ:**
Основные типы плейсментов:
- **TAB** — вкладка в карточке (CRM_DEAL_DETAIL_TAB)
- **TOOLBAR** — кнопка в тулбаре (CRM_LEAD_DETAIL_TOOLBAR)
- **LIST_MENU** — пункт меню в списке (CRM_DEAL_LIST_MENU)
- **CALL_CARD** — виджет в карточке звонка
- **MENU_** — пункт в меню (MENU_SETTINGS)

---

### Q3: Как передать ID сделки в виджет?

**Ответ:**
Bitrix24 автоматически передает параметр `PLACEMENT_OPTIONS` в GET-параметры виджета:

```php
$placementOptions = json_decode($_GET['PLACEMENT_OPTIONS'], true);
$dealId = $placementOptions['ID'] ?? null;
```

Для плейсментов типа `CRM_DEAL_DETAIL_TAB` параметр содержит `{"ID": 123}`.

---

### Q4: Как зарегистрировать виджет в карточке сделки?

**Ответ:**
1. Вызываешь метод `placement.bind`:
   ```php
   CRest::call('placement.bind', [
       'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
       'HANDLER' => 'https://your-domain.com/widget.php',
       'TITLE' => 'Мой виджет',
       'OPTIONS' => ['height' => 400]
   ]);
   ```
2. Создаешь обработчик `widget.php`, который загружается в iframe
3. В обработчике получаешь `PLACEMENT_OPTIONS` и `AUTH_ID`
4. Используешь `BX24.js` для вызова REST API и отображения данных

---

### Q5: Как работает виджет типа "кнопка" (TOOLBAR)?

**Ответ:**
Если плейсмент — `*_TOOLBAR`, виджет отображается как кнопка в тулбаре карточки или списка. При клике на кнопку открывается popup или sidebar с содержимым HANDLER.

Параметр `type` в `OPTIONS`:
- `popup` — модальное окно поверх интерфейса
- `slider` — выезжающая панель справа

Пример:
```php
'OPTIONS' => [
    'type' => 'slider',
    'width' => 800,
    'height' => 600
]
```

---

### Q6: Как получить список зарегистрированных виджетов?

**Ответ:**
```php
$result = CRest::call('placement.get');
print_r($result['result']);
```

Вернет массив всех виджетов приложения:
```php
[
    'CRM_DEAL_DETAIL_TAB' => [
        [
            'handler' => 'https://your-domain.com/widget.php',
            'title' => 'Мой виджет',
            'description' => '...'
        ]
    ]
]
```

---

### Q7: Как удалить виджет?

**Ответ:**
```php
CRest::call('placement.unbind', [
    'PLACEMENT' => 'CRM_DEAL_DETAIL_TAB',
    'HANDLER' => 'https://your-domain.com/widget.php'
]);
```

После этого виджет исчезнет из интерфейса.

---

### Q8: Какие параметры передаются виджету при загрузке?

**Ответ:**
Bitrix24 передает GET-параметры:
- `AUTH_ID` — токен для REST API
- `PLACEMENT` — код плейсмента
- `PLACEMENT_OPTIONS` — JSON с контекстом (ID сущности, параметры)
- `member_id` — ID портала
- `LANG` — язык
- `DOMAIN` — домен портала

Пример URL:
```
widget.php?AUTH_ID=abc&PLACEMENT=CRM_DEAL_DETAIL_TAB&PLACEMENT_OPTIONS={"ID":123}
```

---

### Q9: Как обработать несколько ID элементов в виджете списка?

**Ответ:**
Для плейсментов типа `*_LIST_MENU` передается массив ID:

```php
$placementOptions = json_decode($_GET['PLACEMENT_OPTIONS'], true);
$ids = $placementOptions['ID'];  // [123, 124, 125]
```

Используешь batch-запрос:
```javascript
const batch = {};
ids.forEach((id, index) => {
    batch['item' + index] = ['crm.deal.get', {id: id}];
});

BX24.callBatch(batch, function(result) {
    console.log(result);
});
```

---

### Q10: Как отобразить виджет в карточке звонка?

**Ответ:**
1. Регистрируешь плейсмент `CALL_CARD`:
   ```php
   CRest::call('placement.bind', [
       'PLACEMENT' => 'CALL_CARD',
       'HANDLER' => 'https://your-domain.com/call-widget.php',
       'TITLE' => 'Данные клиента'
   ]);
   ```
2. В обработчике получаешь `PLACEMENT_OPTIONS` с `PHONE_NUMBER` и `CRM_ENTITY_ID`
3. Вызываешь REST API для получения данных клиента:
   ```javascript
   BX24.callMethod('crm.lead.get', {id: leadId}, function(result) {
       // отображаешь информацию
   });
   ```

---

## Практические задачи

- **Задача 1:** Виджет-вкладка в карточке сделки с аналитикой
- **Задача 2:** Кнопка в тулбаре лида — отправка в Telegram
- **Задача 3:** Виджет в списке контактов — массовое действие
- **Задача 4:** Виджет в карточке звонка — данные клиента

**Решения задач находятся в `examples/module-9-2/`**

---

## Полезные ссылки

- **Placement API:** https://dev.1c-bitrix.ru/rest_help/application_embedding/
- **Список плейсментов:** https://dev.1c-bitrix.ru/rest_help/application_embedding/placements.php
- **BX24.js:** https://dev.1c-bitrix.ru/rest_help/js_library/

---

## Частые ошибки

### Ошибка 1: Виджет не отображается в интерфейсе

**Причина:** Не зарегистрирован плейсмент или неправильный код.

**Решение:** Проверь через `placement.get` — есть ли виджет в списке. Убедись, что код плейсмента правильный (чувствителен к регистру).

### Ошибка 2: PLACEMENT_OPTIONS пустой

**Причина:** Плейсмент не поддерживает передачу ID.

**Решение:** Проверь документацию для конкретного плейсмента. Для `*_DETAIL_TAB` всегда передается `{"ID": ...}`.

### Ошибка 3: Виджет загружается, но AUTH_ID не работает

**Причина:** AUTH_ID истек или не передан корректно.

**Решение:** Сохрани `REFRESH_ID` и обнови `access_token` на сервере через OAuth.

### Ошибка 4: iframe не загружается в виджете

**Причина:** HTTP вместо HTTPS или CSP-заголовки.

**Решение:**
```php
header('Content-Security-Policy: frame-ancestors https://*.bitrix24.ru https://*.bitrix24.com');
```

---

## Вопросы для самопроверки

1. Что такое Placement API?
2. Как зарегистрировать виджет в карточке сделки?
3. Какие типы плейсментов существуют?
4. Как получить ID сделки в виджете?
5. Как работает виджет типа "кнопка"?
6. Как удалить виджет из интерфейса?
7. Какие параметры передаются виджету при загрузке?
8. Как обработать несколько ID в виджете списка?
9. Как отобразить виджет в карточке звонка?
10. Как получить список зарегистрированных виджетов?

---

**Следующий модуль:** [Модуль 9.3: BX24.js](module-9-3.md)
