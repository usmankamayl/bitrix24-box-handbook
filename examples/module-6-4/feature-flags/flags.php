<?php
/**
 * Кейс 6.4.3 — Feature Flags: безопасный rollout новой фичи
 *
 * Задача: новый checkout — выкатываем постепенно:
 * 10% пользователей → 50% → 100% → все.
 * Без деплоя, с возможностью мгновенного отключения.
 *
 * Senior-пойнты:
 * - Option, не код — выключаем без релиза.
 * - Детерминированное хеширование по USER_ID — не мигает при обновлении.
 * - Rollout по проценту, по группам, по кукам.
 * - Метрика: кто видел фичу, какая конверсия.
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;

// ============================================================================
// 1. Простой фича-флаг — вкл/выкл глобально
// ============================================================================
//
// БЫСТРЫЙ ВАРИАНТ для hotfix — выключить фичу без деплоя.

final class FeatureFlags
{
    /**
     * Включена ли фича глобально?
     *
     * @param string $feature 'checkout_v2', 'new_catalog', 'promo_banner'
     */
    public static function isEnabled(string $feature): bool
    {
        return Option::get('acme_features', $feature, 'N') === 'Y';
    }

    /**
     * Включить/выключить фичу — из админки или крона.
     */
    public static function set(string $feature, bool $enabled): void
    {
        Option::set('acme_features', $feature, $enabled ? 'Y' : 'N');
    }
}

// ============================================================================
// 2. Rollout по проценту — детерминированное хеширование
// ============================================================================
//
// Ключевое: один и тот же пользователь ВСЕГДА в одной группе.
// Не rand() — иначе пользователь видит то старый, то новый UI.

final class GradualRollout
{
    /**
     * Входит ли пользователь в rollout-группу?
     *
     * @param int    $userId   ID пользователя
     * @param string $feature  'checkout_v2'
     * @param int    $percent  0-100 — процент пользователей в эксперименте
     */
    public static function isInRollout(int $userId, string $feature, int $percent): bool
    {
        if ($percent <= 0) {
            return false;
        }
        if ($percent >= 100) {
            return true;
        }

        // Детерминированный хеш: md5(userId + feature) % 100 < percent
        // Пользователь 123 для фичи 'checkout_v2' всегда получит одно число.
        $hash = crc32($userId . ':' . $feature);
        $bucket = $hash % 100;

        return $bucket < $percent;
    }

    /**
     * Включена ли фича для конкретного пользователя?
     *
     * Логика:
     *   1. Глобальный флаг выключен → нет.
     *   2. Rollout = 100% → да.
     *   3. Rollout < 100% → проверяем хеш.
     */
    public static function isEnabledForUser(int $userId, string $feature): bool
    {
        if (!FeatureFlags::isEnabled($feature)) {
            return false;
        }

        $percent = (int)Option::get('acme_features', $feature . '_rollout', 0);
        if ($percent >= 100) {
            return true;
        }

        return self::isInRollout($userId, $feature, $percent);
    }

    /**
     * Увеличить rollout — вызывается из крона или админки.
     */
    public static function increaseRollout(string $feature, int $step = 10): void
    {
        $current = (int)Option::get('acme_features', $feature . '_rollout', 0);
        $next = min(100, $current + $step);
        Option::set('acme_features', $feature . '_rollout', $next);
    }
}

// ============================================================================
// 3. Rollout по группам — для раннего доступа (beta-testers)
// ============================================================================
//
// Даём доступ определённым группам пользователей ДО общего rollout.

final class GroupRollout
{
    /**
     * Входит ли пользователь в beta-группу?
     *
     * @param int    $userId
     * @param string $feature
     * @param array  $allowedGroups ['beta_testers', 'staff', 'partners']
     */
    public static function isInGroup(int $userId, string $feature, array $allowedGroups): bool
    {
        global $USER;
        $userGroups = $USER->GetUserGroupArray();

        foreach ($allowedGroups as $groupCode) {
            // Ищем группу по символьному коду
            $group = \Bitrix\Main\GroupTable::getList([
                'select' => ['ID'],
                'filter' => ['=STRING_ID' => $groupCode],
            ])->fetch();

            if ($group && in_array($group['ID'], $userGroups)) {
                return true;
            }
        }
        return false;
    }
}

