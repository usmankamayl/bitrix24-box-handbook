# Модуль 4.2: Composite — композитный режим Bitrix

## Теория

### Что такое Composite

**Composite (композитный режим)** — технология Bitrix, при которой готовая HTML-страница кешируется и отдаётся **без запуска PHP**. Динамические блоки (корзина, имя пользователя, счётчик) подгружаются через AJAX после загрузки страницы.

**Эффект:**
- Без Composite: 200-800мс на генерацию страницы (TTFB)
- С Composite: 20-50мс (отдаётся готовый файл или из памяти)

### Как это работает

```
┌─────────────────────────────────────────────────────────┐
│  Первый запрос (кеша нет)                                │
│                                                          │
│  Браузер → nginx → PHP → Bitrix → генерация страницы    │
│                            ↓                             │
│                    Сохраняем HTML в кеш                  │
│                            ↓                             │
│                    Отдаём пользователю                   │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  Повторный запрос (кеш есть)                             │
│                                                          │
│  Браузер → nginx → сразу отдаём /bitrix/composite_cache  │
│         ↓                                                │
│  JS: BX.Composite() → AJAX → подгружаем динамику         │
│  (корзина, "Привет, Иван", счётчик)                      │
└─────────────────────────────────────────────────────────┘
```

### Ключевые понятия

#### 1. Динамические области (Dynamic Areas)

Области страницы, которые **не кешируются** в HTML, а подгружаются через AJAX.

```php
// В шаблоне компонента или на странице
$frame = $this->createFrame()->begin('Загрузка...');
?>
<div>Привет, <?= $USER->GetFullName() ?>!</div>
<div>В корзине: <?= $cartCount ?> товаров</div>
<?php
$frame->end();
```

При включённом Composite:
- HTML-страница кешируется целиком
- Блок между `begin()/end()` заменяется placeholder'ом
- После загрузки страницы BX.Composite делает AJAX-запрос за этим блоком
- Подставляет реальное содержимое

#### 2. Голосование компонентов (Component Voting)

Composite автоматически определяет, какие компоненты "динамические" — на основе их голоса.

```php
// В class.php компонента
public function executeComponent()
{
    // Голосуем ЗА composite (статичный контент)
    $this->setFrameMode(true);   // или аналог в старом API

    // Или голосуем ПРОТИВ (компонент всегда динамический)
    $this->setFrameMode(false);
}
```

**Правила голосования:**
- Если ВСЕ компоненты на странице проголосовали "за" → страница кешируется целиком
- Если хотя бы один "против" → страница считается динамической, кешируется с "битыми" областями

#### 3. Режимы Composite

| Режим | Когда | Как работает |
|-------|-------|-------------|
| **Автокомпозит** | По умолчанию | Страница кешируется, динамические области отмечаются автоматически через frame |
| **Стандартный** | Принудительно | Только указанные области динамические, остальное статика |
| **AJAX-композит** | Legacy | Устаревший, не использовать |

### Настройка Composite

#### Админка
```
Настройки → Композитный сайт → Включить композитный режим
```

#### .settings.php
```php
'composite' => [
    'value' => [
        'enabled'           => true,
        'cache_manager'     => 'redis',   // или 'files', 'memcached'
        'ttl'               => 120,       // TTL HTML-кеша (сек)
        'auto_update'       => true,      // автообновление при изменении
        'auto_update_ttl'   => 60,        // задержка автообновления
        'warning'           => true,      // предупреждение в консоли
        'include_dynamic_areas' => true,
        'domains'           => ['example.com', 'www.example.com'],
        // Исключения — страницы, которые НЕ кешируются
        'exclude_mask'      => ['/personal/*', '/cart/*', '?*nc=1*'],
        // Параметры, при которых не кешируем
        'exclude_params'    => ['utm_source', 'gclid'],
    ],
],
```

### Динамические области в коде

#### Простой frame

```php
// В шаблоне страницы или компонента
<div class="user-panel">
    <?php
    // Создаём frame — всё внутри будет динамическим
    $frame = new \Bitrix\Main\Page\Frame('user_panel');
    $frame->startDynamicArea();
    ?>
        <span><?= $USER->GetFullName() ?></span>
        <a href="/logout.php">Выйти</a>
    <?php
    $frame->finishDynamicArea();
    ?>
</div>
```

#### Frame в компоненте

```php
public function executeComponent()
{
    // Важно: вызываем ДО startResultCache
    $frame = new \Bitrix\Main\Page\Frame('cart_basket');
    $frame->startDynamicArea();

    // Динамическое содержимое
    $this->arResult['CART_COUNT'] = $this->getCartCount();

    $frame->finishDynamicArea();

    // Статичное кешируется
    if ($this->startResultCache()) {
        $this->arResult['ITEMS'] = $this->getStaticItems();
        $this->includeComponentTemplate();
    }
}
```

#### FrameBuffered — буферизация

```php
// Для сложных случаев — когда динамическая область
// генерируется в нескольких местах
$frame = \Bitrix\Main\Page\FrameBuffered::getInstance();

$frame->begin('Загрузка корзины...');
echo $this->renderCart();
$frame->end();
```

### Анти-паттерны в Composite

#### ❌ Персонализированный контент без frame

```php
// ПЛОХО — весь HTML закешируется, все увидят одно и то же
<div>Привет, <?= $USER->GetFullName() ?></div>
```

