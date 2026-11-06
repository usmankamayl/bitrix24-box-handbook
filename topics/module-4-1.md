# Модуль 4.1: Кеширование в Bitrix

## Теория

### Уровни кеширования в Bitrix

Bitrix имеет многоуровневую систему кеширования. Senior-разработчик должен знать все уровни и уметь выбирать правильный для каждого случая:

| Уровень | Где работает | Что кеширует | Управление |
|---------|--------------|--------------|------------|
| **HTML-кеш** (Composite) | Фронтенд | Готовая страница целиком | Автоматически |
| **Компонентный кеш** | Компонент | Результат executeComponent() | `$arParams['CACHE_TIME']` |
| **Managed Cache** | PHP-код | Произвольные данные | `Bitrix\Main\Data\Cache` |
| **Тегированный кеш** | PHP-код | Данные с тегами инвалидации | `TaggedCache` |
| **OPcache** | PHP-интерпретатор | Байт-код скриптов | php.ini |
| **БД-кеш** | MySQL | query_cache (устарел в MySQL 8) | - |

### 1. Managed Cache API (`Bitrix\Main\Data\Cache`)

Базовый API кеширования. Заменил устаревшие `CPageCache`, `CPhpCache`.

```php
use Bitrix\Main\Data\Cache;

$cacheTime = 3600;                    // 1 час
$cacheId   = 'my_data_' . $userId;    // уникальный ключ
$cacheDir  = '/my_module/data';       // папка в /bitrix/cache/

$cache = Cache::createInstance();

if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
    // Кеш найден и валиден
    $data = $cache->getVars();
} elseif ($cache->startDataCache()) {
    // Кеша нет — вычисляем данные
    $data = expensiveCalculation();

    // Если ошибка — отменяем кеширование
    if ($data === false) {
        $cache->abortDataCache();
    } else {
        $cache->endDataCache($data);
    }
}
```

**Важные моменты:**
- `initCache()` проверяет TTL и наличие файла кеша
- `startDataCache()` открывает буфер для записи
- `endDataCache()` сохраняет данные
- `abortDataCache()` — отмена записи (при ошибке)
- `$cacheDir` начинается с `/` — это путь от `/bitrix/cache/`, а не от корня

### 2. Тегированный кеш (`TaggedCache`)

Критически важная тема для senior-уровня. Решает проблему **точечной инвалидации**.

**Проблема без тегов:**
```php
// Закешировали список новостей на 1 час
// Новость #42 изменилась — как сбросить только её кеш?
$cacheId = 'news_list_page_2'; // нельзя сбросить одну новость
$cache->clean($cacheId, $cacheDir); // сбросит только эту страницу
// А если новость показывается на 20 разных страницах?
```

**Решение — теги:**
```php
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Data\TaggedCache;
use Bitrix\Main\Application;

$cache = Cache::createInstance();
$cacheId = 'news_detail_' . $newsId;
$cacheDir = '/news/detail';
$cacheTime = 86400; // сутки — можно долго, т.к. есть теги

if ($cache->initCache($cacheTime, $cacheId, $cacheDir)) {
    $news = $cache->getVars();
} elseif ($cache->startDataCache()) {
    $news = getNewsFromDb($newsId);

    // Регистрируем теги — кеш "привязан" к сущности
    $taggedCache = Application::getInstance()->getTaggedCache();
    $taggedCache->startTagCache($cacheDir);
    $taggedCache->registerTag('iblock_id_' . $iblockId);       // при изменении инфоблока
    $taggedCache->registerTag('iblock_element_' . $newsId);    // при изменении элемента
    $taggedCache->endTagCache();

    $cache->endDataCache($news);
}

// При изменении элемента #42:
// iblock_id_5, iblock_element_42 → сбрасываются ВСЕ закешированные
// данные с этими тегами, где бы они ни были
$taggedCache->clearByTag('iblock_element_42');
```

