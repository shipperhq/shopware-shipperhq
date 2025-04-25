<?php declare(strict_types=1);

namespace SHQ\RateProvider\Feature\Order\Decorator;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

class OrderLineItemDecorator extends OrderLineItemDefinition
{
    public function getEntityName(): string
    {
        return 'order_line_item';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();
        $fields->add(
            (new StringField('shipperhq_delivery_date', 'shipperhqDeliveryDate'))->addFlags(new Runtime())
        );

        return $fields;
    }
}
