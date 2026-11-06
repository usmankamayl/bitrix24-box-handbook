# Задача 2: Logger — система логирования senior-уровня

## Что реализовано

- **Уровни PSR-3**: debug, info, warning, error, critical
- **Ротация**: по размеру файла (10MB) и по дате
- **Контекст**: user_id, IP, URI, memory, request method — автоматически
- **Fail-safe**: логгер никогда не бросает исключения
- **Critical → внешние системы**: Sentry, Slack, syslog
- **Отдельный файл для ошибок**: `2024-01-15.error.log`

## Использование

```php
$logger = LoggerFactory::get('vendor.shop');

$logger->withContext(['import_id' => 42]);
$logger->info('Started', ['count' => 500]);
$logger->exception($e, 'Import failed', ['product_id' => 123]);

// Sentry для критичных
$logger->onCritical(fn($msg) => \Sentry\captureMessage($msg));
$logger->critical('DB down');
```

## Senior-фичи

1. **Не `var_dump` в проде** — `Debug::writeToFile` или свой логгер. `var_dump` в AJAX ломает JSON.

2. **Контекст обязателен** — `user_id`, `uri`, `ip` автоматически. Без них лог бесполезен при инциденте.

3. **Ротация** — логи растут. 10MB/файл + 5 ротаций = ~60MB max.

4. **Fail-safe** — `@file_put_contents` не бросит warning при full disk.

5. **Отдельный файл для ошибок** — `tail -f *.error.log` для мониторинга.

## Файлы

- `Logger.php` — полная система