```php
// ХОРОШО
<?php $frame = new Frame('user_name'); $frame->startDynamicArea(); ?>
<div>Привет, <?= $USER->GetFullName() ?></div>
<?php $frame->finishDynamicArea(); ?>
```

#### ❌ PHP-логика в динамической области

```php
// ПЛОХО — сложная логика в frame'е будет вызываться на каждый AJAX
$frame->startDynamicArea();
$data = heavyCalculation(); // Будет вызываться каждый раз!
$frame->finishDynamicArea();
```

```php
// ХОРОШО — тяжёлые данные кешируем отдельно, frame только выводит
$data = $cache->remember('heavy', fn() => heavyCalculation());
$frame->startDynamicArea();
echo $data['formatted'];
$frame->finishDynamicArea();
```

#### ❌ Забытый csrf-токен в статическом HTML

```php
// Форма в кешированной странице → csrf-токен тоже закешируется → не сработает
// Решение: добавлять токен через JS после загрузки frame'а
?>
<script>
BX.Composite.ready(function() {
    var form = document.getElementById('feedback');
    if (form) {
        BX.ajax.insertToNode('/local/ajax/get-token.php', form);
    }
});
</script>
```

### AJAX-запросы Composite

Когда Composite включён, при загрузке страницы Bitrix автоматически:

1. Отдаёт закешированный HTML
2. Находит все `data-bx-dynamic-*` атрибуты
3. Делает batch-AJAX запрос к `/bitrix/tools/composite.php`
4. Получает HTML динамических областей
5. Подставляет в DOM

```javascript
// Ручное управление — если нужен контроль
BX.Composite.setArea('cart_count', function(html) {
    document.getElementById('cart').innerHTML = html;
});

// Принудительное обновление
BX.Composite.reload();
```

### Отладка Composite

#### Включить отладку
```php
// .settings.php
'composite_debug' => ['value' => true],
```

#### Что смотреть
- `/bitrix/composite_cache/` — закешированные страницы
- HTTP-заголовок `X-Bitrix-Composite: cache|miss|dynamic`
- В логах — `/bitrix/logs/composite.log`

#### Признаки проблем
- `X-Bitrix-Composite: dynamic` на всех страницах — какой-то компонент голосует "против"
- Одинаковый HTML для разных пользователей — забытый frame
- Пустые динамические области — ошибка в AJAX composite.php

### Composite + персонализация

Самая сложная часть — персонализированные блоки в "статичной" странице.

**Стратегии:**

| Тип контента | Решение |
|-------------|---------|
| Имя пользователя, корзина | Dynamic Area + AJAX |
| Рекомендации "Для вас" | JS-виджет, загружается после load |
| Региональные цены | JS-редирект на ?region=msk (кеш по региону) или frame |
| A/B-тесты | JS после загрузки composite |
| Счётчик "осталось 3 шт" | frame с коротким TTL |

### Composite и SEO

- Composite отдаётся с `X-Bitrix-Composite` заголовком — поисковики его игнорируют
- ВАЖНО: мета-теги, title, h1 должны быть в статической части, не в frame
- Хлебные крошки — в статике (иначе не попадут в индекс)

---

## Вопросы для собеседования

### Q1: Что такое динамическая область и как её создать?

**Ответ:** Область страницы, которая не попадает в HTML-кеш, а подгружается AJAX'ом после загрузки. Создаётся через `Frame::startDynamicArea() / finishDynamicArea()`.

### Q2: Как работает голосование компонентов?

**Ответ:** Каждый компонент "голосует" — статичный он или динамический. Если хотя бы один проголосовал "против" → страница считается динамической. Проголосовать можно через `$this->setFrameMode(true/false)` или автоматически через `Frame`.

### Q3: Где должен быть title и мета-теги при Composite?

**Ответ:** В статической части страницы. Если обернуть их в frame — поисковики увидят placeholder, а не реальные теги. Это просадит SEO.

### Q4: Что будет, если форму обернуть в статику, но забыть про csrf?

**Ответ:** csrf-токен закешируется в HTML → все пользователи получат один токен → валидация не пройдёт. Решение: подгружать токен через AJAX в динамической области или через JS.

### Q5: Как отладить, почему страница не кешируется в Composite?

**Ответ:**
1. Смотрим `X-Bitrix-Composite: dynamic` в заголовках
2. Включаем `composite_debug` в .settings.php
3. Проверяем голосование компонентов — какой проголосовал "против"
4. Смотрим `exclude_mask` и `exclude_params` — может URL в исключениях
5. Проверяем права — если пользователь авторизован, composite может не срабатывать

### Q6: Composite vs Varnish/Nginx- cache — что выбрать?

**Ответ:** Composite проще — работает "из коробки", не нужен отдельный сервер. Varnish быстрее и гибче, но требует настройки и не знает о структуре Bitrix. На Bitrix-проекте чаще Composite + Redis/Memcached для хранилища.

---

## Практические задачи

- **Задача 1:** Шаблон с правильными Dynamic Areas (корзина, имя, счётчик)
- **Задача 2:** Компонент с mixed-контентом (статичный список + динамический "избранное")
- **Задача 3:** Настройка Composite с исключениями и отладкой

**Решения задач находятся в `examples/module-4-2/`**