**Стандартные теги Bitrix:**
```php
'iblock_id_' . $iblockId          // инфоблок
'iblock_element_' . $elementId    // элемент инфоблока
'iblock_section_' . $sectionId    // раздел
'catalog_group_' . $groupId       // ценовая группа
'sale_order_' . $orderId          // заказ
'b_user_' . $userId               // пользователь
'crm_deal_' . $dealId             // сделка (в Б24)
```

### 3. Автоматическая инвалидация тегов

**Ключевой механизм:** при изменении данных через ORM/API Bitrix автоматически сбрасывает теги.

```php
// При вызове CIBlockElement::Update() или
// ElementTable::update() Bitrix сам вызовет:
$taggedCache->clearByTag('iblock_element_' . $id);

// Если ты обновляешь напрямую через SQL — теги НЕ сбросятся!
// Это классическая бага на проде
```

**Сам себе сброс тегов:**
```php
// Для своей сущности — регистрируй свой тег и сбрасывай вручную
// в обработчике OnAfterUpdate / в сервисе

// При сохранении
public static function onAfterUpdate(Event $event): void
{
    $id = $event->getParameter('id')['ID'];
    Application::getInstance()
        ->getTaggedCache()
        ->clearByTag('my_entity_' . $id);
}
```

### 4. Кеширование в компонентах

**Параметры компонента:**
```php
$APPLICATION->IncludeComponent('my:news.list', '', [
    'CACHE_TYPE'   => 'A',        // A=Auto, Y=Yes, N=No
    'CACHE_TIME'   => '3600',
    'CACHE_GROUPS' => 'Y',        // учитывать группы пользователя!
    // CACHE_FILTER — для фильтра (не рекомендуется для больших инфоблоков)
]);
```

**Режимы CACHE_TYPE:**
- `A` — Auto: берёт из настроек сайта (`/bitrix/admin/cache_admin.php`)
- `Y` — кеширует всегда с указанным CACHE_TIME
- `N` — не кеширует

**CACHE_GROUPS — критично для безопасности:**
```php
'CACHE_GROUPS' => 'Y',
// Без 'Y': админ увидит кеш гостя, гость — кеш админа
// Если контент различается по группам — ОБЯЗАТЕЛЬНО 'Y'
```

**Внутри компонента:**
```php
public function executeComponent()
{
    if ($this->startResultCache()) {
        // Данные для кеша
        $this->arResult['ITEMS'] = $this->getItems();

        // Регистрация тегов для автоинвалидации
        $this->registerTag('iblock_id_' . $this->arParams['IBLOCK_ID']);

        // Если данных нет — отменяем кеш
        if (empty($this->arResult['ITEMS'])) {
            $this->abortResultCache();
        }

        $this->includeComponentTemplate();
    }
}
```

### 5. Cache Engine — хранилище

**Типы хранилищ** (`.settings.php` → `cache.engine`):

```php
'cache' => [
    'value' => [
        'type' => 'memcache',      // memcached сервер
        // 'type' => 'redis',
        // 'type' => 'apc',
        // 'type' => 'files',      // по умолчанию — файлы
        'hosts' => ['127.0.0.1:11211'],
        'sid'  => $_SERVER['HTTP_HOST'] . '#01', // уникальный ключ для multi-site!
    ],
],
```

**Файловое хранилище:** `/bitrix/cache/` (или `/bitrix/managed_cache/`)

**Проблемы с файловым кешем на нагруженном проекте:**
- Много мелких файлов → нагрузка на inodes
- Медленная очистка (`rm -rf` десятки тысяч файлов)
- На NFS — латентность

**Решение:** memcached/redis. В Б24 часто используют memcached.

```php
// .settings.php — memcached
'cache' => [
    'value' => [
        'type'     => 'memcache',
        'memcache' => [
            'host' => '127.0.0.1',
            'port' => '11211',
        ],
        'sid' => $_SERVER['HTTP_HOST'] . '#01',
    ],
],
```

### 6. HTML-кеш — Composite (кратко, подробно в 4.2)

