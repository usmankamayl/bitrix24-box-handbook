#!/usr/bin/env bash
#
# Задача 6: чек-лист «первый день на проекте» в виде скрипта.
#
# Скрипт ТОЛЬКО ЧИТАЕТ: ничего не пишет в проект, не меняет настройки,
# не ходит по HTTP. Запускать на сервере (или локально) с shell-доступом.
#
#   bash first-day.sh /path/to/document_root
#
# Каждый блок — один пункт чек-листа. Если инструмент недоступен (нет mysql,
# нет git), блок печатает SQL/команду для ручного запуска, а не падает.

set -u

ROOT="${1:-}"

if [ -z "$ROOT" ] || [ ! -d "$ROOT/bitrix" ]; then
    echo "usage: $0 /path/to/document_root" >&2
    exit 2
fi

SETTINGS="$ROOT/bitrix/.settings.php"
VERSION_FILE="$ROOT/bitrix/modules/main/classes/general/version.php"

hr() { printf '\n== %s\n' "$1"; }

# Параметры БД из .settings.php: host, database, login, password — по строке.
db_conf() {
    php -r '
        $c = @include $argv[1];
        $d = (is_array($c) && isset($c["connections"]["default"])) ? $c["connections"]["default"] : [];
        foreach (["host", "database", "login", "password"] as $k) {
            echo (string)($d[$k] ?? ""), PHP_EOL;
        }
    ' "$SETTINGS" 2>/dev/null
}

DB="$(db_conf)"
DB_HOST="$(echo "$DB" | sed -n '1p')"
DB_NAME="$(echo "$DB" | sed -n '2p')"
DB_USER="$(echo "$DB" | sed -n '3p')"
DB_PASS="$(echo "$DB" | sed -n '4p')"

sql() {
    # sql "<query>" — выполняет запрос, если есть клиент mysql
    if command -v mysql >/dev/null 2>&1 && [ -n "$DB_NAME" ]; then
        MYSQL_PWD="$DB_PASS" mysql -h "${DB_HOST:-localhost}" -u "$DB_USER" -D "$DB_NAME" \
            -N -B -e "$1" 2>/dev/null
    else
        echo "  [нет клиента mysql] выполните вручную: $1"
    fi
}

# --- 1. Версия ядра и установленные модули ---------------------------------
hr '1. Версия ядра и модулей'
if [ -f "$VERSION_FILE" ]; then
    grep -o 'define("SM_VERSION","[^"]*")\|define("SM_VERSION", "[^"]*")' "$VERSION_FILE" 2>/dev/null \
        || grep -n 'SM_VERSION' "$VERSION_FILE" | head -3
else
    echo "  файл версии не найден: $VERSION_FILE"
fi
echo '  модули (ID, VERSION):'
sql 'select ID, VERSION from b_module order by ID' | sed 's/^/    /'

# mtime файла: GNU stat, затем BSD stat, затем «?»
mtime() {
    stat -c '%y' "$1" 2>/dev/null | cut -c1-16 \
        || stat -f '%Sm' -t '%Y-%m-%d %H:%M' "$1" 2>/dev/null \
        || echo '?'
}

# --- 2. Какой init.php фактически работает ---------------------------------
hr '2. init.php'
for f in "$ROOT/local/php_interface/init.php" "$ROOT/bitrix/php_interface/init.php"; do
    if [ -f "$f" ]; then
        echo "  есть: ${f#$ROOT/} ($(wc -l < "$f") строк, изменён $(mtime "$f"))"
    else
        echo "  нет:  ${f#$ROOT/}"
    fi
done
if [ -f "$ROOT/local/php_interface/init.php" ]; then
    echo '  -> приоритет у /local: версия из /bitrix (если есть) НЕ выполняется'
fi
# Обработчики, зарегистрированные прямо в init.php (D7-путь не виден в БД).
if [ -f "$ROOT/local/php_interface/init.php" ]; then
    echo '  addEventHandler в init.php:'
    grep -n 'addEventHandler\|AddEventHandler' "$ROOT/local/php_interface/init.php" | head -10 | sed 's/^/    /'
fi

