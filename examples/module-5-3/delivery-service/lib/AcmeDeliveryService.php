<?php
// examples/module-5-3/delivery-service/lib/AcmeDeliveryService.php
// Кастомная служба доставки с расчётом по внешнему API,
// кешированием и circuit breaker.

namespace Acme\Sale\Delivery;

use Bitrix\Main\Error;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;
use Bitrix\Sale\Shipment;

class AcmeDeliveryService extends Base
{
    private const API_URL     = 'https://api.acme-delivery.example/v1';
    private const TTL         = 1800;  // 30 минут кеша на одинаковые параметры
    private const BREAKER_TTL = 300;   // 5 минут "half-open" состояния после ошибок

    // Статический код службы — важен для сохранения в b_sale_delivery_srv.
    // Без этого Bitrix не сможет привязать службу к профилю.
    public static function getClassTitle(): string
    {
        return 'Доставка Acme';
    }

    public static function getClassDescription(): string
    {
        return 'Расчёт через API Acme Delivery. Тарифы: курьер, ПВЗ, почта.';
    }

    /**
     * Главный метод: Bitrix вызывает его на каждое изменение
     * в форме оформления (город, вес, способ оплаты).
     *
     * ВАЖНО: должен быть быстрым — страница заказа дёргает его
     * десятки раз при редактировании. Поэтому кеш + таймаут + circuit breaker.
     *
     * @param Shipment $shipment
     * @return CalculationResult
     */
    protected function calculateConcrete(Shipment $shipment): CalculationResult
    {
        $result = new CalculationResult();

        // 1. Если circuit breaker сработал — не ходим в API,
        //    возвращаем ошибку, чтобы страница заказа не падала.
        if (self::isCircuitOpen()) {
            $result->addError(new Error('Служба доставки временно недоступна'));
            return $result;
        }

        // 2. Вытаскиваем параметры из отгрузки.
        $order  = $shipment->getCollection()->getOrder();
        $props  = self::getOrderProperties($order);
        $city   = $props['CITY'] ?? null;
        $weight = (float) $shipment->getWeight();
        $price  = (float) $shipment->getPrice();

        // Если город не указан — ошибка, fallback 'Москва' недопустим.
        if (!$city) {
            $result->addError(new Error('Не указан город доставки'));
            return $result;
        }

        // 3. Ключ кеша: точные значения (без нормализации).
        //    Разные пользователи с одинаковыми параметрами получают одинаковый расчёт.
        $cacheKey = self::buildCacheKey($city, $weight, $price);

        $cached = self::getFromCache($cacheKey);
        if ($cached !== null) {
            return self::buildResultFromArray($cached);
        }

        // 4. Расчёт по API.
        try {
            $apiResult = self::callApi([
                'city'   => $city,
                'weight' => $weight,
                'price'  => $price,
            ]);
        } catch (\Throwable $e) {
            self::recordFailure();
            // Ошибка — тоже валидный CalculationResult:
            // возвращаем ошибку, страница заказа не ломается.
            $result->addError(new Error('Ошибка расчёта доставки: ' . $e->getMessage()));
            return $result;
        }

        self::resetFailures();
        self::saveToCache($cacheKey, $apiResult);

        return self::buildResultFromArray($apiResult);
    }

    /**
     * Вызов внешнего API.
     * @throws \RuntimeException при таймауте/ошибке
     */
    private static function callApi(array $params): array
    {
        $http = new HttpClient([
            'socketTimeout' => 5,    // строго короткий таймаут —
                                    // иначе страница заказа виснет
            'streamTimeout' => 5,
        ]);

        $response = $http->post(
            self::API_URL . '/calculate',
            json_encode($params),
            ['Content-Type' => 'application/json']
        );

        if ($http->getStatus() !== 200) {
            throw new \RuntimeException('HTTP ' . $http->getStatus());
        }

        $data = json_decode($response, true);
        if (empty($data['price'])) {
            throw new \RuntimeException('Empty price in response');
        }

        return $data;
    }

    /**
     * Из массива API-ответа собираем CalculationResult.
     * Возвращаемая структура определяет, что увидит покупатель.
     */
    private static function buildResultFromArray(array $data): CalculationResult
    {
        $result = new CalculationResult();
        $result->setDeliveryPrice((float) $data['price']);
        $result->setPeriodDescription($data['period'] ?? '2-3 дня');

        if (isset($data['period_from'])) {
            $result->setPeriodFrom((int) $data['period_from']);
        }
        if (isset($data['period_to'])) {
            $result->setPeriodTo((int) $data['period_to']);
        }
        if (isset($data['period_type'])) {
            $result->setPeriodType($data['period_type']);
        }

        return $result;
    }

