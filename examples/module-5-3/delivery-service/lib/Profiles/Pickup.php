<?php
// examples/module-5-3/delivery-service/lib/Profiles/Pickup.php
// Профиль доставки: пункт выдачи.

namespace Acme\Sale\Delivery\Profiles;

use Acme\Sale\Delivery\AcmeDeliveryService;

class Pickup extends AcmeDeliveryService
{
    public static function getClassTitle(): string
    {
        return 'Пункт выдачи';
    }

    public static function getClassDescription(): string
    {
        return 'Самовывоз из пункта выдачи';
    }
}
