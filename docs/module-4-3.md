# Модуль 4.3: Отладка и профилирование в Bitrix

## Теория

### Инструменты отладки в Bitrix

| Инструмент | Что делает | Уровень |
|-----------|-----------|---------|
| **Debug Mode** | SQL-лог, PHP-ошибки | Базовый |
| **Bitrix Debug Panel** | Время генерации, запросы, кеш | Админка |
| **XHProf / Tideways** | Профилирование PHP | Senior |
| **Diagnostic** | Проверка системы, производительности | Админка |
| **Встроенный профайлер** | `Bitrix\Main\Diag\SqlTracker` | Senior |
| **Blackfire / New Relic** | APM | Enterprise |

### 1. Debug Mode — включение и использование

```php
// .settings.php — НЕ на проде!
'debug' => ['value' => true],
'exception_handling' => [
    'value' => [
        'debug'              => true,   // показывать трейс
        'handled_errors_types' => E_ALL & ~E_NOTICE & ~E_STRICT,
        'exception_errors_types' => E_ALL | E_STRICT,
        'ignore_silence'     => false,
        'assertion_throws_exception' => true,
        'assertion_error_type' => E_USER_ERROR,
    ],
],
```

**После включения:**
- Показываются PHP-ошибки с трейсом
- SQL-запросы выводятся в подвале страницы (или в лог)
- Время выполнения запросов видно

**⚠️ На проде:** `'debug' => false` — иначе пользователи увидят трейсы с путями к файлам, SQL-запросами и возможно кредами.

### 2. Bitrix Debug Panel (визуальная)

В админке внизу страницы — панель с:
- Время генерации страницы
- Количество SQL-запросов
- Время запросов
- Использованные компоненты
- Кеш-хиты/миссы

**Включается:** Настройки → Интерфейс → Панель отладки (только для админов).

### 3. SQL-лог и SqlTracker

```php
use Bitrix\Main\Diag\SqlTracker;

// Начинаем отслеживать SQL-запросы
$tracker = new SqlTracker();
$tracker->start();

// Код с запросами
$items = getItems();
$stats = getStats();

// Останавливаем и смотрим
$tracker->stop();
$queries = $tracker->getQueries();

foreach ($queries as $query) {
    printf(
        "Time: %.3fms | SQL: %s\n",
        $query->getTime() * 1000,
        $query->getSql()
    );
}

// Итог
echo "Total queries: " . count($queries);
echo "Total time: " . $tracker->getTime() . "s";
```

**В реальном проекте:** оборачиваешь подозрительный код в `start/stop` — видишь N+1 проблемы.

### 4. Встроенный профайлер (старый API)

```php
// В коде
$GLOBALS['DB']->StartUsingTimer();
$dbRes = $DB->Query($sql);
$GLOBALS['DB']->ShowQueryStat();
```

### 5. Диагностика в админке

`Настройки → Инструменты → Диагностика`:

- **Проверка системы** — соответствие требованиям Bitrix
- **Монитор производительности** — время генерации страниц по типам
- **Проверка БД** — целостность, индексы
- **Проверка кеша** — заполненность, хиты/миссы
- **PHP Info** — конфигурация сервера

**Монитор производительности:**
```
Страница: /catalog/123/ | Время: 1.2s | SQL: 45 запросов | PHP: 0.8s | MySQL: 0.4s
```

### 6. XHProf / Tideways — настоящее профилирование

**XHProf** — расширение для профилирования PHP (Facebook).

```php
// В начале страницы
if (extension_loaded('xhprof')) {
    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
}

// Код для профилирования
$result = doSomething();

if (extension_loaded('xhprof')) {
    $data = xhprof_disable();
    // Сохраняем для анализа
    file_put_contents('/tmp/profile.xhprof', serialize($data));
}
```

**Tideways** — форк XHProf с UI и интеграциями.

### 7. Отладка AJAX в Bitrix

```php
// В ajax-обработчике — НЕ используй die() для отладки,
// это сломает JSON-ответ. Пиши в лог:

use Bitrix\Main\Diag\Debug;

Debug::writeToFile([
    'request'  => $_REQUEST,
    'user_id'  => $USER->GetID(),
    'data'     => $data,
], 'ajax_debug', '/logs/ajax.log');
```

