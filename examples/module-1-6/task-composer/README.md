# Задача 4. Composer PSR-4

## Что показывает

- `psr-4` находит класс по имени файла **без изменения регистра** — то, чего
  не умеет `Loader::registerNamespace()`.
- Два загрузчика сосуществуют: `Loader` (ядро, `Bitrix\*`) и
  `Composer\Autoload\ClassLoader` (`MyCompany\*`).
- `vendor/` и `composer.json` под документ-рутом требуют запрета на отдачу.

## Установка

```bash
# 1. Скопировать в сайт
cp local/composer.json          /path/to/site/local/
cp local/.htaccess              /path/to/site/local/
cp local/vendor/.htaccess       /path/to/site/local/vendor/     # каталог создать
cp -r local/php_interface/classes/Pricing /path/to/site/local/php_interface/classes/
# 2. Строку подключения из local/php_interface/init.php добавить в свой init.php
# 3. Поставить зависимости (сейчас их нет — создастся только автозагрузчик)
cd /path/to/site/local && composer install
# 4. Проверка
cp check.php /path/to/site/composer-check.php
```

Composer должен быть установлен на машине; на коробке обычно кладут
`composer.phar` в `/local/` (в веб-корень его лучше не класть — см. `.htaccess`).

## Проверки, которые надо сдать

```bash
curl -I https://site.local/local/composer.json
curl -I https://site.local/local/vendor/autoload.php
# ожидаемо: 403 или 404
```

```bash
cd /path/to/site/local && composer dump-autoload -o
# затем добавить новый класс и убедиться, что он не находится до пересборки
cd /path/to/site/local && composer dump-autoload
```

## nginx

`.htaccess` nginx не читает. В конфиг сайта:

```nginx
location ~ ^/local/(vendor|composer\.(json|lock|phar)) {
    deny all;
    return 404;
}
```

## Замечания по продакшену

- `composer.lock` — в репозиторий. На сервере:
  `composer install --no-dev --optimize-autoloader`.
- Локально PHP 8.3, на коробке 8.1 — `composer install` упадёт на
  `requires php`. Лечится секцией:

  ```json
  "config": { "platform": { "php": "8.1.9" } }
  ```

  Это не «понижает» PHP, а заставляет Composer разрешать зависимости под
  целевую версию.
- Не требовать пакеты, которые ядро уже несёт: `psr/log`, `psr/container`,
  `psr/http-message`, `psr/http-client`, `php-http/promise`,
  `phpmailer/phpmailer`, `geoip2/geoip2`, `maxmind-db/reader`,
  `nikic/php-parser`, `simshaun/recurr`. `Loader` регистрирует эти namespace
  раньше Composer-а — ваша копия не загрузится.
