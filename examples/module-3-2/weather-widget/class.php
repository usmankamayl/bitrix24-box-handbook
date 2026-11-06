<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Loader;

class WeatherWidgetComponent extends CBitrixComponent
{
    private const API_URL = 'https://api.openweathermap.org/data/2.5/weather';

    /**
     * Проверка обязательных параметров
     */
    private function checkParams()
    {
        if (empty($this->arParams['API_KEY'])) {
            $this->arResult['ERROR'] = 'Не указан API ключ';
            return false;
        }

        if (empty($this->arParams['CITY'])) {
            $this->arParams['CITY'] = 'Moscow';
        }

        if (empty($this->arParams['UNITS'])) {
            $this->arParams['UNITS'] = 'metric';
        }

        if (empty($this->arParams['LANG'])) {
            $this->arParams['LANG'] = 'ru';
        }

        return true;
    }

    /**
     * Получение данных о погоде с API
     */
    private function getWeatherFromApi()
    {
        // Создаем HTTP-клиент с таймаутами
        $httpClient = new HttpClient([
            'waitResponse' => true,
            'socketTimeout' => 5,
            'streamTimeout' => 10,
            'redirect' => true,
        ]);

        // Формируем URL с параметрами
        $url = self::API_URL . '?' . http_build_query([
            'q' => $this->arParams['CITY'],
            'appid' => $this->arParams['API_KEY'],
            'units' => $this->arParams['UNITS'],
            'lang' => $this->arParams['LANG'],
        ]);

        // Логируем запрос
        AddMessage2Log('Weather API Request: ' . $url, 'weather_widget');

        try {
            // Выполняем запрос
            $response = $httpClient->get($url);
            $status = $httpClient->getStatus();

            // Проверяем статус ответа
            if ($status !== 200) {
                throw new \Exception('API returned status ' . $status);
            }

            // Парсим JSON
            $data = Json::decode($response);

            // Логируем успех
            AddMessage2Log('Weather API Success', 'weather_widget');

            return $data;

        } catch (\Exception $e) {
            // Логируем ошибку
            AddMessage2Log('Weather API Error: ' . $e->getMessage(), 'weather_widget');
            return null;
        }
    }

    /**
     * Форматирование данных для шаблона
     */
    private function formatWeatherData($apiData)
    {
        if (empty($apiData)) {
            return null;
        }

        // Получаем единицы измерения
        $tempUnit = $this->arParams['UNITS'] === 'imperial' ? '°F' : '°C';
        $speedUnit = $this->arParams['UNITS'] === 'imperial' ? 'mph' : 'м/с';

        return [
            'CITY' => $apiData['name'],
            'COUNTRY' => $apiData['sys']['country'] ?? '',
            'TEMPERATURE' => round($apiData['main']['temp']),
            'TEMP_UNIT' => $tempUnit,
            'FEELS_LIKE' => round($apiData['main']['feels_like']),
            'DESCRIPTION' => ucfirst($apiData['weather'][0]['description'] ?? ''),
            'ICON' => $apiData['weather'][0]['icon'] ?? '',
            'ICON_URL' => 'https://openweathermap.org/img/wn/' . ($apiData['weather'][0]['icon'] ?? '') . '@2x.png',
            'HUMIDITY' => $apiData['main']['humidity'] ?? 0,
            'PRESSURE' => $apiData['main']['pressure'] ?? 0,
            'WIND_SPEED' => round($apiData['wind']['speed'], 1),
            'WIND_UNIT' => $speedUnit,
            'WIND_DEG' => $apiData['wind']['deg'] ?? 0,
            'CLOUDS' => $apiData['clouds']['all'] ?? 0,
            'SUNRISE' => date('H:i', $apiData['sys']['sunrise'] ?? 0),
            'SUNSET' => date('H:i', $apiData['sys']['sunset'] ?? 0),
        ];
    }

    /**
     * Основной метод выполнения компонента
     */
    public function executeComponent()
    {
        // Проверяем параметры
        if (!$this->checkParams()) {
            $this->includeComponentTemplate();
            return;
        }

        // Если кеширование включено
        if ($this->startResultCache()) {
            // Получаем данные из API
            $apiData = $this->getWeatherFromApi();

            if ($apiData === null) {
                $this->abortResultCache();
                $this->arResult['ERROR'] = 'Не удалось получить данные о погоде';
            } else {
                // Форматируем данные
                $this->arResult['WEATHER'] = $this->formatWeatherData($apiData);
            }

            // Подключаем шаблон
            $this->includeComponentTemplate();
        }
    }
}
