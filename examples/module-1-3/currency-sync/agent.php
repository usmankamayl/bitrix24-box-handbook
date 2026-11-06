<?php
/**
 * Агент для синхронизации данных с внешним API
 *
 * Задача: Получать данные о курсах валют и обновлять их в системе
 *
 * Этот агент демонстрирует:
 * - Работу с внешними API
 * - HTTP-запросы через Bitrix\Main\Web\HttpClient
 * - Обработку JSON-данных
 * - Кеширование результатов
 * - Retry-логику при ошибках
 * - Работу с highload-блоками (опционально)
 */

namespace MyCompany\Agents;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Diag\Debug;

class CurrencyRatesAgent
{
    /**
     * URL API для получения курсов валют
     * Используем бесплатное API exchangerate-api.com (или ЦБ РФ)
     */
    const API_URL = "https://www.cbr-xml-daily.ru/daily_json.js";

    /**
     * Максимальное количество попыток при ошибке
     */
    const MAX_RETRIES = 3;

    /**
     * Задержка между попытками (секунды)
     */
    const RETRY_DELAY = 5;

    /**
     * Список валют для синхронизации
     */
    const CURRENCIES = ['USD', 'EUR', 'GBP', 'CNY', 'JPY'];

    /**
     * Основной метод агента
     *
     * @return string
     */
    public static function execute()
    {
        try {
            self::log("Начало синхронизации курсов валют");

            // Получаем данные из внешнего API с повторными попытками
            $rates = self::fetchRatesWithRetry();

            if (empty($rates)) {
                self::logError("Не удалось получить курсы валют после " . self::MAX_RETRIES . " попыток");
                return self::getAgentName();
            }

            // Сохраняем курсы в систему
            $savedCount = self::saveRates($rates);

            // Очищаем кеш компонентов, которые используют курсы
            self::clearCache();

            self::log("Синхронизация завершена. Обновлено валют: {$savedCount}");

            // Уведомляем о значительных изменениях курса
            self::checkSignificantChanges($rates);

        } catch (\Exception $e) {
            self::logError("Критическая ошибка: " . $e->getMessage());
            self::notifyAdmin($e->getMessage());
        }

        return self::getAgentName();
    }

    /**
     * Получить курсы валют из API с повторными попытками
     *
     * @return array|null
     */
    private static function fetchRatesWithRetry()
    {
        $attempt = 0;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            try {
                self::log("Попытка {$attempt} из " . self::MAX_RETRIES);

                $rates = self::fetchRates();

                if ($rates !== null) {
                    return $rates;
                }

            } catch (\Exception $e) {
                self::logError("Ошибка при попытке {$attempt}: " . $e->getMessage());
            }

            // Ждем перед следующей попыткой
            if ($attempt < self::MAX_RETRIES) {
                sleep(self::RETRY_DELAY);
            }
        }

