# Задача 3. Свой модуль с `lib/`

## Что показывает

Три механизма сразу:

1. **Модульный префикс** — `MyCompany\Tools\StringHelper` → `mycompany.tools/lib/tools/stringhelper.php`
   без единой строки регистрации. Первые два сегмента namespace в нижнем
   регистре, через точку, дают ID модуля; остальное — путь внутри `lib/`.
2. **Имя файла в нижнем регистре** — не стилистика, а требование: `Loader`
   приводит путь к нижнему регистру.
3. **Карта для legacy** — `CMyCompanyToolsString` без namespace находится
   только через `registerAutoLoadClasses()` в `include.php`, с путём
   относительно корня модуля.

## Установка

```bash
# из корня этого примера
cp -r local/modules/mycompany.tools /path/to/site/local/modules/
cp check.php /path/to/site/module-check.php
```

Модуль: *Marketplace → Установленные решения* → «MyCompany: инструменты» →
«Установить». Файл `install/index.php` зарегистрирует модуль через
`ModuleManager::registerModule()`.

## Что сдать

- `class_exists()` **до** `includeModule` — `true` (файл найден по пути).
- `isModuleInstalled()` — `false` до установки, `true` после.
- `Loader::includeModule()` — `false` до установки, `true` после; повторный
  вызов тоже `true`, но `include.php` второй раз не выполняется.
- Константа `MYCOMPANY_TOOLS_INCLUDED` появляется только после успешного
  `includeModule` — доказательство, что `include.php` выполнился.

## Ловушки

- Модуль, скопированный без `install/`, не появится в списке: установщик —
  часть контракта.
- `DoUninstall()` в примере не удаляет файлы — это нормально для учебного
  модуля; реальный модуль удаляет свои таблицы и настройки.
- Если класс не находится при существующем файле — проверьте регистр **каждого**
  сегмента пути: `lib/Tools/StringHelper.php` на Linux = не найден.
- В `include.php` не должно быть тяжёлой работы: он выполняется при каждом
  `includeModule`, то есть практически на каждом запросе сайта.