# --- 3. .settings.php: кеш и обработка ошибок ------------------------------
hr '3. .settings.php: кеш и ошибки'
if [ -f "$SETTINGS" ]; then
    php -r '
        $c = include $argv[1];
        $cache = $c["cache"] ?? [];
        $exc = $c["exception_handling"] ?? [];
        echo "  cache.type        = ", ($cache["type"] ?? "(нет)"), PHP_EOL;
        echo "  cache.sid         = ", (string)($cache["sid"] ?? "(нет)"), PHP_EOL;
        echo "  exception.debug   = ", !empty($exc["debug"]) ? "true" : "false", PHP_EOL;
        echo "  exception.log     = ", (string)($exc["log"]["file"] ?? "(нет)"), PHP_EOL;
        echo "  session.mode      = ", (string)($c["session"]["mode"] ?? "(нет)"), PHP_EOL;
        echo "  readonly (защита от записи из админки) = ",
            !empty($c["readonly"]) ? "true" : "false", PHP_EOL;
        echo "  есть .settings_extra.php: ",
            file_exists(dirname($argv[1]) . "/.settings_extra.php") ? "да" : "нет", PHP_EOL;
    ' "$SETTINGS" 2>/dev/null
else
    echo "  .settings.php не найден — это legacy-установка (dbconn.php) или другой корень"
fi

# --- 4. Свои модули --------------------------------------------------------
hr '4. /local/modules'
if [ -d "$ROOT/local/modules" ]; then
    for m in "$ROOT"/local/modules/*/; do
        [ -d "$m" ] || continue
        v="-"
        [ -f "$m/install/version.php" ] && v="$(php -r '$a = include $argv[1]; echo $a["VERSION"] ?? "?";' "${m}install/version.php" 2>/dev/null)"
        installed="$(sql "select count(*) from b_module where ID='$(basename "$m")'")"
        echo "  $(basename "$m")  version=$v  в b_module=${installed:-?}"
    done
else
    echo '  /local/modules нет (или пуст)'
fi

# --- 5. Что переопределено -------------------------------------------------
hr '5. Переопределения в /local'
for d in components templates js css; do
    if [ -d "$ROOT/local/$d" ]; then
        echo "  local/$d: $(find "$ROOT/local/$d" -maxdepth 2 -mindepth 1 | wc -l) объектов верхнего уровня"
    fi
done

# --- 6. Правили ли ядро ----------------------------------------------------
hr '6. Изменения в /bitrix'
if [ -d "$ROOT/.git" ]; then
    echo '  git есть, изменённые файлы в bitrix/:'
    git -C "$ROOT" status --porcelain -- bitrix/ 2>/dev/null | head -20 | sed 's/^/    /'
    [ -z "$(git -C "$ROOT" status --porcelain -- bitrix/ 2>/dev/null)" ] && echo '    (чисто)'
else
    echo '  репозитория нет — эвристика по mtime (файлы новее version.php):'
    if [ -f "$VERSION_FILE" ]; then
        find "$ROOT/bitrix" -type f -name '*.php' -newer "$VERSION_FILE" 2>/dev/null \
            | grep -v '/cache/\|/managed_cache/\|/stack_cache/\|/updates/' | head -20 | sed 's/^/    /'
        echo '    ВНИМАНИЕ: штатное обновление тоже обновляет mtime. Это сигнал «сравнить с дистрибутивом»,'
        echo '             а не приговор.'
    fi
fi

# --- 7. Обработчики событий ------------------------------------------------
hr '7. Обработчики (b_module_to_module)'
sql "select FROM_MODULE_ID, MESSAGE_ID, TO_MODULE_ID, coalesce(TO_CLASS,''), coalesce(TO_METHOD,'') from b_module_to_module order by MESSAGE_ID limit 40" | sed 's/^/  /'

# --- 8. Кеш на диске -------------------------------------------------------
hr '8. Кеш'
for d in cache managed_cache stack_cache; do
    if [ -d "$ROOT/bitrix/$d" ]; then
        echo "  bitrix/$d: $(du -sh "$ROOT/bitrix/$d" 2>/dev/null | cut -f1)"
    fi
done
grep -rl 'COMPOSITE' "$ROOT/.htaccess" "$ROOT/local/.htaccess" 2>/dev/null | sed 's/^/  композит упомянут в: /'

# --- 9. Агенты: кто их гоняет ---------------------------------------------
hr '9. Агенты и cron'
crontab -l 2>/dev/null | grep -i 'bitrix\|cron_events\|agents' | sed 's/^/  /' || echo '  crontab недоступен'
sql "select NAME, DATE_EXEC from b_agent where ACTIVE='Y' order by DATE_EXEC asc limit 5" | sed 's/^/  /'
echo '  Если DATE_EXEC у всех в прошлом — агенты не крутятся. Это cron, а не код.'

# --- 10. Права и группы ----------------------------------------------------
hr '10. Кто администратор'
sql "select u.ID, u.LOGIN, ug.GROUP_ID from b_user u left join b_user_group ug on ug.USER_ID=u.ID where ug.GROUP_ID=1 limit 10" | sed 's/^/  /'

printf '\nГотово. Скрипт ничего не менял.\n'