// ============================================================================
// 4. Использование в коде — подмена старого/нового компонента
// ============================================================================

class CheckoutPage
{
    public function render(): string
    {
        $userId = $GLOBALS['USER']->GetID();

        // Проверяем фичу — старый или новый checkout
        if (GradualRollout::isEnabledForUser($userId, 'checkout_v2')) {
            // Новый checkout — компонент/сервис v2
            $this->logExposure($userId, 'checkout_v2');
            return $this->renderV2();
        }

        // Старый checkout — стабильный
        return $this->renderV1();
    }

    private function renderV1(): string
    {
        // Старый код — не трогаем
        return 'old checkout';
    }

    private function renderV2(): string
    {
        // Новый код — под флагом
        return 'new checkout';
    }

    /**
     * Логируем, кто увидел фичу — для метрики.
     */
    private function logExposure(int $userId, string $feature): void
    {
        // В аналитику: пользователь X увидел фичу Y в момент Z
        // Потом считаем конверсию: из тех, кто увидел — сколько оформили заказ?
        Application::getConnection()->queryExecute(
            "INSERT INTO acme_feature_exposure (USER_ID, FEATURE, EXPOSED_AT)
             VALUES (?, ?, NOW())",
            [$userId, $feature]
        );
    }
}

// ============================================================================
// 5. Админ-интерфейс — переключатель фичи
// ============================================================================
//
// В админке: checkbox "checkout_v2" + slider "rollout %".
// Сохраняем в Option — мгновенно, без деплоя.

// GET /bitrix/admin/acme_features.php?feature=checkout_v2&rollout=25
if ($_GET['feature'] && isset($_GET['rollout'])) {
    FeatureFlags::set($_GET['feature'], true);
    Option::set('acme_features', $_GET['feature'] . '_rollout', (int)$_GET['rollout']);
    echo 'updated';
}

// ============================================================================
// 6. Метрика — считаем конверсию
// ============================================================================

final class FeatureMetrics
{
    /**
     * Конверсия: из тех, кто увидел фичу — сколько сделали целевое действие.
     *
     * @param string $feature   'checkout_v2'
     * @param string $action    'order_created'
     * @param int    $hours     период анализа
     */
    public static function getConversion(string $feature, string $action, int $hours = 24): array
    {
        $conn = Application::getConnection();

        // Сколько увидели фичу
        $exposed = $conn->query(
            "SELECT COUNT(DISTINCT USER_ID) AS cnt
             FROM acme_feature_exposure
             WHERE FEATURE = ? AND EXPOSED_AT > DATE_SUB(NOW(), INTERVAL ? HOUR)",
            [$feature, $hours]
        )->fetch()['cnt'];

        // Сколько сделали действие после просмотра фичи
        $converted = $conn->query(
            "SELECT COUNT(DISTINCT USER_ID) AS cnt
             FROM acme_feature_exposure fe
             JOIN b_sale_order o ON o.USER_ID = fe.USER_ID
             WHERE fe.FEATURE = ?
               AND o.DATE_INSERT > fe.EXPOSED_AT
               AND fe.EXPOSED_AT > DATE_SUB(NOW(), INTERVAL ? HOUR)",
            [$feature, $hours]
        )->fetch()['cnt'];

        $rate = $exposed > 0 ? round($converted / $exposed * 100, 2) : 0;

        return [
            'exposed' => (int)$exposed,
            'converted' => (int)$converted,
            'conversion_rate' => $rate,
        ];
    }
}

// ============================================================================
// 7. Таблица для метрики
// ============================================================================
//
// CREATE TABLE acme_feature_exposure (
//   ID INT AUTO_INCREMENT PRIMARY KEY,
//   USER_ID INT NOT NULL,
//   FEATURE VARCHAR(64) NOT NULL,
//   EXPOSED_AT DATETIME NOT NULL,
//   KEY idx_feature_time (FEATURE, EXPOSED_AT)
// ) ENGINE=InnoDB;
