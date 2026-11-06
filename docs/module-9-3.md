# Модуль 9.3: BX24.js (клиентская библиотека)

## Теория

### Что такое BX24.js

**BX24.js** — это JavaScript-библиотека для взаимодействия встраиваемых приложений с Bitrix24. Она предоставляет:

- Вызов методов REST API из браузера (без серверного прокси)
- Работа с диалогами (открытие карточек CRM, попапы)
- Получение информации о текущем пользователе и контексте
- Управление интерфейсом (resize, закрытие, уведомления)

**Ключевое отличие от серверного REST API:**
- Запросы идут напрямую из браузера пользователя
- Используется `AUTH_ID` вместо `access_token`
- Не нужен отдельный backend для простых задач

---

### Подключение BX24.js

```html
<script src="//api.bitrix24.com/api/v1/"></script>
<script>
BX24.init(function() {
    // Приложение готово к работе
    console.log('BX24 initialized');
});
</script>
```

**Важно:**
- `BX24.init()` вызывается один раз при загрузке страницы
- Все вызовы API должны быть внутри `BX24.init()` или после его выполнения
- BX24 автоматически получает `AUTH_ID` из параметров URL iframe

---

### Основные методы BX24.js

#### **BX24.callMethod** — вызов REST API метода

```javascript
BX24.callMethod(
    'crm.deal.get',           // метод
    {id: 123},                 // параметры
    function(result) {         // callback
        if (result.error()) {
            console.error(result.error());
        } else {
            console.log(result.data());
        }
    }
);
```

**Возвращаемый объект:**
```javascript
{
    data: function() { return {...} },      // данные
    error: function() { return null },      // ошибка (если есть)
    more: function() { return false },      // есть ли следующая страница
    next: function(callback) { ... },       // загрузить следующую страницу
    total: function() { return 100 },       // всего элементов
    status: function() { return 200 }       // HTTP код
}
```

---

#### **BX24.callBatch** — пакетный вызов

Для нескольких запросов одновременно:

```javascript
BX24.callBatch(
    {
        'deal': ['crm.deal.get', {id: 123}],
        'contact': ['crm.contact.get', {id: 456}],
        'company': ['crm.company.get', {id: 789}]
    },
    function(result) {
        console.log('Deal:', result.deal.data());
        console.log('Contact:', result.contact.data());
        console.log('Company:', result.company.data());
    }
);
```

**Преимущества:**
- Один HTTP-запрос вместо трех
- Быстрее и меньше нагрузка на сервер
- Лимит: до 50 команд в одном batch

---

#### **BX24.callListMethod** — вызов метода списка с автоматической пагинацией

```javascript
BX24.callListMethod(
    'crm.lead.list',                        // метод
    {filter: {'>DATE_CREATE': '2024-01-01'}}, // параметры
    ['ID', 'TITLE', 'DATE_CREATE'],          // поля для выборки
    function(result) {
        const leads = result;  // массив всех лидов (не первая страница!)
        console.log('Total leads:', leads.length);
    }
);
```

**Особенность:**
- Автоматически загружает все страницы
- Возвращает массив, а не объект с `data()`
- Медленнее, чем `callMethod` для больших объемов

---

### Работа с диалогами

#### **BX24.openApplication** — открыть приложение

```javascript
BX24.openApplication(
    {
        'page': 'settings',          // страница приложения
        'dealId': 123                // параметры для передачи
    },
    function(result) {
        // callback после закрытия
        console.log('App closed');
    }
);
```

---

#### **BX24.closeApplication** — закрыть приложение

```javascript
BX24.closeApplication();
```

---

#### **BX24.openPath** — открыть страницу в Bitrix24

```javascript
// Открыть карточку сделки
BX24.openPath('/crm/deal/details/123/');

// Открыть список лидов
BX24.openPath('/crm/lead/list/');
```

---

#### **BX24.resizeWindow** — изменить размер iframe

```javascript
// Установить высоту iframe в 500px
BX24.resizeWindow(document.body.scrollWidth, 500);
```

---

#### **BX24.fitWindow** — автоматическая подгонка размера

