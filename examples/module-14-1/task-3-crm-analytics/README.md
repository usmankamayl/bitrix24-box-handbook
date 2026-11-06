# Задача 3: Модуль аналитики CRM

## Описание

Модуль `acme.analytics` для сбора и анализа данных CRM в Bitrix24.

## Функциональность

- ✅ Сбор статистики по сделкам и лидам
- ✅ Воронки продаж с конверсией
- ✅ Аналитика по менеджерам
- ✅ Прогнозирование продаж
- ✅ Дашборд с графиками
- ✅ Экспорт отчетов в Excel
- ✅ Обработчики событий CRM для отслеживания изменений
- ✅ Административный интерфейс для просмотра аналитики

## Структура модуля

```
/local/modules/acme.analytics/
├── install/
│   ├── index.php              # Установщик
│   ├── version.php
│   └── admin/
│       └── menu.php           # Пункт в админ-меню
├── lib/
│   ├── Analytics/
│   │   ├── DealAnalytics.php      # Аналитика сделок
│   │   ├── LeadAnalytics.php      # Аналитика лидов
│   │   ├── UserAnalytics.php      # Аналитика менеджеров
│   │   └── FunnelAnalytics.php    # Воронки продаж
│   ├── Export/
│   │   └── ExcelExporter.php      # Экспорт в Excel
│   ├── EventHandlers.php          # Обработчики событий CRM
│   └── Model/
│       └── AnalyticsTable.php     # Хранение агрегированных данных
├── admin/
│   └── dashboard.php              # Административный дашборд
└── options.php
```

## Использование

### 1. Статистика по сделкам

```php
<?php
use Acme\Analytics\Analytics\DealAnalytics;

$analytics = new DealAnalytics();

// Статистика за период
$stats = $analytics->getStats('2024-01-01', '2024-01-31');

print_r($stats);
/*
[
    'total_deals' => 150,
    'total_amount' => 5000000,
    'won_deals' => 45,
    'won_amount' => 2000000,
    'lost_deals' => 30,
    'conversion' => 30.0,
    'avg_deal_size' => 33333.33,
]
*/
```

### 2. Воронка продаж

```php
<?php
use Acme\Analytics\Analytics\FunnelAnalytics;

$funnel = new FunnelAnalytics();

// Получить воронку с конверсией
$data = $funnel->getFunnelData('C1:NEW'); // ID воронки

foreach ($data['stages'] as $stage) {
    echo sprintf(
        "%s: %d сделок (%s руб.) - конверсия: %.1f%%\n",
        $stage['name'],
        $stage['count'],
        number_format($stage['amount'], 0, '.', ' '),
        $stage['conversion']
    );
}
```

### 3. Рейтинг менеджеров

```php
<?php
use Acme\Analytics\Analytics\UserAnalytics;

$userAnalytics = new UserAnalytics();

// Топ менеджеров за месяц
$top = $userAnalytics->getTopManagers('2024-01-01', '2024-01-31', 10);

foreach ($top as $position => $manager) {
    echo sprintf(
        "#%d. %s - %d сделок на %s руб.\n",
        $position + 1,
        $manager['name'],
        $manager['deals_count'],
        number_format($manager['amount'], 0, '.', ' ')
    );
}
```

### 4. Экспорт в Excel

```php
<?php
use Acme\Analytics\Export\ExcelExporter;

$exporter = new ExcelExporter();

// Экспорт отчета
$filePath = $exporter->exportDeals([
    'date_from' => '2024-01-01',
    'date_to' => '2024-01-31',
]);

// Отдать файл пользователю
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="report.xlsx"');
readfile($filePath);
```

### 5. Дашборд аналитики

Доступен через: **CRM → Аналитика** (в административном меню)

Показывает:
- Воронки продаж с конверсией
- Динамику по дням/месяцам
- Рейтинг менеджеров
- Прогноз продаж
- Графики (Chart.js)

## Обработчики событий

Модуль автоматически отслеживает изменения в CRM:

```php
<?php
// При создании/изменении сделки автоматически обновляется статистика

// При перемещении по стадиям отслеживается воронка

// При закрытии сделки обновляется конверсия
```

## Примеры использования

### Пример 1: Отчет для руководителя

