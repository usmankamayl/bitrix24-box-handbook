<?php
/**
 * Обработчик службы доставки СДЭК
 *
 * Интеграция с API СДЭК для расчета стоимости и сроков доставки
 */

namespace Sale\Handlers\Delivery;

use Bitrix\Main\Error;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Sale\Delivery\CalculationResult;
use Bitrix\Sale\Delivery\Services\Base;
use Bitrix\Sale\Shipment;

\Bitrix\Main\Loader::registerAutoLoadClasses(
    null,
    [
        'Sale\Handlers\Delivery\CdekHandler' => __FILE__,
    ]
);

/**
 * Класс обработчика службы доставки СДЭК
 */
class CdekHandler extends Base
{
    /** @var string API endpoint для тестового режима */
    const API_URL_TEST = 'https://api.edu.cdek.ru/v2';

    /** @var string API endpoint для боевого режима */
    const API_URL_LIVE = 'https://api.cdek.ru/v2';

    /** @var string|null Кешированный токен доступа */
    private static $accessToken = null;

    /** @var int Время жизни токена (секунды) */
    private static $tokenExpires = 0;

    /**
     * Проверка, является ли служба профильной
     * (профильная служба имеет несколько вариантов доставки)
     *
     * @return bool
     */
    public static function isProfile()
    {
        return true;
    }

    /**
     * Расчет стоимости доставки
     *
     * @param Shipment $shipment Объект отгрузки
     * @return CalculationResult
     */
    protected function calculateConcrete(Shipment $shipment)
    {
        $result = new CalculationResult();

        // Получаем параметры доставки
        $config = $this->config['MAIN'];

        // Проверяем наличие обязательных параметров
        if (empty($config['CDEK_ACCOUNT']) || empty($config['CDEK_PASSWORD'])) {
            $result->addError(new Error('Не заданы учетные данные СДЭК'));
            return $result;
        }

        // Получаем токен доступа
        $token = $this->getAccessToken($config);

        if (!$token) {
            $result->addError(new Error('Не удалось получить токен доступа'));
            return $result;
        }

        // Получаем адрес доставки
        $order = $shipment->getOrder();
        $propertyCollection = $order->getPropertyCollection();

        $location = $propertyCollection->getDeliveryLocation();
        if (!$location) {
            $result->addError(new Error('Не указан адрес доставки'));
            return $result;
        }

        // Получаем индекс доставки
        $postalCode = $location->getPostalCode();
        $cityName = $location->getCityName();

        // Проверяем кеш
        $cacheKey = $this->getCacheKey($postalCode, $cityName, $shipment);
        $cacheTime = (int)($config['CDEK_CACHE_TIME'] ?? 60);

        if ($cacheTime > 0) {
            $cachedData = $this->getCachedData($cacheKey, $cacheTime);
            if ($cachedData) {
                $this->log('Using cached data', ['cache_key' => $cacheKey]);
                return $this->buildResultFromCache($cachedData, $config);
            }
        }

        // Формируем данные для расчета
        $calculationData = $this->buildCalculationData($shipment, $config, $postalCode, $cityName);

        if (!$calculationData) {
            $result->addError(new Error('Не удалось сформировать данные для расчета'));
            return $result;
        }

        // Выполняем запрос к API
        $apiUrl = $this->getApiUrl($config) . '/calculator/tariff';
        $httpClient = new HttpClient();

        // Устанавливаем заголовки
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setHeader('Authorization', 'Bearer ' . $token);

        // Логируем запрос
        $this->log('API Request', [
            'url' => $apiUrl,
            'data' => $calculationData
        ]);

        // Отправляем запрос
        $response = $httpClient->post($apiUrl, Json::encode($calculationData));

        // Логируем ответ
        $this->log('API Response', [
            'response' => $response,
            'status' => $httpClient->getStatus()
        ]);

        if (!$response) {
            $result->addError(new Error('Ошибка при обращении к API СДЭК: ' . $httpClient->getError()));
            return $result;
        }

        // Декодируем ответ
        try {
            $responseData = Json::decode($response);
        } catch (\Exception $e) {
            $result->addError(new Error('Ошибка декодирования ответа: ' . $e->getMessage()));
            return $result;
        }

        // Проверяем наличие ошибок в ответе
        if (isset($responseData['errors'])) {
            $errorMessages = [];
            foreach ($responseData['errors'] as $error) {
                $errorMessages[] = $error['message'] ?? 'Неизвестная ошибка';
            }
            $result->addError(new Error('Ошибка СДЭК: ' . implode(', ', $errorMessages)));
            return $result;
        }

        // Проверяем наличие данных о стоимости
        if (!isset($responseData['total_sum'])) {
            $result->addError(new Error('В ответе API отсутствует стоимость доставки'));
            return $result;
        }

        // Получаем стоимость
        $price = (float)$responseData['total_sum'];

        // Применяем наценку, если задана
        $extraCharge = (float)($config['CDEK_EXTRA_CHARGE'] ?? 0);
        if ($extraCharge > 0) {
            $price = $price * (1 + $extraCharge / 100);
        }

        $result->setDeliveryPrice(round($price, 2));

        // Формируем описание сроков доставки
        $periodMin = $responseData['period_min'] ?? null;
        $periodMax = $responseData['period_max'] ?? null;

        if ($periodMin && $periodMax) {
            if ($periodMin === $periodMax) {
                $periodDescription = $periodMin . ' ' . $this->getPluralDays($periodMin);
            } else {
                $periodDescription = $periodMin . '-' . $periodMax . ' ' . $this->getPluralDays($periodMax);
            }
            $result->setPeriodDescription($periodDescription);
        }

        // Кешируем результат
        if ($cacheTime > 0) {
            $this->cacheData($cacheKey, [
                'price' => $result->getPrice(),
                'period' => $result->getPeriodDescription()
            ]);
        }

        return $result;
    }

