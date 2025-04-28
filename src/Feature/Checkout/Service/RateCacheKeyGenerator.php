<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Checkout\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class RateCacheKeyGenerator
{
    private const CACHE_KEY = 'shipperhq_shipping_rates';

    public function generateKey(Cart $cart, SalesChannelContext $context): string
    {
        $addressHash = $this->getAddressHash($context);
        $cartItemsHash = $this->getCartItemsHash($cart);
        $currencyId = $context->getCurrency()->getId();
        $customerGroupId = $context->getCurrentCustomerGroup()->getId();

        return self::CACHE_KEY . '_' . md5(
            $addressHash . '_' . 
            $cartItemsHash . '_' . 
            $currencyId . '_' . 
            $customerGroupId
        );
    }

    private function getAddressHash(SalesChannelContext $context): string
    {
        $customer = $context->getCustomer();
        
        if (!$customer) {
            return 'guest';
        }
        
        $shippingAddress = $customer->getActiveShippingAddress();
        
        if (!$shippingAddress) {
            return 'no_address';
        }
        
        return md5(
            $shippingAddress->getCountry()->getIso() . '_' .
            ($shippingAddress->getCountryState() ? $shippingAddress->getCountryState()->getShortCode() : '') . '_' .
            $shippingAddress->getZipcode() . '_' .
            $shippingAddress->getCity() . '_' .
            $shippingAddress->getStreet()
        );
    }

    private function getCartItemsHash(Cart $cart): string
    {
        $itemsData = [];
        
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }
            
            $itemsData[] = [
                'id' => $lineItem->getId(),
                'quantity' => $lineItem->getQuantity(),
                'price' => $lineItem->getPrice() ? $lineItem->getPrice()->getTotalPrice() : 0,
                'weight' => $this->getItemWeight($lineItem)
            ];
        }
        
        usort($itemsData, function ($a, $b) {
            return strcmp($a['id'], $b['id']);
        });
        
        return md5(json_encode($itemsData));
    }

    private function getItemWeight(LineItem $lineItem): float
    {
        if ($lineItem->getDeliveryInformation() && $lineItem->getDeliveryInformation()->getWeight()) {
            return $lineItem->getDeliveryInformation()->getWeight();
        }
        
        if ($lineItem->hasPayloadValue('weight')) {
            return (float) $lineItem->getPayloadValue('weight');
        }
        
        return 0.0;
    }
}