```php
<?php
use Acme\Analytics\Analytics\DealAnalytics;
use Acme\Analytics\Analytics\UserAnalytics;

$dealAnalytics = new DealAnalytics();
$userAnalytics = new UserAnalytics();

$dateFrom = date('Y-m-01'); // начало месяца
$dateTo = date('Y-m-d');    // сегодня

// Общая статистика
$stats = $dealAnalytics->getStats($dateFrom, $dateTo);

echo "=== Отчет за текущий месяц ===\n\n";

echo "Всего сделок: {$stats['total_deals']}\n";
echo "Сумма сделок: " . number_format($stats['total_amount'], 0, '.', ' ') . " руб.\n";
echo "Выигранных: {$stats['won_deals']} ({$stats['conversion']}%)\n";
echo "Проигранных: {$stats['lost_deals']}\n";
echo "Средний чек: " . number_format($stats['avg_deal_size'], 0, '.', ' ') . " руб.\n\n";

// Топ-5 менеджеров
echo "=== Топ-5 менеджеров ===\n\n";

$top = $userAnalytics->getTopManagers($dateFrom, $dateTo, 5);

foreach ($top as $i => $manager) {
    echo sprintf(
        "%d. %s - %d сделок на %s руб.\n",
        $i + 1,
        $manager['name'],
        $manager['deals_count'],
        number_format($manager['amount'], 0, '.', ' ')
    );
}
```

### Пример 2: Прогноз выручки

```php
<?php
use Acme\Analytics\Analytics\DealAnalytics;

$analytics = new DealAnalytics();

// Прогноз на основе текущих сделок в работе
$forecast = $analytics->getForecast();

echo "=== Прогноз выручки на месяц ===\n\n";

echo "Гарантированная выручка: " . 
     number_format($forecast['guaranteed'], 0, '.', ' ') . " руб.\n";
     
echo "Ожидаемая выручка: " . 
     number_format($forecast['expected'], 0, '.', ' ') . " руб.\n";
     
echo "Максимальная выручка: " . 
     number_format($forecast['maximum'], 0, '.', ' ') . " руб.\n";
```

### Пример 3: Компонент для сайта

```php
<?php
// /local/components/acme/analytics.widget/component.php

use Acme\Analytics\Analytics\DealAnalytics;

$analytics = new DealAnalytics();

$arResult['STATS'] = $analytics->getStats(
    date('Y-m-01'),
    date('Y-m-d')
);

$arResult['CHART_DATA'] = $analytics->getChartData(
    date('Y-m-01'),
    date('Y-m-d'),
    'day'
);

$this->includeComponentTemplate();
```

## Административный интерфейс

### Дашборд аналитики

Файл: `/bitrix/admin/acme_analytics_dashboard.php`

Содержит:
- Графики динамики продаж
- Воронки с конверсией
- Таблицы рейтингов
- Фильтры по периодам
- Кнопки экспорта

### Добавление в меню

В `install/admin/menu.php`:

```php
<?php
return [
    [
        'parent_menu' => 'global_menu_crm',
        'section' => 'acme_analytics',
        'sort' => 1000,
        'text' => 'Аналитика',
        'title' => 'Аналитика CRM',
        'url' => 'acme_analytics_dashboard.php',
        'icon' => 'acme_analytics_menu_icon',
    ],
];
```

## Настройки модуля

- **Включить автоматический сбор** - собирать данные по событиям
- **Период агрегации** - как часто пересчитывать статистику
- **Глубина истории (дней)** - сколько хранить детальную статистику

## Расширение модуля

### Добавление нового типа аналитики

1. Создайте класс в `lib/Analytics/`:

```php
<?php
namespace Acme\Analytics\Analytics;

class LeadAnalytics extends BaseAnalytics
{
    public function getStats($dateFrom, $dateTo)
    {
        // Логика аналитики лидов
    }
}
```

2. Используйте:

```php
$leadAnalytics = new \Acme\Analytics\Analytics\LeadAnalytics();
$stats = $leadAnalytics->getStats('2024-01-01', '2024-01-31');
```

## Заключение

Модуль `acme.analytics` предоставляет:
- ✅ Полную статистику по CRM
- ✅ Визуализацию воронок
- ✅ Рейтинги менеджеров
- ✅ Прогнозирование
- ✅ Экспорт отчетов
- ✅ Административный интерфейс

Идеально для руководителей отделов продаж и аналитиков!
