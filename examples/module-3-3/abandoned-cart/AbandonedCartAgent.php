<?php
/**
 * Агент для проверки и отправки напоминаний о брошенных корзинах
 *
 * Запускается по расписанию (например, каждый час) и проверяет:
 * - Корзины, которые не обновлялись 24 часа
 * - Пользователям еще не отправлялось напоминание
 * - Отправляет email с промокодом
 */

namespace Local\Agents;

use Bitrix\Main\Mail\Event;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Loader;
use Bitrix\Sale;

class AbandonedCartAgent
{
    /** @var int Через сколько часов отправлять напоминание */
    const HOURS_BEFORE_REMINDER = 24;

    /** @var int Размер скидки по промокоду (%) */
    const PROMO_DISCOUNT = 10;

    /**
     * Основной метод агента
     *
     * @return string Строка для следующего запуска агента
     */
    public static function checkAbandonedCarts()
    {
        if (!Loader::includeModule('sale')) {
            return self::getAgentName();
        }

        // Получаем список брошенных корзин
        $abandonedCarts = self::getAbandonedCarts();

        foreach ($abandonedCarts as $cart) {
            self::sendReminderEmail($cart);
        }

        \AddMessage2Log(
            "Checked abandoned carts: found " . count($abandonedCarts),
            'abandoned_cart'
        );

        return self::getAgentName();
    }

    /**
     * Получить список брошенных корзин
     *
     * @return array Массив данных о корзинах
     */
    private static function getAbandonedCarts(): array
    {
        $result = [];
        $limitDate = new DateTime();
        $limitDate->add('-' . self::HOURS_BEFORE_REMINDER . ' hours');

        // Получаем пользователей с непустыми корзинами
        $baskets = Sale\Basket::getList([
            'filter' => [
                '>QUANTITY' => 0,
                '<DATE_UPDATE' => $limitDate
            ],
            'group' => ['FUSER_ID']
        ]);

        while ($basket = $baskets->fetch()) {
            $fuserId = $basket['FUSER_ID'];

            // Проверяем, не отправляли ли уже напоминание
            if (self::wasReminderSent($fuserId)) {
                continue;
            }

            // Получаем данные пользователя
            $user = self::getUserByFuserId($fuserId);
            if (!$user || !$user['EMAIL']) {
                continue;
            }

            // Получаем корзину пользователя
            $userBasket = self::getUserBasket($fuserId);
            if (!$userBasket || $userBasket['ITEMS_COUNT'] === 0) {
                continue;
            }

            $result[] = [
                'FUSER_ID' => $fuserId,
                'USER' => $user,
                'BASKET' => $userBasket
            ];
        }

        return $result;
    }

    /**
     * Получить корзину пользователя
     *
     * @param int $fuserId ID корзины
     * @return array|null Данные корзины
     */
    private static function getUserBasket(int $fuserId): ?array
    {
        try {
            $basket = Sale\Basket::loadItemsForFUser($fuserId, SITE_ID);

            if ($basket->isEmpty()) {
                return null;
            }

            $items = [];
            $total = 0;
            $count = 0;

            /** @var Sale\BasketItem $basketItem */
            foreach ($basket as $basketItem) {
                $price = $basketItem->getPrice();
                $quantity = $basketItem->getQuantity();
                $sum = $price * $quantity;

                $items[] = [
                    'NAME' => $basketItem->getField('NAME'),
                    'PRICE' => $price,
                    'QUANTITY' => $quantity,
                    'SUM' => $sum,
                    'PICTURE' => $basketItem->getField('PICTURE'),
                    'DETAIL_PAGE_URL' => $basketItem->getField('DETAIL_PAGE_URL')
                ];

                $total += $sum;
                $count += $quantity;
            }

            return [
                'ITEMS' => $items,
                'TOTAL' => $total,
                'ITEMS_COUNT' => $count,
                'CURRENCY' => $basket->getCurrency()
            ];
        } catch (\Exception $e) {
            \AddMessage2Log("Failed to get basket: " . $e->getMessage(), 'abandoned_cart');
            return null;
        }
    }

    /**
     * Получить пользователя по FUSER_ID
     *
     * @param int $fuserId ID корзины
     * @return array|null Данные пользователя
     */
    private static function getUserByFuserId(int $fuserId): ?array
    {
        // Получаем USER_ID по FUSER_ID из таблицы b_sale_fuser
        $fuser = Sale\Internals\FuserTable::getById($fuserId)->fetch();

        if (!$fuser || !$fuser['USER_ID']) {
            return null;
        }

        $user = \CUser::GetByID($fuser['USER_ID'])->Fetch();
        return $user ?: null;
    }

    /**
     * Проверить, отправлялось ли уже напоминание
     *
     * @param int $fuserId ID корзины
     * @return bool
     */
    private static function wasReminderSent(int $fuserId): bool
    {
        // Проверяем в кастомной таблице или через опции
        $option = \COption::GetOptionString('sale', 'abandoned_cart_sent_' . $fuserId, '');

        if (!$option) {
            return false;
        }

        // Проверяем, прошло ли 7 дней с последней отправки
        $lastSent = \DateTime::createFromFormat('Y-m-d H:i:s', $option);
        $now = new \DateTime();
        $diff = $now->diff($lastSent);

        return $diff->days < 7;
    }

