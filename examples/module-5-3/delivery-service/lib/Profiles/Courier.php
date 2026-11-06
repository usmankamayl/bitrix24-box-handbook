<?php
// examples/module-5-3/delivery-service/lib/Profiles/Courier.php
// Профиль доставки: курьер до двери.

namespace Acme\Sale\Delivery\Profiles;

use Acme\Sale\Delivery\AcmeDeliveryService;

class Courier extends AcmeDeliveryService
{
    public static function getClassTitle(): string
    {
        return 'Курьер до двери';
    }

    public static function getClassDescription(): string
    {
        return 'Курьерская доставка до двери';
    }
}