```javascript
// Подстроить размер iframe под содержимое
BX24.fitWindow();
```

---

#### **BX24.selectUser** — выбор пользователя

```javascript
BX24.selectUser(function(user) {
    console.log('Selected user:', user);
    // user: {id: 1, name: 'Иван', lastName: 'Петров'}
});
```

---

#### **BX24.selectUsers** — выбор нескольких пользователей

```javascript
BX24.selectUsers(function(users) {
    console.log('Selected users:', users);
    // users: [{id: 1, name: '...'}, {id: 2, name: '...'}]
});
```

---

### Работа с пользователем

#### **BX24.getAuth** — получить AUTH_ID

```javascript
BX24.init(function() {
    const authId = BX24.getAuth();
    console.log('AUTH_ID:', authId);
});
```

---

#### **BX24.getLang** — получить язык интерфейса

```javascript
const lang = BX24.getLang();  // 'ru', 'en', 'de', etc.
```

---

#### **BX24.getDomain** — получить домен портала

```javascript
const domain = BX24.getDomain();  // 'company.bitrix24.ru'
```

---

### Работа с контекстом (Placement)

#### **BX24.placement.info** — информация о плейсменте

```javascript
BX24.init(function() {
    const placementInfo = BX24.placement.info();
    console.log('Placement:', placementInfo.placement);      // 'CRM_DEAL_DETAIL_TAB'
    console.log('Options:', placementInfo.options);          // {ID: 123}
    console.log('Entity ID:', placementInfo.options.ID);     // 123
});
```

---

### Уведомления и диалоги

#### **BX24.showNotification** — показать уведомление

```javascript
BX24.showNotification('Сделка успешно обновлена', 'success');

// Типы: 'success', 'error', 'info', 'warning'
```

---

#### **BX24.showConfirm** — диалог подтверждения

```javascript
BX24.showConfirm(
    'Вы уверены?',
    function() {
        console.log('Да');
    },
    function() {
        console.log('Нет');
    }
);
```

---

#### **BX24.showPrompt** — диалог с вводом

```javascript
BX24.showPrompt(
    'Введите название:',
    function(value) {
        console.log('Введено:', value);
    }
);
```

---

### Работа с карточками CRM

#### **BX24.openDealCard** — открыть карточку сделки

```javascript
BX24.openDealCard(123, function() {
    console.log('Deal card opened');
});
```

---

#### **BX24.openLeadCard** — открыть карточку лида

```javascript
BX24.openLeadCard(456);
```

---

#### **BX24.openContactCard** — открыть карточку контакта

```javascript
BX24.openContactCard(789);
```

---

#### **BX24.openCompanyCard** — открыть карточку компании

```javascript
BX24.openCompanyCard(101);
```

---

### Примеры использования

#### Пример 1: Получение данных сделки

```javascript
BX24.init(function() {
    const dealId = BX24.placement.info().options.ID;

    BX24.callMethod('crm.deal.get', {id: dealId}, function(result) {
        if (result.error()) {
            alert('Ошибка: ' + result.error());
            return;
        }

        const deal = result.data();
        console.log('Deal title:', deal.TITLE);
        console.log('Amount:', deal.OPPORTUNITY);
        console.log('Stage:', deal.STAGE_ID);
    });
});
```

---

#### Пример 2: Создание задачи из виджета

```javascript
BX24.init(function() {
    document.getElementById('create-task').addEventListener('click', function() {
        BX24.showPrompt('Введите название задачи:', function(taskName) {
            BX24.callMethod('tasks.task.add', {
                fields: {
                    TITLE: taskName,
                    RESPONSIBLE_ID: 1,  // текущий пользователь
                    DEADLINE: new Date(Date.now() + 86400000).toISOString()
                }
            }, function(result) {
                if (result.error()) {
                    BX24.showNotification('Ошибка создания задачи', 'error');
                } else {
                    BX24.showNotification('Задача создана #' + result.data().id, 'success');
                }
            });
        });
    });
});
```

---

#### Пример 3: Пакетная загрузка данных

