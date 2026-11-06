<?php

namespace BitrixCourse\BasketDiscount;

/**
 * Эта скидка применяется в коде, а не через стандартный движок правил
 * корзины (см. README) — значит, в штатных отчётах по скидкам
 * (sale.discount) её не видно. Пишем отдельный аудит-лог для
 * финансового контроля: какие заказы содержат эту скидку и на сколько.
 */
class DiscountAuditLog
{
    public static function write(int $orderId, array $discountedItems): void
    {
        \AddMessage2Log(
            sprintf(
                'Применена скидка "каждый N-й товар" в заказе #%d: %s',
                $orderId,
                json_encode($discountedItems, JSON_UNESCAPED_UNICODE)
            ),
            'bitrix_course'
        );
    }
}
