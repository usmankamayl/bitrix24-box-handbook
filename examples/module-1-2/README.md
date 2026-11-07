# Модуль 1.2: компоненты — практика

Четыре компонента и контрольное задание. Все папки самодостаточны: внутри
`component/` лежит готовая структура для `/local/components/study/<имя>/`,
рядом — страница и README с порядком проверки.

| Папка | Что внутри | Куда копировать |
|---|---|---|
| `practice-1-news-list/` | простой классовый компонент: параметры, выборка с правами, пагинация, кеш с тегом, два шаблона, `result_modifier.php` | `/local/components/study/news.list/` |
| `practice-2-news-filter/` | тот же список + GET-фильтр, внешний `FILTER_NAME`, `CACHE_FILTER`, `component_epilog.php` | `/local/components/study/news.filtered/` |
| `practice-3-feedback-form/` | POST-форма: sessid, honeypot, подписанная метка времени, throttle, капча, `Event::send`, AJAX | `/local/components/study/feedback/` |
| `practice-4-complex-news/` | комплексный компонент: ЧПУ, `SEF_URL_TEMPLATES`, 404, дочерние `bitrix:news.list` / `news.detail` | `/local/components/study/news/` |
| `control-task/` | пять дефектов компонента, эталон и разбор | — |

Порядок: 1 → 2 → 3 → 4 → контрольное задание.

## Общие правила набора

- Компонент без `class.php` и `component.php` одновременно: если есть
  `class.php`, `component.php` не подключается. Пустая заглушка
  `component.php` рядом с классом — дефект структуры, а не «правильный шаблон».
- Тексты — через `Loc::getMessage()`, файлы `lang/ru/class.php`,
  `lang/ru/.parameters.php`, `templates/<tpl>/lang/ru/template.php`.
- Экранирование — в шаблоне, `htmlspecialcharsbx()`. Компонент отдаёт сырые
  значения.
- `registerCacheTag()` — всегда, когда кеш зависит от инфоблока.
- В формах — `bitrix_sessid_post()` и `check_bitrix_sessid()`, без исключений.

## Проверка синтаксиса

```bash
for f in $(find . -name '*.php'); do php -l "$f" || echo "FAIL $f"; done
```

Синтаксис ничего не говорит о поведении: компоненты нужно ставить на живую
инсталляцию и проверять по таблицам «Эксперименты» в каждом README.