```javascript
BX24.init(function() {
    const dealId = BX24.placement.info().options.ID;

    BX24.callBatch({
        'deal': ['crm.deal.get', {id: dealId}],
        'contact': ['crm.deal.contact.items.get', {id: dealId}],
        'company': ['crm.deal.fields', {}],
        'products': ['crm.deal.productrows.get', {id: dealId}]
    }, function(result) {
        const deal = result.deal.data();
        const contacts = result.contact.data();
        const products = result.products.data();

        // Отображаем всё на одной странице
        document.getElementById('deal-title').textContent = deal.TITLE;
        document.getElementById('contacts').innerHTML = contacts.map(c =>
            `<div>${c.NAME} ${c.LAST_NAME}</div>`
        ).join('');
        document.getElementById('products-count').textContent = products.length;
    });
});
```

---

#### Пример 4: Виджет в карточке звонка

```javascript
BX24.init(function() {
    const callInfo = BX24.placement.info().options;
    const phone = callInfo.PHONE_NUMBER;

    // Ищем контакт по телефону
    BX24.callMethod('crm.duplicate.findbycomm', {
        type: 'PHONE',
        values: [phone]
    }, function(result) {
        if (result.error()) {
            console.error(result.error());
            return;
        }

        const contact = result.data();
        if (contact && contact.CONTACT) {
            // Загружаем данные контакта
            BX24.callMethod('crm.contact.get', {id: contact.CONTACT[0]}, function(contactResult) {
                const c = contactResult.data();
                document.getElementById('client-name').textContent =
                    c.NAME + ' ' + c.LAST_NAME;
                document.getElementById('client-phone').textContent = phone;
            });
        }
    });
});
```

---

#### Пример 5: Автоматическая подгонка размера виджета

```javascript
BX24.init(function() {
    // После загрузки контента подгоняем размер
    setTimeout(function() {
        BX24.fitWindow();
    }, 500);
});

// Или при изменении контента
document.getElementById('load-more').addEventListener('click', function() {
    // Загрузили данные
    loadData();

    // Подогнали размер
    BX24.fitWindow();
});
```

---

## Вопросы для собеседования

### Q1: Что такое BX24.js и зачем он нужен?

**Ответ:**
BX24.js — это JavaScript-библиотека для встраиваемых приложений Bitrix24. Она позволяет вызывать REST API напрямую из браузера без серверного прокси, работать с диалогами, получать контекст (ID сделки, лида), управлять интерфейсом.

**Преимущества:**
- Быстрая разработка — не нужен отдельный backend
- Доступ к контексту (текущая сущность)
- Встроенные диалоги и уведомления

---

### Q2: Как вызвать метод REST API из BX24.js?

**Ответ:**
```javascript
BX24.callMethod('crm.deal.get', {id: 123}, function(result) {
    if (result.error()) {
        console.error(result.error());
    } else {
        console.log(result.data());
    }
});
```

`result.data()` — возвращает данные
`result.error()` — возвращает ошибку (или null)
`result.more()` — есть ли следующая страница
`result.next(callback)` — загрузить следующую страницу

---

### Q3: Чем отличается callMethod от callBatch?

**Ответ:**
- **callMethod** — один запрос к одному методу
- **callBatch** — несколько запросов к разным методам в одном HTTP-запросе

Пример callBatch:
```javascript
BX24.callBatch({
    'deal': ['crm.deal.get', {id: 123}],
    'contact': ['crm.contact.get', {id: 456}]
}, function(result) {
    console.log(result.deal.data());
    console.log(result.contact.data());
});
```

**Преимущество callBatch:** быстрее, меньше нагрузка на сервер, до 50 команд.

---

### Q4: Как получить ID текущей сделки в виджете?

**Ответ:**
```javascript
BX24.init(function() {
    const placementInfo = BX24.placement.info();
    const dealId = placementInfo.options.ID;
    console.log('Deal ID:', dealId);
});
```

`placement.info()` возвращает:
```javascript
{
    placement: 'CRM_DEAL_DETAIL_TAB',
    options: {ID: 123}
}
```

---

### Q5: Как показать уведомление пользователю?

**Ответ:**
```javascript
BX24.showNotification('Сделка обновлена', 'success');

// Типы: 'success', 'error', 'info', 'warning'
```

