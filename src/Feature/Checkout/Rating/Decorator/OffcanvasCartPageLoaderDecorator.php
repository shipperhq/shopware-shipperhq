<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Checkout\Rating\Decorator;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Symfony\Component\HttpFoundation\Request;
use SHQ\RateProvider\Feature\Checkout\Service\ShippingRateCache;

class OffcanvasCartPageLoaderDecorator extends OffcanvasCartPageLoader
{
    public function __construct(
        private readonly OffcanvasCartPageLoader $decorated,
        private readonly ShippingRateCache $rateCache,
        private readonly CartService $cartService,
        private readonly LoggerInterface $logger,
    ) {}

    public function load(Request $request, SalesChannelContext $salesChannelContext): OffcanvasCartPage
    {
        $page = $this->decorated->load($request, $salesChannelContext);
        $cart = $page->getCart();
        if ($cart !== null) {
            $permissions = [
                'skipPromotion' => true,
                'skipDeliveryRecalculation' => true,
                'skipProductRecalculation' => true,
                'skipDiscountRecalculation' => true,
                'skipDeliveryPriceRecalculation' => true
            ];
            $cart->setBehavior(new CartBehavior($permissions));
        }
        $shippingMethods = $page->getShippingMethods();
        $rates = $this->rateCache->getRates($cart, $salesChannelContext);
        $removedCount = 0;
        foreach ($shippingMethods as $key => $shippingMethod) {
            $customFields = $shippingMethod->getCustomFields();
            $isShipperHQ = $customFields !== null && isset($customFields['shipperhq_carrier_code']) && $customFields['shipperhq_carrier_code'] !== null;

            if ($isShipperHQ && !isset($rates[$shippingMethod->getId()])) {
                $shippingMethods->remove($key);
                $removedCount++;
            }
        }
        $this->logger->debug('SHIPPERHQ: OffcanvasCartPageLoaderDecorator filtered shipping methods', [
            'filtered_methods_count' => $shippingMethods->count(),
            'removed_methods_count' => $removedCount
        ]);
        $page->setShippingMethods($shippingMethods);
        return $page;
    }
} 