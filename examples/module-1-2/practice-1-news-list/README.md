# Практика 1. Простой компонент `study:news.list`

Классовый компонент списка новостей: параметры, выборка с правами, пагинация,
кеш с тегом инфоблока, два шаблона, `result_modifier.php`, языковые файлы.

## Установка

```bash
# 1. Компонент — в /local/components/study/news.list/
cp -r component "$DOCUMENT_ROOT/local/components/study/news.list"

# 2. Страница
cp page.php "$DOCUMENT_ROOT/study-news/index.php"
```

`IBLOCK_ID` в `page.php` замените на свой. Инфоблок должен быть активен,
элементы — активны и иметь заполненный `DETAIL_PAGE_URL` (шаблон URL задаётся
в настройках инфоблока).

Проверка: `/study-news/` показывает карточки; `/study-news/?PAGEN_1=2` —
вторую страницу (при `NEWS_COUNT` меньше числа элементов).

## Структура

```
component/
├── .description.php              метаданные для визуального редактора
├── .parameters.php               интерфейс настроек
├── class.php                     StudyNewsListComponent extends CBitrixComponent
├── lang/ru/class.php             тексты ошибок
├── lang/ru/.parameters.php       подписи настроек
└── templates/
    ├── .default/
    │   ├── template.php          карточки
    │   ├── result_modifier.php   группировка по годам
    │   ├── style.css             подключается ядром автоматически
    │   └── lang/ru/template.php
    └── compact/                  второй шаблон: дата + ссылка
        ├── template.php
        ├── style.css
        └── lang/ru/template.php
```

`component.php` нет намеренно: при наличии `class.php` ядро берёт класс и
`component.php` не подключает вообще (см. `CBitrixComponent::__getClassForPath()`
и `initComponent()`). Пустой `component.php` рядом с `class.php` — мёртвый файл,
который вводит в заблуждение.

Имя класса — `StudyNewsListComponent`: `study:news.list` → CamelCase + `Component`.
Ядро технически находит любой наследник `CBitrixComponent`, объявленный в
`class.php`, но соглашение нужно, чтобы `includeComponentClass('study:news.list')`
и наследование шаблонов работали предсказуемо.

## Что проверять при разборе

1. **Ключ кеша.** `$arParams` попадает в ключ целиком (кроме ключей с `~`),
   плюс сайт, язык, шаблон сайта и шаблон компонента. Руками в ключ добавлены
   только группы пользователя (`CACHE_GROUPS`) и номер страницы
   (`CDBResult::GetNavParams()`). Без второго параметра страница 2 отдавала бы
   HTML страницы 1.
2. **Пустой список кешируется.** `abortResultCache()` вызывается только при
   исключении и при ненайденном инфоблоке.
3. **`$this->arResult` после `includeComponentTemplate()`** урезан до
   `IBLOCK_ID`, `IBLOCK_NAME`, `ITEMS_COUNT`, `NAV_STRING`. Поэтому
   `SetTitle()` вызывается вне блока `if (startResultCache())` и берёт
   `IBLOCK_NAME` — иначе на попадании в кеш заголовок пропал бы.
4. **Экранирование — только в шаблоне.** Компонент отдаёт сырые значения
   (`Fetch()`, не `GetNext()`), шаблон вызывает `htmlspecialcharsbx()`.
   `PREVIEW_TEXT` (сырой HTML) в шаблоне не выводится; выводится
   `PREVIEW_TEXT_PLAIN`.
5. **Права.** `CHECK_PERMISSIONS => Y`, `MIN_PERMISSION => R` — фильтр учитывает
   права текущего пользователя. Это не отменяет `CACHE_GROUPS`.
6. **Тег `iblock_id_N`.** `CIBlockResult::Fetch()` регистрирует его сам, когда
   прочитана хотя бы одна строка. Явный `RegisterTag()` в компоненте страхует
   пустой список и данные, полученные не через `CIBlockElement`. Оба варианта
   верны; отсутствие тега при выборке через ORM или свой SQL — ошибка.

## Эксперименты (по одному, с выводом `AddMessage2Log` в `loadItems`)

| Действие | Ожидаемое поведение |
|---|---|
| Открыть страницу дважды | на втором заходе `loadItems()` не выполняется |
| Изменить элемент в админке | кеш сброшен тегом `iblock_id_N` |
| Деактивировать все элементы | пустая страница, ответ закеширован |
| Создать первый элемент | кеш пустого списка сброшен тегом |
| `CACHE_TYPE = N` | выборка выполняется на каждом запросе |
| `CACHE_GROUPS = N` | один кеш для всех групп — только если данные не зависят от прав |
| Уменьшить `NEWS_COUNT` | новый ключ кеша (параметр входит в него) |

## Осознанные упрощения

- Нет `SEF`/ЧПУ внутри компонента: `DETAIL_PAGE_URL` берётся из настроек
  инфоблока. Комплексный компонент с ЧПУ — практика 4.
- Нет свойств элементов: только поля. Свойства — тема каталога (модуль 3).
- `PREVIEW_PICTURE` отдаётся исходным размером. Ресайз (`CFile::ResizeImageGet`)
  добавляется, когда появляется реальная ширина макета.