        return null;
    }

    /**
     * Получить курсы валют из API
     *
     * @return array|null
     * @throws \Exception
     */
    private static function fetchRates()
    {
        // Создаем HTTP-клиент
        $httpClient = new HttpClient([
            'socketTimeout' => 10,
            'streamTimeout' => 10,
            'redirect' => true,
            'redirectMax' => 5,
        ]);

        // Устанавливаем заголовки
        $httpClient->setHeader('User-Agent', 'Bitrix Currency Sync Agent');
        $httpClient->setHeader('Accept', 'application/json');

        // Выполняем запрос
        $response = $httpClient->get(self::API_URL);

        // Проверяем статус
        $status = $httpClient->getStatus();
        if ($status !== 200) {
            throw new \Exception("API returned status {$status}");
        }

        if (empty($response)) {
            throw new \Exception("Empty response from API");
        }

        // Парсим JSON
        try {
            $data = Json::decode($response);
        } catch (\Exception $e) {
            throw new \Exception("Invalid JSON response: " . $e->getMessage());
        }

        // Проверяем структуру ответа (для API ЦБ РФ)
        if (!isset($data['Valute']) || !is_array($data['Valute'])) {
            throw new \Exception("Invalid response structure");
        }

        // Извлекаем нужные валюты
        $rates = [];
        foreach (self::CURRENCIES as $currencyCode) {
            if (isset($data['Valute'][$currencyCode])) {
                $currency = $data['Valute'][$currencyCode];
                $rates[$currencyCode] = [
                    'CODE' => $currencyCode,
                    'NAME' => $currency['Name'],
                    'VALUE' => $currency['Value'],
                    'NOMINAL' => $currency['Nominal'],
                    'RATE' => $currency['Value'] / $currency['Nominal'], // Курс за 1 единицу
                    'DATE' => $data['Date'],
                ];
            }
        }

        return $rates;
    }

    /**
     * Сохранить курсы валют в системе
     *
     * @param array $rates
     * @return int количество сохраненных валют
     */
    private static function saveRates(array $rates)
    {
        $savedCount = 0;

        foreach ($rates as $currencyCode => $rate) {
            try {
                // Сохраняем в настройки (для простоты)
                // В реальном проекте можно использовать HL-блок или отдельную таблицу
                \COption::SetOptionString(
                    'currency',
                    "rate_{$currencyCode}",
                    Json::encode($rate)
                );

                // Также можно обновить стандартный справочник валют Bitrix
                self::updateBitrixCurrency($currencyCode, $rate['RATE']);

                $savedCount++;

                self::log("Сохранен курс {$currencyCode}: {$rate['RATE']} руб.");

            } catch (\Exception $e) {
                self::logError("Ошибка сохранения {$currencyCode}: " . $e->getMessage());
            }
        }

        return $savedCount;
    }

    /**
     * Обновить курс валюты в стандартном справочнике Bitrix
     *
     * @param string $currencyCode
     * @param float $rate
     */
    private static function updateBitrixCurrency($currencyCode, $rate)
    {
        if (!\Bitrix\Main\Loader::includeModule('currency')) {
            return;
        }

        // Получаем текущую валюту
        $currency = \CCurrency::GetByID($currencyCode);

        if ($currency) {
            // Обновляем курс относительно базовой валюты (RUB)
            \CCurrencyRates::Add([
                'CURRENCY' => $currencyCode,
                'DATE_RATE' => date('Y-m-d'),
                'RATE' => $rate,
                'RATE_CNT' => 1,
            ]);
        }
    }

    /**
     * Проверить значительные изменения курса и уведомить
     *
     * @param array $rates
     */
    private static function checkSignificantChanges(array $rates)
    {
        $threshold = 2.0; // Порог изменения в процентах

        foreach ($rates as $currencyCode => $rate) {
            // Получаем предыдущий курс
            $previousRateJson = \COption::GetOptionString('currency', "rate_{$currencyCode}", '');

            if (empty($previousRateJson)) {
                continue; // Первый запуск, нет предыдущих данных
            }

            try {
                $previousRate = Json::decode($previousRateJson);
                $oldValue = $previousRate['RATE'];
                $newValue = $rate['RATE'];

                // Вычисляем изменение в процентах
                $changePercent = abs(($newValue - $oldValue) / $oldValue * 100);

                if ($changePercent >= $threshold) {
                    $direction = $newValue > $oldValue ? 'вырос' : 'упал';

                    $message = "Курс {$currencyCode} {$direction} на " .
                              round($changePercent, 2) . "% " .
                              "(было: {$oldValue}, стало: {$newValue})";

                    self::log("ВНИМАНИЕ: " . $message);

                    // Отправляем уведомление
                    self::notifySignificantChange($currencyCode, $oldValue, $newValue, $changePercent);
                }

            } catch (\Exception $e) {
                self::logError("Ошибка проверки изменений {$currencyCode}: " . $e->getMessage());
            }
        }
    }

    /**
     * Отправить уведомление о значительном изменении курса
     *
     * @param string $currency
     * @param float $oldRate
     * @param float $newRate
     * @param float $changePercent
     */
    private static function notifySignificantChange($currency, $oldRate, $newRate, $changePercent)
    {
        \CEvent::Send(
            "CURRENCY_CHANGE_ALERT",
            SITE_ID,
            [
                "CURRENCY" => $currency,
                "OLD_RATE" => number_format($oldRate, 4, '.', ''),
                "NEW_RATE" => number_format($newRate, 4, '.', ''),
                "CHANGE_PERCENT" => number_format($changePercent, 2, '.', ''),
                "DATE_TIME" => date("d.m.Y H:i:s"),
            ]
        );
    }

    /**
     * Очистить кеш компонентов
     */
    private static function clearCache()
    {
        try {
            // Очищаем кеш по тегу
            \Bitrix\Main\Application::getInstance()
                ->getTaggedCache()
                ->clearByTag('currency_rates');

            // Или очищаем управляемый кеш
            if (defined('BX_COMP_MANAGED_CACHE')) {
                global $CACHE_MANAGER;
                $CACHE_MANAGER->ClearByTag('currency_rates');
            }

        } catch (\Exception $e) {
            self::logError("Ошибка очистки кеша: " . $e->getMessage());
        }
    }

    /**
     * Получить имя агента
     *
     * @return string
     */
    private static function getAgentName()
    {
        return __METHOD__ . "();";
    }

    /**
     * Логирование
     *
     * @param string $message
     */
    private static function log($message)
    {
        Debug::writeToFile(
            $message,
            date("Y-m-d H:i:s"),
            "/logs/currency_agent.log"
        );

        \CEventLog::Add([
            "SEVERITY" => "INFO",
            "AUDIT_TYPE_ID" => "CURRENCY_SYNC",
            "DESCRIPTION" => $message,
        ]);
    }

    /**
     * Логирование ошибок
     *
     * @param string $message
     */
    private static function logError($message)
    {
        Debug::writeToFile(
            $message,
            date("Y-m-d H:i:s") . " ERROR",
            "/logs/currency_agent_errors.log"
        );

        \CEventLog::Add([
            "SEVERITY" => "ERROR",
            "AUDIT_TYPE_ID" => "CURRENCY_SYNC",
            "DESCRIPTION" => $message,
        ]);
    }

    /**
     * Уведомить администратора
     *
     * @param string $errorMessage
     */
    private static function notifyAdmin($errorMessage)
    {
        \CEvent::Send(
            "AGENT_ERROR",
            SITE_ID,
            [
                "AGENT_NAME" => "CurrencyRatesAgent",
                "ERROR_MESSAGE" => $errorMessage,
                "DATE_TIME" => date("d.m.Y H:i:s"),
            ]
        );
    }
}
