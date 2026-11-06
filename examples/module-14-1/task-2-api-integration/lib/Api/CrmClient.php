<?php
/**
 * Клиент для работы с внешней CRM через API
 *
 * Пример использования:
 *
 * $crm = new CrmClient();
 * $result = $crm->createContact([
 *     'name' => 'Иван Иванов',
 *     'email' => 'ivan@example.com',
 * ]);
 */

namespace Acme\Integration\Api;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Result;

class CrmClient extends AbstractClient
{
    /**
     * URL базового адреса CRM API
     *
     * @return string
     */
    protected function getApiUrl(): string
    {
        return Option::get('acme.integration', 'crm_api_url', 'https://api.crm.example.com');
    }

    /**
     * API ключ для авторизации
     *
     * @return string
     */
    protected function getApiKey(): string
    {
        return Option::get('acme.integration', 'crm_api_key', '');
    }

    /**
     * Создать контакт
     *
     * @param array $data Данные контакта
     * @return Result
     */
    public function createContact(array $data): Result
    {
        // Валидация обязательных полей
        if (empty($data['name'])) {
            return $this->createErrorResult('Поле name обязательно');
        }

        return $this->post('/contacts', $data);
    }

    /**
     * Получить контакт по ID
     *
     * @param int $contactId
     * @return Result
     */
    public function getContact(int $contactId): Result
    {
        // Используем кеширование на 5 минут
        return $this->get('/contacts/' . $contactId, [], true, 300);
    }

    /**
     * Обновить контакт
     *
     * @param int $contactId
     * @param array $data Данные для обновления
     * @return Result
     */
    public function updateContact(int $contactId, array $data): Result
    {
        // Инвалидируем кеш при обновлении
        $this->cache->delete('api_get_' . md5($this->buildUrl('/contacts/' . $contactId)));

        return $this->put('/contacts/' . $contactId, $data);
    }

    /**
     * Удалить контакт
     *
     * @param int $contactId
     * @return Result
     */
    public function deleteContact(int $contactId): Result
    {
        // Инвалидируем кеш при удалении
        $this->cache->delete('api_get_' . md5($this->buildUrl('/contacts/' . $contactId)));

        return $this->delete('/contacts/' . $contactId);
    }

    /**
     * Поиск контактов
     *
     * @param array $filter Фильтры поиска
     * @param int $page Номер страницы
     * @param int $perPage Записей на странице
     * @return Result
     */
    public function searchContacts(array $filter = [], int $page = 1, int $perPage = 50): Result
    {
        $params = array_merge($filter, [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $this->get('/contacts', $params);
    }

    /**
     * Создать сделку
     *
     * @param array $data Данные сделки
     * @return Result
     */
    public function createDeal(array $data): Result
    {
        if (empty($data['title'])) {
            return $this->createErrorResult('Поле title обязательно');
        }

        return $this->post('/deals', $data);
    }

    /**
     * Получить сделку по ID
     *
     * @param int $dealId
     * @return Result
     */
    public function getDeal(int $dealId): Result
    {
        return $this->get('/deals/' . $dealId, [], true, 300);
    }

    /**
     * Обновить статус сделки
     *
     * @param int $dealId
     * @param string $status Новый статус
     * @return Result
     */
    public function updateDealStatus(int $dealId, string $status): Result
    {
        return $this->put('/deals/' . $dealId, [
            'status' => $status,
        ]);
    }

    /**
     * Получить список сделок
     *
     * @param array $filter Фильтры
     * @param int $page Страница
     * @param int $perPage Записей на странице
     * @return Result
     */
    public function getDeals(array $filter = [], int $page = 1, int $perPage = 50): Result
    {
        $params = array_merge($filter, [
            'page' => $page,
            'per_page' => $perPage,
        ]);

        return $this->get('/deals', $params);
    }

    /**
     * Добавить примечание к контакту
     *
     * @param int $contactId
     * @param string $note Текст примечания
     * @return Result
     */
    public function addNoteToContact(int $contactId, string $note): Result
    {
        return $this->post('/contacts/' . $contactId . '/notes', [
            'text' => $note,
        ]);
    }

    /**
     * Привязать контакт к сделке
     *
     * @param int $dealId
     * @param int $contactId
     * @return Result
     */
    public function attachContactToDeal(int $dealId, int $contactId): Result
    {
        return $this->post('/deals/' . $dealId . '/contacts', [
            'contact_id' => $contactId,
        ]);
    }

    /**
     * Получить статистику по воронке продаж
     *
     * @param string $pipelineId ID воронки
     * @return Result
     */
    public function getPipelineStats(string $pipelineId): Result
    {
        // Кешируем статистику на 1 час
        return $this->get('/pipelines/' . $pipelineId . '/stats', [], true, 3600);
    }
}