    /**
     * Отметить, что напоминание отправлено
     *
     * @param int $fuserId ID корзины
     */
    private static function markReminderSent(int $fuserId)
    {
        $now = date('Y-m-d H:i:s');
        \COption::SetOptionString('sale', 'abandoned_cart_sent_' . $fuserId, $now);
    }

    /**
     * Отправить email-напоминание
     *
     * @param array $cart Данные корзины
     */
    private static function sendReminderEmail(array $cart)
    {
        $user = $cart['USER'];
        $basket = $cart['BASKET'];

        // Генерируем промокод
        $promoCode = self::generatePromoCode($user['ID']);

        // Формируем HTML со списком товаров
        $cartItemsHtml = self::buildCartItemsHtml($basket['ITEMS']);

        // Получаем данные сайта
        $site = \CSite::GetByID(SITE_ID)->Fetch();

        // Формируем поля для письма
        $fields = [
            'USER_NAME' => $user['NAME'] ?: $user['LOGIN'],
            'USER_EMAIL' => $user['EMAIL'],
            'CART_ITEMS' => $cartItemsHtml,
            'CART_TOTAL' => \CCurrencyLang::CurrencyFormat($basket['TOTAL'], $basket['CURRENCY']),
            'CART_COUNT' => $basket['ITEMS_COUNT'],
            'CART_URL' => '/personal/cart/',
            'PROMO_CODE' => $promoCode,
            'PROMO_DISCOUNT' => self::PROMO_DISCOUNT,
            'SITE_NAME' => $site['NAME'],
            'SERVER_NAME' => $site['SERVER_NAME'] ?: $_SERVER['HTTP_HOST']
        ];

        // Отправляем письмо
        $result = Event::send([
            'EVENT_NAME' => 'ABANDONED_CART_REMINDER',
            'LID' => SITE_ID,
            'C_FIELDS' => $fields
        ]);

        if ($result->isSuccess()) {
            self::markReminderSent($cart['FUSER_ID']);

            \AddMessage2Log(
                "Abandoned cart reminder sent to user #{$user['ID']} ({$user['EMAIL']})",
                'abandoned_cart'
            );
        } else {
            \AddMessage2Log(
                "Failed to send reminder: " . implode(', ', $result->getErrorMessages()),
                'abandoned_cart',
                'ERROR'
            );
        }
    }

    /**
     * Построить HTML со списком товаров
     *
     * @param array $items Товары корзины
     * @return string HTML
     */
    private static function buildCartItemsHtml(array $items): string
    {
        $html = '';

        foreach ($items as $item) {
            $priceFormatted = \CCurrencyLang::CurrencyFormat($item['PRICE'], 'RUB');
            $sumFormatted = \CCurrencyLang::CurrencyFormat($item['SUM'], 'RUB');

            $html .= '<div style="border-bottom: 1px solid #eee; padding: 15px 0;">';
            $html .= '<div style="font-weight: bold;">' . htmlspecialchars($item['NAME']) . '</div>';
            $html .= '<div style="color: #666; font-size: 14px;">';
            $html .= htmlspecialchars($item['QUANTITY']) . ' × ' . $priceFormatted . ' = ' . $sumFormatted;
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Генерация промокода для пользователя
     *
     * @param int $userId ID пользователя
     * @return string Промокод
     */
    private static function generatePromoCode(int $userId): string
    {
        // Генерируем уникальный промокод
        $code = 'COMEBACK' . $userId . mt_rand(100, 999);

        // Создаем купон в системе (если модуль sale подключен)
        if (Loader::includeModule('sale')) {
            try {
                $discount = \Bitrix\Sale\Discount\DiscountCouponsManager::add([
                    'COUPON' => $code,
                    'TYPE' => \Bitrix\Sale\Internals\DiscountCouponTable::TYPE_ONE_TIME,
                    'ACTIVE' => 'Y',
                    'DESCRIPTION' => 'Промокод для возврата к брошенной корзине'
                ]);

                // Создаем правило скидки
                // (упрощенный вариант, в реальности нужно больше логики)
            } catch (\Exception $e) {
                \AddMessage2Log("Failed to create promo code: " . $e->getMessage(), 'abandoned_cart');
            }
        }

        return $code;
    }

    /**
     * Имя агента для повторного запуска
     *
     * @return string
     */
    private static function getAgentName(): string
    {
        return '\\' . __CLASS__ . '::checkAbandonedCarts();';
    }

    /**
     * Установка агента
     * Вызывать один раз при установке функционала
     */
    public static function install()
    {
        // Проверяем, не установлен ли уже агент
        $existingAgent = \CAgent::GetList(
            [],
            ['NAME' => self::getAgentName()]
        )->Fetch();

        if ($existingAgent) {
            echo "Агент уже установлен (ID: {$existingAgent['ID']})\n";
            return;
        }

        // Добавляем агент - запуск каждый час
        $agentId = \CAgent::AddAgent(
            self::getAgentName(),
            '',
            'N',
            3600, // Каждый час
            '',
            'Y',
            '',
            30
        );

        if ($agentId) {
            echo "✓ Агент установлен (ID: {$agentId})\n";
            echo "Запуск: каждый час\n";
        } else {
            echo "✗ Ошибка установки агента\n";
        }
    }

    /**
     * Удаление агента
     */
    public static function uninstall()
    {
        \CAgent::RemoveAgent(self::getAgentName(), '');
        echo "✓ Агент удален\n";
    }
}
