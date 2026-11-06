<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Loader;

class CurrencyRatesComponent extends CBitrixComponent
{
    // API Центрального Банка РФ
    private const CBR_API_URL = 'https://www.cbr-xml-daily.ru/daily_json.js';

    /**
     * Проверка и подготовка параметров
     */
    private function prepareParams()
    {
        // Список валют
        if (empty($this->arParams['CURRENCIES'])) {
            $this->arParams['CURRENCIES'] = 'USD,EUR,CNY,GBP';
        }

        // Преобразуем строку в массив
        $this->arParams['CURRENCIES_ARRAY'] = array_map(
            'trim',
            explode(',', $this->arParams['CURRENCIES'])
        );

        // Базовая валюта
        if (empty($this->arParams['BASE_CURRENCY'])) {
            $this->arParams['BASE_CURRENCY'] = 'RUB';
        }

        // Показывать динамику
        if (!isset($this->arParams['SHOW_DYNAMICS'])) {
            $this->arParams['SHOW_DYNAMICS'] = 'Y';
        }
    }

    /**
     * Получение курсов валют от ЦБ РФ
     */
    private function getRatesFromCBR()
    {
        // Создаем HTTP-клиент
        $httpClient = new HttpClient([
            'waitResponse' => true,
            'socketTimeout' => 5,
            'streamTimeout' => 10,
            'redirect' => true,
        ]);

        // Устанавливаем заголовки
        $httpClient->setHeader('Accept', 'application/json');
        $httpClient->setHeader('User-Agent', 'BitrixCurrencyWidget/1.0');

        $url = self::CBR_API_URL;

        // Логируем запрос
        $this->logRequest($url);

        try {
            // Выполняем запрос
            $startTime = microtime(true);
            $response = $httpClient->get($url);
            $duration = round((microtime(true) - $startTime) * 1000);
            $status = $httpClient->getStatus();

            // Проверяем статус
            if ($status !== 200) {
                throw new \Exception('CBR API returned status ' . $status);
            }

            // Парсим JSON
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('JSON parse error: ' . json_last_error_msg());
            }

            // Логируем успех
            $this->logResponse($url, $status, $duration);

            return $data;

        } catch (\Exception $e) {
            // Логируем ошибку
            $this->logError($url, $e->getMessage());
            return null;
        }
    }

    /**
     * Форматирование данных валют
     */
    private function formatRatesData($apiData)
    {
        if (empty($apiData) || !isset($apiData['Valute'])) {
            return null;
        }

        $rates = [];
        $currencies = $this->arParams['CURRENCIES_ARRAY'];

        // Дата обновления
        $updateDate = '';
        if (!empty($apiData['Date'])) {
            $updateDate = date('d.m.Y H:i', strtotime($apiData['Date']));
        }

        // Обрабатываем каждую валюту
        foreach ($currencies as $code) {
            if (!isset($apiData['Valute'][$code])) {
                continue;
            }

            $valute = $apiData['Valute'][$code];

            // Рассчитываем изменение
            $change = 0;
            $changePercent = 0;
            if ($this->arParams['SHOW_DYNAMICS'] === 'Y') {
                $change = $valute['Value'] - $valute['Previous'];
                if ($valute['Previous'] > 0) {
                    $changePercent = ($change / $valute['Previous']) * 100;
                }
            }

            $rates[] = [
                'CODE' => $code,
                'NAME' => $valute['Name'],
                'NOMINAL' => $valute['Nominal'],
                'VALUE' => round($valute['Value'], 4),
                'PREVIOUS' => round($valute['Previous'], 4),
                'CHANGE' => round($change, 4),
                'CHANGE_PERCENT' => round($changePercent, 2),
                'IS_GROWTH' => $change > 0,
                'IS_DECLINE' => $change < 0,
            ];
        }

        return [
            'RATES' => $rates,
            'UPDATE_DATE' => $updateDate,
            'BASE_CURRENCY' => $this->arParams['BASE_CURRENCY'],
        ];
    }

    /**
     * Логирование запроса
     */
    private function logRequest($url)
    {
        AddMessage2Log(
            'Currency API Request: ' . $url,
            'currency_rates',
            \CEventLog::SEVERITY_INFO
        );
    }

    /**
     * Логирование ответа
     */
    private function logResponse($url, $status, $duration)
    {
        AddMessage2Log(
            'Currency API Response: status=' . $status . ', duration=' . $duration . 'ms',
            'currency_rates',
            \CEventLog::SEVERITY_INFO
        );
    }

    /**
     * Логирование ошибки
     */
    private function logError($url, $error)
    {
        AddMessage2Log(
            'Currency API Error: ' . $error,
            'currency_rates',
            \CEventLog::SEVERITY_ERROR
        );
    }

    /**
     * Основной метод выполнения
     */
    public function executeComponent()
    {
        // Подготавливаем параметры
        $this->prepareParams();

        // Кеширование
        if ($this->startResultCache()) {
            // Получаем данные из API
            $apiData = $this->getRatesFromCBR();

            if ($apiData === null) {
                $this->abortResultCache();
                $this->arResult['ERROR'] = 'Не удалось получить курсы валют';
            } else {
                // Форматируем данные
                $formattedData = $this->formatRatesData($apiData);

                if ($formattedData === null) {
                    $this->abortResultCache();
                    $this->arResult['ERROR'] = 'Ошибка обработки данных';
                } else {
                    $this->arResult = array_merge($this->arResult, $formattedData);
                }
            }

            $this->includeComponentTemplate();
        }
    }
}