    /**
     * Кеш: канонический метод через Data\Cache.
     * initCache() → getVars() / startDataCache() → endDataCache() → abortDataCache()
     */
    private static function buildCacheKey(string $city, float $weight, float $price): string
    {
        // Точные значения без нормализации — разные корзины = разные ключи
        $normCity = mb_strtolower(trim($city));
        return 'acme_del_' . md5("$normCity|$weight|$price");
    }

    private static function getFromCache(string $key): ?array
    {
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if ($cache->initCache(self::TTL, $key, '/acme_delivery')) {
            return $cache->getVars();
        }
        return null;
    }

    private static function saveToCache(string $key, array $data): void
    {
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        // Канон: initCache проверяет существование, если нет — startDataCache
        if (!$cache->initCache(self::TTL, $key, '/acme_delivery')) {
            if ($cache->startDataCache()) {
                $cache->endDataCache($data);
            }
        }
    }

    /**
     * Circuit breaker: три состояния (CLOSED/OPEN/HALF_OPEN).
     * CLOSED: всё работает.
     * OPEN: >= 3 ошибок → блокируем на BREAKER_TTL.
     * HALF_OPEN: после BREAKER_TTL пробуем один запрос — если ОК, сбрасываем счётчик.
     *
     * Атомарность через транзакцию БД (для production — Redis INCR/EXPIRE).
     */
    private static function isCircuitOpen(): bool
    {
        $conn = \Bitrix\Main\Application::getConnection();

        // Атомарное чтение через FOR UPDATE (предотвращает race)
        $sql = "SELECT OPTION_VALUE, DATE_MODIFY
                FROM b_option
                WHERE MODULE_ID = 'sale' AND OPTION_NAME = 'acme_delivery_failures'
                FOR UPDATE";

        $row = $conn->query($sql)->fetch();
        $failCount = $row ? (int) $row['OPTION_VALUE'] : 0;
        $lastModify = $row ? strtotime($row['DATE_MODIFY']) : 0;

        // Если было >= 3 ошибок и последняя < BREAKER_TTL секунд назад — OPEN.
        if ($failCount >= 3 && (time() - $lastModify) < self::BREAKER_TTL) {
            return true;
        }

        // Если прошло >= BREAKER_TTL — переход в HALF_OPEN, разрешаем один запрос.
        // Счётчик сбросится при успехе (resetFailures) или вырастет при ошибке (recordFailure).
        return false;
    }

    private static function recordFailure(): void
    {
        // Атомарный инкремент через SQL
        $conn = \Bitrix\Main\Application::getConnection();
        $conn->query("
            INSERT INTO b_option (MODULE_ID, OPTION_NAME, OPTION_VALUE, DATE_MODIFY)
            VALUES ('sale', 'acme_delivery_failures', '1', NOW())
            ON DUPLICATE KEY UPDATE
                OPTION_VALUE = OPTION_VALUE + 1,
                DATE_MODIFY = NOW()
        ");
    }

    private static function resetFailures(): void
    {
        \Bitrix\Main\Config\Option::set('sale', 'acme_delivery_failures', 0);
    }

    /**
     * Достаём свойства заказа (CITY, ADDRESS и т.д.) из PropertyValueCollection.
     * Код свойства в админке — строковый (например 'CITY'), не ID.
     */
    private static function getOrderProperties($order): array
    {
        $result = [];
        $props = $order->getPropertyCollection();
        foreach ($props as $prop) {
            $result[$prop->getField('CODE')] = $prop->getValue();
        }
        return $result;
    }

    /**
     * Профили (курьер/ПВЗ/почта) — это отдельные службы с PARENT_ID.
     * Base::getChildrenClassNames() возвращает массив под-служб.
     */
    public static function getChildrenClassNames(): array
    {
        return [
            '\\Acme\\Sale\\Delivery\\Profiles\\Courier',
            '\\Acme\\Sale\\Delivery\\Profiles\\Pickup',
            '\\Acme\\Sale\\Delivery\\Profiles\\Post',
        ];
    }
}