### 8. Логирование — Debug::writeToFile

```php
use Bitrix\Main\Diag\Debug;

// Простое логирование
Debug::writeToFile($data, 'my_module', '/logs/my_module.log');

// С трассировкой
Debug::writeToFile(
    ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
    'error',
    '/logs/errors.log'
);
```

**Для senior:** пиши свой wrapper с уровнями (debug/info/warning/error) и ротацией логов.

### 9. Отладка компонентов

```php
// Внутри class.php — временная отладка
public function executeComponent()
{
    if ($this->isDebug()) {
        echo '<pre>' . print_r($this->arParams, true) . '</pre>';
    }
    // ...
}

private function isDebug(): bool
{
    return $_GET['debug'] === 'Y' && $GLOBALS['USER']->IsAdmin();
}
```

### 10. Отладка Composite

```bash
# Заголовки
curl -I https://site.com/ | grep -i composite

# Лог
tail -f /bitrix/logs/composite.log

# Файловый кеш
ls -la /bitrix/composite_cache/en/
```

### 11. Мониторинг на проде

```php
// .settings.php — performance monitoring
'perfmon' => [
    'value' => [
        'use_gathering' => true,   // собирать статистику запросов
    ],
],
```

Потом в админке: Настройки → Производительность → SQL-запросы.

**Важно:** на проде это нагружает БД — включай только для диагностики.

---

## Типичные проблемы и их диагностика

| Симптом | Диагностика | Решение |
|---------|------------|---------|
| Страница грузится 10с | SqlTracker → ищем медленные запросы | Индексы, кеширование |
| N+1 запросов | SqlTracker → видим 50 одинаковых запросов | `->with()` в ORM, join'ы |
| "Белый экран" | Логи PHP (error_log), debug mode | Включить отображение ошибок |
| Страница не кешируется | Composite header `dynamic` | Искать frame-нарушение |
| Медленная админка | Debug panel, perfmon | Отключить лишние модули, индексы |
| 500 ошибка при AJAX | Логи nginx/apache, error_log | Логировать через Debug::writeToFile |

---

## Вопросы для собеседования

### Q1: Как найти медленный запрос на странице?

**Ответ:**
1. `SqlTracker::start()` / `stop()` вокруг кода
2. Debug panel в админке — видно время каждого запроса
3. `perfmon` для исторических данных
4. EXPLAIN для конкретного запроса

### Q2: Как отладить 500 ошибку на проде без debug mode?

**Ответ:**
1. `error_log` / `php_errors.log` — ищем последние fatal'ы
2. `Debug::writeToFile()` в коде — логируем подозрительные места
3. Проверить nginx/apache error log
4. Временно включить `debug` с IP-фильтром (только для себя)

```php
'debug' => ['value' => $_SERVER['REMOTE_ADDR'] === '123.45.67.89'],
```

### Q3: Что такое N+1 проблема и как её найти?

**Ответ:** Когда для каждой строки результата делается отдельный запрос. Пример:
```php
$orders = OrderTable::getList(...); // 1 запрос
foreach ($orders as $order) {
    $order->fill('USER'); // N запросов — по одному на каждый заказ
}
```
Видно в SqlTracker как N идентичных запросов. Решение: `'select' => ['*', 'USER']` или `->with('USER')`.

### Q4: Как профилировать страницу в Bitrix?

**Ответ:**
- Для быстрого анализа — Debug Panel (время, запросы, компоненты)
- Для глубокого — XHProf/Tideways (время по каждой функции, CPU, memory)
- Для продакшена — APM (Blackfire, New Relic, Tideways)

### Q5: Как логировать в Bitrix правильно?

**Ответ:** `Debug::writeToFile()` с уровнями и ротацией. Для критичного — отправка в syslog/Sentry. Не `var_dump`/`print_r` в проде — сломает JSON и покажет пользователю.

---

## Практические задачи

- **Задача 1:** SqlTracker для поиска N+1 и медленных запросов
- **Задача 2:** Система логирования с уровнями и ротацией
- **Задача 3:** Отладка "медленной страницы" — пошаговый анализ

**Решения задач находятся в `examples/module-4-3/`**
