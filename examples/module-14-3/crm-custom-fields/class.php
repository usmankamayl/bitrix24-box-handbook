<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Crm\Entity;
use Bitrix\Crm\Field;
use Bitrix\Crm\Item;
use Bitrix\Main\Error;

/**
 * Компонент: CRM форма с кастомными полями
 *
 * Демонстрирует:
 * - Добавление пользовательских полей в CRM
 * - Модификацию стандартных полей
 * - Валидацию и сохранение данных
 * - Интеграцию с бизнес-процессами
 */
class CrmCustomFieldsComponent extends CBitrixComponent
{
    protected $entityTypeId;
    protected $entity;

    public function executeComponent()
    {
        if (!Loader::includeModule('crm')) {
            ShowError('Модуль CRM не установлен');
            return;
        }

        $this->entityTypeId = $this->getEntityTypeId($this->arParams['ENTITY_TYPE']);
        $entityId = (int)$this->arParams['ENTITY_ID'];

        // Загрузка сущности
        if ($entityId > 0) {
            $this->loadEntity($entityId);
        } else {
            $this->entity = null;
        }

        // Инициализация формы
        $this->initializeForm();

        // Подключение скриптов
        $this->attachAssets();

        $this->includeComponentTemplate();
    }

    /**
     * Получение ID типа сущности
     */
    protected function getEntityTypeId($type)
    {
        $map = [
            'DEAL' => \CCrmOwnerType::Deal,
            'LEAD' => \CCrmOwnerType::Lead,
            'CONTACT' => \CCrmOwnerType::Contact,
            'COMPANY' => \CCrmOwnerType::Company,
        ];

        return $map[$type] ?? \CCrmOwnerType::Deal;
    }

    /**
     * Загрузка сущности CRM
     */
    protected function loadEntity($entityId)
    {
        $entity = new \CCrmDeal();
        $this->entity = $entity->GetByID($entityId, false);

        if (!$this->entity) {
            $this->entity = null;
        }
    }

    /**
     * Инициализация формы
     */
    protected function initializeForm()
    {
        // Создание конфигурации полей
        $this->arResult['ENTITY_TYPE'] = $this->arParams['ENTITY_TYPE'];
        $this->arResult['ENTITY_ID'] = $this->arParams['ENTITY_ID'];
        $this->arResult['ENTITY'] = $this->entity;

        // Получение стандартных полей
        $this->arResult['STANDARD_FIELDS'] = $this->getStandardFields();

        // Получение кастомных полей
        if ($this->arParams['ENABLE_CUSTOM_FIELDS'] === 'Y') {
            $this->arResult['CUSTOM_FIELDS'] = $this->getCustomFields();
        }

        // Получение текущих значений
        if ($this->entity) {
            $this->arResult['CURRENT_VALUES'] = $this->entity;
        } else {
            $this->arResult['CURRENT_VALUES'] = [];
        }

        // Конфигурация для JavaScript
        $this->arResult['FORM_CONFIG'] = $this->getFormConfig();
    }

    /**
     * Получение стандартных полей CRM
     */
    protected function getStandardFields()
    {
        $entityType = $this->arParams['ENTITY_TYPE'];

        // Для типа DEAL (Сделка)
        if ($entityType === 'DEAL') {
            return [
                [
                    'name' => 'TITLE',
                    'title' => 'Название сделки',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'name' => 'OPPORTUNITY',
                    'title' => 'Сумма сделки',
                    'type' => 'number',
                    'required' => false,
                ],
                [
                    'name' => 'CURRENCY_ID',
                    'title' => 'Валюта',
                    'type' => 'list',
                    'values' => [
                        'RUB' => 'Рубли (RUB)',
                        'USD' => 'Доллары (USD)',
                        'EUR' => 'Евро (EUR)',
                    ],
                ],
                [
                    'name' => 'STAGE_ID',
                    'title' => 'Стадия',
                    'type' => 'list',
                    'required' => true,
                    'values' => [
                        'NEW' => 'Новая',
                        'IN_PROGRESS' => 'В процессе',
                        'NEGOTIATION' => 'Переговоры',
                        'WON' => 'Выигрыш',
                        'LOST' => 'Проигрыш',
                    ],
                ],
                [
                    'name' => 'CLOSEDATE',
                    'title' => 'Дата закрытия',
                    'type' => 'date',
                ],
                [
                    'name' => 'ASSIGNED_BY_ID',
                    'title' => 'Ответственный',
                    'type' => 'user',
                    'required' => true,
                ],
                [
                    'name' => 'COMMENTS',
                    'title' => 'Комментарий',
                    'type' => 'textarea',
                ],
            ];
        }

        return [];
    }

    /**
     * Получение кастомных полей
     */
    protected function getCustomFields()
    {
        $customFields = [];

        // Кастомные поля для различных типов сущностей
        $fields = [
            'UF_CRM_PROJECT_ID' => [
                'title' => 'Проект',
                'type' => 'text',
                'help' => 'ID проекта в системе управления проектами',
            ],
            'UF_CRM_QUALITY_SCORE' => [
                'title' => 'Оценка качества',
                'type' => 'number',
                'help' => 'Оценка от 1 до 100',
                'min' => 1,
                'max' => 100,
            ],
            'UF_CRM_PRIORITY' => [
                'title' => 'Приоритет',
                'type' => 'list',
                'values' => [
                    'LOW' => 'Низкий',
                    'MEDIUM' => 'Средний',
                    'HIGH' => 'Высокий',
                    'CRITICAL' => 'Критичный',
                ],
            ],
            'UF_CRM_TAGS' => [
                'title' => 'Теги',
                'type' => 'text',
                'help' => 'Теги через запятую',
            ],
            'UF_CRM_RISK_ASSESSMENT' => [
                'title' => 'Оценка рисков',
                'type' => 'textarea',
                'help' => 'Описание возможных рисков',
            ],
            'UF_CRM_EXTERNAL_ID' => [
                'title' => 'Внешний ID',
                'type' => 'text',
                'help' => 'ID в интегрируемой системе',
            ],
            'UF_CRM_LAST_ACTIVITY' => [
                'title' => 'Последняя активность',
                'type' => 'datetime',
                'readonly' => true,
            ],
            'UF_CRM_RESPONSIBLE_TEAM' => [
                'title' => 'Команда',
                'type' => 'user_multi',
                'help' => 'Члены команды, работающие над сделкой',
            ],
        ];

        foreach ($fields as $fieldCode => $fieldInfo) {
            $customFields[] = array_merge(['name' => $fieldCode], $fieldInfo);
        }

        return $customFields;
    }

    /**
     * Конфигурация формы
     */
    protected function getFormConfig()
    {
        return [
            'sections' => [
                [
                    'id' => 'main',
                    'title' => 'Основная информация',
                    'fields' => $this->arResult['STANDARD_FIELDS'],
                ],
                [
                    'id' => 'custom',
                    'title' => 'Дополнительные поля',
                    'fields' => $this->arResult['CUSTOM_FIELDS'] ?? [],
                ],
            ],
        ];
    }

    /**
     * Подключение ассетов
     */
    protected function attachAssets()
    {
        global $APPLICATION;

        $templateFolder = $this->GetFolder() . '/templates/' . $this->GetCurrentTemplate();

        $APPLICATION->AddHeadScript($templateFolder . '/script.js');
        $APPLICATION->SetAdditionalCSS($templateFolder . '/style.css');

        // Подключение UI расширений
        \Bitrix\Main\UI\Extension::load(['ui.notification', 'ui.buttons']);
    }
}
