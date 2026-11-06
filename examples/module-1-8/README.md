# Модуль 1.8: Result, Exception и обработка ошибок — практика

Один домен — заявки (`mycompany_request`) и склад (`mycompany_stock`). Папки
самодостаточны: каждая содержит `install.sql` для таблиц, которые ей нужны.

| Папка | Тема | Куда копировать |
|---|---|---|
| `practice-1-result-probe/` | что реально умеют `Result`/`Error`/`ErrorCollection` в твоей версии | `/local/tools/result-probe.php` |
| `practice-2-domain-service/` | доменный сервис с `Result`: правила, коды, идемпотентность | `/local/php_interface/classes/` |
| `practice-3-transaction/` | транзакция, граница `Result`/исключение, read-back | `/local/php_interface/classes/` |
| `practice-4-endpoint/` | `Result` → HTTP: маппинг кодов, отсутствие утечек | `/local/api/request-close.php` |
| `control-task/` | пять дефектов обработки ошибок, эталон и разбор | — |

Порядок прохождения: 1 → 2 → 3 → 4 → контрольное задание.

Практика 4 содержит копию домена из практики 2 (`OrderTable.php`,
`OrderStatusService.php`): папка должна запускаться автономно. В реальном
проекте домен один — копия существует только ради самодостаточности примера.

## Подготовка БД

Таблицы создаются один раз, любым клиентом к БД инсталляции:

```bash
mysql -u root -p b24 < practice-2-domain-service/install.sql
mysql -u root -p b24 < practice-3-transaction/install.sql
```

`practice-4-endpoint/install.sql` — тот же DDL, что в практике 2 (копия, если
второй раз создавать не нужно).

## Общее правило набора

Ни в одном примере нет `if ($result)`, `getErrors()[0]` без `isSuccess()`,
`catch (\Exception)` без переброса и текста исключения в ответе клиенту. Если
такое встретилось — это дефект примера, а не приём.
