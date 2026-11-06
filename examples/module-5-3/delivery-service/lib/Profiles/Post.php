<?php
// examples/module-5-3/delivery-service/lib/Profiles/Post.php
// Профиль доставки: Почта России.

namespace Acme\Sale\Delivery\Profiles;

use Acme\Sale\Delivery\AcmeDeliveryService;

class Post extends AcmeDeliveryService
{
    public static function getClassTitle(): string
    {
        return 'Почта России';
    }

    public static function getClassDescription(): string
    {
        return 'Доставка почтой России';
    }
}