    /**
     * Получение токена доступа к API
     *
     * @param array $config Конфигурация
     * @return string|null
     */
    protected function getAccessToken($config)
    {
        // Проверяем кеш токена
        if (self::$accessToken && time() < self::$tokenExpires) {
            return self::$accessToken;
        }

        $apiUrl = $this->getApiUrl($config) . '/oauth/token';

        // Параметры для получения токена
        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $config['CDEK_ACCOUNT'],
            'client_secret' => $config['CDEK_PASSWORD']
        ];

        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/x-www-form-urlencoded');

        $response = $httpClient->post($apiUrl, http_build_query($params));

        if (!$response) {
            $this->log('Token error', ['error' => $httpClient->getError()]);
            return null;
        }

        try {
            $data = Json::decode($response);
        } catch (\Exception $e) {
            $this->log('Token decode error', ['error' => $e->getMessage()]);
            return null;
        }

        if (!isset($data['access_token'])) {
            $this->log('Token not found', ['response' => $data]);
            return null;
        }

        // Кешируем токен (на час)
        self::$accessToken = $data['access_token'];
        self::$tokenExpires = time() + 3600;

        return self::$accessToken;
    }

    /**
     * Формирование данных для запроса расчета
     *
     * @param Shipment $shipment Отгрузка
     * @param array $config Конфигурация
     * @param string $postalCode Индекс получателя
     * @param string $cityName Город получателя
     * @return array|null
     */
    protected function buildCalculationData(Shipment $shipment, $config, $postalCode, $cityName)
    {
        // Получаем вес и габариты
        $weight = $this->getWeight($shipment);

        if ($weight <= 0) {
            $weight = 1000; // 1 кг по умолчанию
        }

        // Получаем профиль доставки
        $profile = $this->getConfigValue('PROFILE');
        $tariffCode = null;

        if ($profile && isset($this->config['PROFILES'][$profile])) {
            $tariffCode = $this->config['PROFILES'][$profile]['TARIFF_CODE'];
        }

        if (!$tariffCode) {
            $this->log('Tariff code not found', ['profile' => $profile]);
            return null;
        }

        // Формируем данные для запроса
        $data = [
            'tariff_code' => $tariffCode,
            'from_location' => [
                'code' => (int)$config['CDEK_FROM_CODE']
            ],
            'to_location' => [
                'postal_code' => $postalCode
            ],
            'packages' => [
                [
                    'weight' => $weight,
                    'length' => 10,
                    'width' => 10,
                    'height' => 10
                ]
            ]
        ];

        return $data;
    }

    /**
     * Получение веса отгрузки в граммах
     *
     * @param Shipment $shipment Отгрузка
     * @return int
     */
    protected function getWeight(Shipment $shipment)
    {
        $weight = 0;

        // Проходим по всем товарам в отгрузке
        $shipmentItemCollection = $shipment->getShipmentItemCollection();

        foreach ($shipmentItemCollection as $shipmentItem) {
            $basketItem = $shipmentItem->getBasketItem();

            if (!$basketItem) {
                continue;
            }

            // Получаем вес товара
            $itemWeight = (float)$basketItem->getWeight();
            $quantity = $shipmentItem->getQuantity();

            $weight += $itemWeight * $quantity;
        }

        // Конвертируем в граммы (Bitrix хранит вес в граммах)
        return (int)$weight;
    }

    /**
     * Получение URL API в зависимости от режима
     *
     * @param array $config Конфигурация
     * @return string
     */
    protected function getApiUrl($config)
    {
        $mode = $config['CDEK_MODE'] ?? 'TEST';
        return $mode === 'LIVE' ? self::API_URL_LIVE : self::API_URL_TEST;
    }

    /**
     * Формирование ключа кеша
     *
     * @param string $postalCode Индекс
     * @param string $cityName Город
     * @param Shipment $shipment Отгрузка
     * @return string
     */
    protected function getCacheKey($postalCode, $cityName, Shipment $shipment)
    {
        $weight = $this->getWeight($shipment);
        $profile = $this->getConfigValue('PROFILE');

        return md5(implode('_', [
            $this->getId(),
            $profile,
            $postalCode,
            $cityName,
            $weight
        ]));
    }

    /**
     * Получение данных из кеша
     *
     * @param string $cacheKey Ключ кеша
     * @param int $cacheTime Время жизни кеша (минуты)
     * @return array|null
     */
    protected function getCachedData($cacheKey, $cacheTime)
    {
        $cache = \Bitrix\Main\Data\Cache::createInstance();

        if ($cache->initCache($cacheTime * 60, $cacheKey, '/sale/delivery/cdek/')) {
            return $cache->getVars();
        }

        return null;
    }

    /**
     * Сохранение данных в кеш
     *
     * @param string $cacheKey Ключ кеша
     * @param array $data Данные
     */
    protected function cacheData($cacheKey, $data)
    {
        $config = $this->config['MAIN'];
        $cacheTime = (int)($config['CDEK_CACHE_TIME'] ?? 60);

        $cache = \Bitrix\Main\Data\Cache::createInstance();

        if ($cache->startDataCache($cacheTime * 60, $cacheKey, '/sale/delivery/cdek/')) {
            $cache->endDataCache($data);
        }
    }

    /**
     * Построение результата из кешированных данных
     *
     * @param array $cachedData Кешированные данные
     * @param array $config Конфигурация
     * @return CalculationResult
     */
    protected function buildResultFromCache($cachedData, $config)
    {
        $result = new CalculationResult();

        if (isset($cachedData['price'])) {
            $result->setDeliveryPrice($cachedData['price']);
        }

        if (isset($cachedData['period'])) {
            $result->setPeriodDescription($cachedData['period']);
        }

        return $result;
    }

    /**
     * Получение правильного склонения слова "день"
     *
     * @param int $number Число
     * @return string
     */
    protected function getPluralDays($number)
    {
        $cases = [2, 0, 1, 1, 1, 2];
        $titles = ['день', 'дня', 'дней'];

        return $titles[
            ($number % 100 > 4 && $number % 100 < 20)
                ? 2
                : $cases[min($number % 10, 5)]
        ];
    }

    /**
     * Логирование событий
     *
     * @param string $title Заголовок записи
     * @param array $data Данные для логирования
     */
    protected function log($title, $data = [])
    {
        \Bitrix\Main\Diag\Debug::writeToFile(
            $data,
            $title,
            'cdek_delivery.log'
        );
    }

    /**
     * Получение списка пунктов выдачи
     * (для использования в дополнительном функционале)
     *
     * @param string $cityCode Код города СДЭК
     * @return array
     */
    public function getPickupPoints($cityCode)
    {
        $config = $this->config['MAIN'];
        $token = $this->getAccessToken($config);

        if (!$token) {
            return [];
        }

        $apiUrl = $this->getApiUrl($config) . '/deliverypoints';

        $httpClient = new HttpClient();
        $httpClient->setHeader('Authorization', 'Bearer ' . $token);

        $response = $httpClient->get($apiUrl . '?city_code=' . $cityCode);

        if (!$response) {
            return [];
        }

        try {
            $data = Json::decode($response);
            return $data;
        } catch (\Exception $e) {
            $this->log('Pickup points error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Трекинг отправления
     * (для использования в дополнительном функционале)
     *
     * @param string $trackingNumber Трек-номер
     * @return array|null
     */
    public function trackShipment($trackingNumber)
    {
        $config = $this->config['MAIN'];
        $token = $this->getAccessToken($config);

        if (!$token) {
            return null;
        }

        $apiUrl = $this->getApiUrl($config) . '/orders';

        $httpClient = new HttpClient();
        $httpClient->setHeader('Authorization', 'Bearer ' . $token);

        $response = $httpClient->get($apiUrl . '?cdek_number=' . $trackingNumber);

        if (!$response) {
            return null;
        }

        try {
            $data = Json::decode($response);
            return $data;
        } catch (\Exception $e) {
            $this->log('Tracking error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
