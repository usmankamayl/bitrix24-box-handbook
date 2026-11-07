# Практика 4. Комплексный компонент `study:news`

Раздел `/study-news/` с ЧПУ: список, детальная страница, 404, дочерние
`bitrix:news.list` и `bitrix:news.detail`.

## Установка

```bash
cp -r component "$DOCUMENT_ROOT/local/components/study/news"
mkdir -p "$DOCUMENT_ROOT/study-news"
cp page.php "$DOCUMENT_ROOT/study-news/index.php"
```

1. `IBLOCK_ID` и `IBLOCK_TYPE` в `page.php` — свои.
2. Добавьте правило в `/urlrewrite.php` (см. `urlrewrite.php` в этой папке:
   это **одна запись**, а не весь файл) либо через админку:
   *Настройки → Настройки продукта → Сайты → Список сайтов → <сайт> →
   Правила обработки адресов*.
3. Веб-сервер должен отдавать несуществующие пути в `/bitrix/urlrewrite.php`
   (штатные `.htaccess` для Apache или `try_files` для nginx — стандартная
   поставка Bitrix это уже делает).

## Цепочка запроса

```
GET /study-news/15/
  ↓ веб-сервер: файла нет → /bitrix/urlrewrite.php
  ↓ urlrewrite: CONDITION #^/study-news/# → PATH /study-news/index.php
  ↓ index.php: header.php + $APPLICATION->IncludeComponent('study:news', ...)
  ↓ class.php: CComponentEngine::guessComponentPath('/study-news/', [...], $variables)
  ↓ $variables = ['ELEMENT_ID' => '15'], $componentPage = 'detail'
  ↓ includeComponentTemplate('detail') → templates/.default/detail.php
  ↓ bitrix:news.detail (ELEMENT_ID = 15, 4-й аргумент — родитель)
```

`guessComponentPath()` работает только с адресом. Он не знает, существует ли
элемент 15: это выясняет дочерний компонент.

## Маршруты и ожидаемое поведение

| URL | Результат |
|---|---|
| `/study-news/` | список, `list.php` |
| `/study-news/15/` | детальная, `detail.php` (если элемент активен и доступен) |
| `/study-news/15abc/` | 404: `filter_var(... FILTER_VALIDATE_INT)` не пропускает |
| `/study-news/0/` | 404: `min_range => 1` |
| `/study-news/15/extra/` | 404: ни один шаблон не подошёл |
| `/study-news/999999999/` | 404 от `bitrix:news.detail` (маршрут верный, элемента нет) |

Проверять нужно не текст, а **HTTP-статус**: `curl -I http://site/study-news/15abc/`
→ `HTTP/1.1 404`.

## Почему родитель не кешируется

Кеш родителя закрыл бы собой весь раздел: список на всех страницах
пагинации и все детальные страницы получили бы один HTML. Правильная модель —
кешировать данные там, где они получены, то есть в дочерних компонентах:
у `bitrix:news.list` ключ включает номер страницы, у `bitrix:news.detail` —
ID элемента.

Проверка: включите `CACHE_TYPE => 'Y'`, откройте список и детальную, затем
закомментируйте строку `'ELEMENT_ID' => ...` в `detail.php` — увидите, что
именно параметры задают отдельную запись кеша.

## Переопределение шаблонов

```
/local/templates/<шаблон сайта>/components/study/news/.default/
├── list.php
├── detail.php
└── bitrix/
    ├── news.list/.default/template.php
    └── news.detail/.default/template.php
```

Шаблоны дочерних компонентов ищутся внутри шаблона родителя — именно поэтому
`$APPLICATION->IncludeComponent(..., $component)` передаёт четвёртым аргументом
объект родителя. Без него дочерний компонент возьмёт свой шаблон из
`/bitrix/components/bitrix/news.list/templates/.default/`, и правка в
`.../study/news/.default/bitrix/...` молча не применится.

## Что проверять в коде

1. **`makeComponentUrlTemplates()`** — значения по умолчанию из компонента plus
   настройки из `$arParams['SEF_URL_TEMPLATES']`. Без этого компонент падает при
   вызове без SEF-настроек.
2. **`SEF_FOLDER` нормализуется**: `../`, `//`, обратные слэши. Значение приходит
   из настроек, но ошибка в настройке не должна утаскивать ссылки за пределы
   раздела.
3. **`filter_var(..., FILTER_VALIDATE_INT, min_range 1)`** вместо `(int)`: `(int)`
   превратит `15abc` в `15` и покажет чужую новость по кривому адресу.
4. **404 через `Tools::process404()`**: первый аргумент — сообщение, второй —
   определить `ERROR_404`, третий — поставить HTTP-статус, четвёртый — подменить
   страницу файлом `/404.php` и вызвать `die()`.
   `SHOW_404 = 'Y'` в примере выключен намеренно: `die()` убивает остальную
   страницу, включая подвал.
5. **Оба режима SEF**: при `SEF_MODE = 'N'` переменные берутся из query-строки
   через `initComponentVariables()`. Смешивать режимы нельзя: `guessComponentPath`
   требует, чтобы запрошенный адрес начинался с `SEF_FOLDER`.

## Осознанные упрощения

- Нет разделов внутри раздела (`#SECTION_CODE#/`, greedy-части, `resolveCallback`).
  Это следующий шаг: `$engine->addGreedyPart()` + колбэк на `CIBlockFindTools`.
- Нет RSS, поиска, постраничной навигации по разделам — только то, что нужно
  для понимания маршрутизации.
- Дочерние компоненты — штатные `bitrix:*`. Своя реализация выборки в
  комплексном компоненте — не цель темы.
