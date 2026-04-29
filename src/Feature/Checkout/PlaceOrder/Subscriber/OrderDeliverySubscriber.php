<?php declare(strict_types=1);
/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package SHQ\RateProvider
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Feature\Checkout\PlaceOrder\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use SHQ\RateProvider\Feature\Checkout\Service\ShippingRateCache;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDeliverySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ShippingRateCache $rateCache,
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'onCartConverted',
        ];
    }

    public function onCartConverted(CartConvertedEvent $event): void
    {
        $cart = $event->getCart();
        $context = $event->getSalesChannelContext();
        $convertedCart = $event->getConvertedCart();

        $rates = $this->rateCache->getRates($cart, $context);

        if (empty($rates) || empty($convertedCart['deliveries'])) {
            return;
        }

        foreach ($convertedCart['deliveries'] as $key => $delivery) {
            $shippingMethodId = $delivery['shippingMethodId'] ?? null;

            if (!$shippingMethodId || !isset($rates[$shippingMethodId])) {
                continue;
            }

            $rate = $rates[$shippingMethodId];
            $deliveryDate = $rate['delivery_date'] ?? null;
            $dispatchDate = $rate['dispatch_date'] ?? null;

            if (!$deliveryDate) {
                continue;
            }

            $convertedCart['deliveries'][$key]['customFields'] = array_merge(
                $convertedCart['deliveries'][$key]['customFields'] ?? [],
                [
                    'shipperhq_delivery_date' => $deliveryDate,
                    'shipperhq_dispatch_date' => $dispatchDate,
                ]
            );
        }

        $event->setConvertedCart($convertedCart);
    }
}