Композитный режим кеширует **всю страницу** и отдаёт её без PHP. Настраивается в админке:
`Настройки → Композитный сайт`

### 7. OPcache — PHP уровень

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=60        ; проверять изменения раз в минуту
opcache.fast_shutdown=1
```

**Важно:** `opcache.revalidate_freq=0` на dev (иначе изменения не подхватываются).

---

## Вопросы для собеседования

### Q1: Какие уровни кеширования есть в Bitrix?

**Ответ:**
1. **Composite (HTML-кеш)** — вся страница, отдаётся nginx/apache без PHP
2. **Кеш компонентов** — результат `executeComponent()` сериализуется в `arResult`
3. **Managed Cache** — `Bitrix\Main\Data\Cache` для произвольных данных
4. **Tagged Cache** — тегированная инвалидация
5. **OPcache** — байт-код PHP
6. **Сессионные данные** — `$_SESSION`

На собеседовании дополнительно спросят: *"Что выбрать когда?"*
- Частые изменения данных → Tagged Cache с длинным TTL
- Персонализированный контент → Managed Cache с `$cacheId` по пользователю
- Статичные страницы → Composite

### Q2: Как работает инвалидация кеша по тегам?

**Ответ:**
TaggedCache хранит связь `тег → кеш-файлы`. При `clearByTag('tag_name')` удаляются все файлы, помеченные этим тегом. Теги регистрируются в `startTagCache()/endTagCache()` вокруг `startDataCache()`.

Ключевое: `CIBlockElement::Update()` и ORM автоматически сбрасывают `iblock_element_*` теги. При прямом SQL — нет.

### Q3: Что будет, если CACHE_GROUPS=N, а контент зависит от группы пользователя?

**Ответ:** Все пользователи увидят кеш, созданный **первым** посетителем. Админ зайдёт — получит кеш, созданный для гостя. Это утечка данных и баг. `CACHE_GROUPS => 'Y'` добавляет `USER_GROUPS` к ключу кеша.

### Q4: Чем отличается `startDataCache` от `initCache`?

**Ответ:**
- `initCache()` — только проверяет наличие и валидность кеша, возвращает `true/false`
- `startDataCache()` — открывает буфер для записи нового кеша (следующий шаг после `initCache === false`)

### Q5: Как сбросить весь кеш модуля, но не трогать другие?

**Ответ:**
```php
$cache->cleanDir('/my_module/');     // удалить все файлы в директории
// или через тегированный кеш:
$taggedCache->clearByTag('my_module_all');
```

### Q6: Что такое `sid` в настройках memcache и зачем?

**Ответ:** `sid` — site id, префикс ключа кеша. На одном memcached-сервере может обслуживаться несколько сайтов. Без `sid` сайты будут читать чужой кеш. Классическая бага на мультисайтовости.

### Q7: Проблема "cache stampede" / "cache stampeding". Что это и как решается?

**Ответ:** "Эффект толпы" — когда кеш истёк, и 100 одновременных запросов все начинают вычислять данные заново. Решения:
- **Mutex/lock** — только первый запрос вычисляет, остальные ждут
- **Stale-while-revalidate** — отдать устаревший кеш, в фоне пересчитать
- **Probabilistic early expiration** — до истечения TTL с малой вероятностью пересчитывать

В Bitrix это решается через `Cache::createInstance()` + ручной mutex, либо через настройки composite.

### Q8: Почему прямой SQL опасен для кеша?

**Ответ:**
```php
// Плохо — кеш не сбросится:
$connection->queryExecute("UPDATE b_iblock_element SET NAME='New' WHERE ID=42");

// Хорошо — теги сбросятся автоматически:
ElementTable::update(42, ['NAME' => 'New']);
```

---

## Практические задачи

- **Задача 1:** CachedRepository — сервис с тегированным кешем и автоинвалидацией
- **Задача 2:** Компонент с кешем по группам пользователей и тегами
- **Задача 3:** Система "warming" кеша после сброса + защита от stampede

**Решения задач находятся в `examples/module-4-1/`**
