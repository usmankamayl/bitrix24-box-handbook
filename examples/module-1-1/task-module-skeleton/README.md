# Module Structure — каркас D7-модуля

## Что делает

Минимальный, но полный каркас модуля `mycompany.hello` для коробочного Bitrix (УС/Б24): структура каталогов, установщик со всеми этапами, автозагрузка классов через `lib/`, точка инициализации `include.php`.

## Бизнес-задача

Базовый шаблон для старта собственного модуля: регистрация в системе, автозагрузка D7-классов, этапы установки для БД/файлов/событий. Копируется в `/local/modules/` и служит фундаментом любого кастомного функционала.

## Структура

```
module-structure/
├── install/
│   ├── index.php        # класс mycompany_hello extends CModule
│   └── version.php      # версия и дата
├── lib/
│   └── Helper.php       # Mycompany\Hello\Helper — автозагрузка через lib/
└── include.php          # вызывается при Loader::includeModule()
```

Целевой путь в проекте: `/local/modules/mycompany.hello/`

## Этапы установки (важно для собеседования)

Порядок держим сами в `DoInstall()`:
1. `InstallFiles` — копирование компонентов, шаблонов, admin-страниц
2. `InstallDB` — таблицы, инфоблоки, HL-блоки, демо-данные
3. `InstallEvents` — регистрация обработчиков
4. `ModuleManager::registerModule` — запись в `b_module`

Регистрация — последней: если `registerModule` пройдёт, а `InstallDB()` упадёт,
получится «модуль установлен, таблиц нет». `DoInstall` проверяет
`$USER->IsAdmin()`: установка — административное действие, и кнопка в админке
защитой не является.

`DoUninstall` — в обратном порядке. На удалении БД спрашивают пользователя,
удалять ли данные.

## Автозагрузка D7

Классы в `lib/` подхватываются по namespace, совпадающему с `MODULE_ID`:
- `mycompany.hello` → `Mycompany\Hello`
- файл `lib/Helper.php` → класс `Mycompany\Hello\Helper`

После `ModuleManager::registerModule` автозагрузка работает без `registerAutoLoadClasses` — это ключевое отличие D7 от старого ядра.

## Что изменено относительно исходной версии

- `CModule` дополнен полным набором этапов: `InstallDB/InstallFiles/InstallEvents` и обратные `Un*`
- `Helper::getTimeInfo()` переведён на `Bitrix\Main\Type\DateTime` вместо `date()`
- `include.php` почищен от ссылки на несуществующий `EventHandlers`

## Вопросы для собеседования

- Какой namespace должен быть у классов в `lib/` и как он связан с `MODULE_ID`?
- В каком порядке вызываются `InstallDB`, `InstallFiles`, `InstallEvents` и `registerModule`?
- Чем `include.php` отличается от `init.php` проекта?
- Что произойдёт, если удалить модуль без `UnInstallDB`?
