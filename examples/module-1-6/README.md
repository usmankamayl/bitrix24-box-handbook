# Практика модуля 1.6 — автозагрузка, namespace, Composer

Четыре задачи и контрольное задание. Каждая папка — самостоятельный набор
файлов, который копируется в `/local/` демо-инсталляции. Все примеры — для
**коробки** (БУС или Bitrix24 Box); на облаке `/local/` доступен, но установка
модуля через «Marketplace → Установить» и правка конфига веб-сервера
ограничены.

| Папка | Что показывает | Что копировать |
|---|---|---|
| `task-string-helper/` | регистрация класса картой `registerAutoLoadClasses` | `StringHelper.php` → `/local/php_interface/classes/Helpers/`, строку из `init.php` → в свой `init.php` |
| `task-probe/` | диагностика: стек загрузчиков, стоимость промаха, регистр ФС | `probe.php` → корень сайта |
| `task-module/` | свой модуль `mycompany.tools`: `lib/` + `include.php` + `install/` | каталог `local/modules/mycompany.tools/` |
| `task-composer/` | PSR-4 через Composer, `vendor/` закрыт | `local/composer.json`, `local/php_interface/init.php`, `local/vendor/.htaccess` |
| `control-task/` | пять дефектов инициализации | ничего, только чтение |

Порядок прохождения: 1 → 2 → 3 → 4 → контрольное задание. Задача 2 проверяет
результат задачи 1 на живой коробке, поэтому запускать её после установки
`StringHelper`.

Все файлы проверены `php -l` (PHP 8.1; код совместим с 8.3).
