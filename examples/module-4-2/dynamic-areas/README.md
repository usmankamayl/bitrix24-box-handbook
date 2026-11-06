# Задача 1: Dynamic Areas в шаблоне сайта

## Что показывает

Senior-подход к разделению страницы на статику и динамику:

**Статика (в HTML-кеш):**
- Шапка/футер
- Меню
- Основной контент
- SEO-теги (title, meta, h1)

**Динамика (frame):**
- Имя пользователя
- Корзина (количество, сумма)
- Кнопки "Войти/Выйти"

**Deferred JS (не frame):**
- Рекомендации — загружаются после composite

## Senior-фичи

1. **Placeholder в frame** — `$frame->setAnimation(true)` показывает спиннер до загрузки динамики. Без него пользователь видит пустое место.

2. **SEO в статике** — title, meta, h1, хлебные крошки НЕ в frame. Иначе поисковики увидят placeholder.

3. **Меню через компонент** — `bitrix:menu` сам кешируется внутри composite (не frame).

4. **`onCompositeReady`** — правильное событие для загрузки JS-зависимого контента. Не `$(document).ready` — composite грузится асинхронно.

## Анти-паттерны в коде

```php
// ❌ ПЛОХО — персонализированный контент в статике
<div>Привет, <?= $USER->GetFullName() ?></div>
// Все увидят имя первого посетителя

// ❌ ПЛОХО — тяжёлая логика внутри frame
$frame->startDynamicArea();
$data = heavyCalculation(); // Выполнится на каждый AJAX!
$frame->finishDynamicArea();

// ✅ ХОРОШО — данные кешируем отдельно, frame только выводит
$data = $cache->remember('heavy', fn() => heavyCalculation());
$frame->startDynamicArea();
echo $data;
$frame->finishDynamicArea();
```

## Файлы

- `template.php` — шаблон сайта с правильными frame