---

### Q6: Как изменить размер iframe виджета?

**Ответ:**
```javascript
// Установить конкретную высоту
BX24.resizeWindow(document.body.scrollWidth, 500);

// Автоматически подогнать под содержимое
BX24.fitWindow();
```

---

### Q7: Как открыть карточку сделки из приложения?

**Ответ:**
```javascript
BX24.openDealCard(123, function() {
    console.log('Deal card opened');
});
```

Аналогично:
- `BX24.openLeadCard(456)`
- `BX24.openContactCard(789)`
- `BX24.openCompanyCard(101)`

---

### Q8: Что такое AUTH_ID и как его получить в JS?

**Ответ:**
**AUTH_ID** — временный токен для вызова REST API, передается в параметрах iframe. В BX24.js получаешь через:

```javascript
const authId = BX24.getAuth();
```

Но обычно BX24 автоматически использует его для `callMethod`, поэтому редко нужен напрямую.

---

### Q9: Как загрузить все страницы списка?

**Ответ:**
Используй `BX24.callListMethod` — он автоматически загружает все страницы:

```javascript
BX24.callListMethod(
    'crm.lead.list',
    {filter: {'>DATE_CREATE': '2024-01-01'}},
    ['ID', 'TITLE'],
    function(result) {
        console.log('Total:', result.length);
    }
);
```

Или вручную через `result.more()` и `result.next()`:
```javascript
BX24.callMethod('crm.lead.list', {}, function(result) {
    if (result.more()) {
        result.next(function(nextResult) {
            // следующая страница
        });
    }
});
```

---

### Q10: Как передать параметры в openApplication?

**Ответ:**
```javascript
BX24.openApplication({
    'page': 'settings',
    'dealId': 123,
    'customParam': 'value'
}, function() {
    // callback после закрытия
});
```

Параметры будут доступны в iframe через `BX24.placement.info().options`.

---

## Практические задачи

- **Задача 1:** Виджет сделки с загрузкой данных через BX24.callMethod
- **Задача 2:** Форма создания задачи с BX24.callMethod и showPrompt
- **Задача 3:** Дашборд с batch-запросом для загрузки нескольких сущностей
- **Задача 4:** Список лидов с автоподгрузкой через callListMethod

**Решения задач находятся в `examples/module-9-3/`**

---

## Полезные ссылки

- **BX24.js документация:** https://dev.1c-bitrix.ru/rest_help/js_library/
- **Список методов:** https://dev.1c-bitrix.ru/rest_help/js_library/bx24_methods.php

---

## Частые ошибки

### Ошибка 1: BX24 is not defined

**Причина:** Скрипт не загрузился или HTTPS не настроен.

**Решение:**
```html
<script src="//api.bitrix24.com/api/v1/"></script>
```

Убедись, что URL виджета — HTTPS.

### Ошибка 2: callMethod возвращает 401 Unauthorized

**Причина:** AUTH_ID истек или не передан.

**Решение:** Проверь, что BX24.init() выполняется до вызовов API. Если виджет открыт долго — перезагрузи iframe.

### Ошибка 3: placement.info() возвращает undefined

**Причина:** Виджет не открыт через плейсмент.

**Решение:** Проверь, что виджет зарегистрирован через `placement.bind` и открыт в интерфейсе Bitrix24.

### Ошибка 4: iframe не подгоняет размер

**Причина:** fitWindow() вызван до загрузки контента.

**Решение:**
```javascript
BX24.init(function() {
    loadData();
    setTimeout(function() {
        BX24.fitWindow();
    }, 500);
});
```

---

## Вопросы для самопроверки

1. Что такое BX24.js?
2. Как вызвать метод REST API из JavaScript?
3. Чем отличается callMethod от callBatch?
4. Как получить ID текущей сделки в виджете?
5. Как показать уведомление пользователю?
6. Как изменить размер iframe?
7. Как открыть карточку сделки?
8. Что такое AUTH_ID?
9. Как загрузить все страницы списка?
10. Как передать параметры в openApplication?

---

**Следующий модуль:** [Модуль 9.4: Публикация в Bitrix24.Market](module-9-4.md)
