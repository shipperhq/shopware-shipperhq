<?php declare(strict_types=1);
/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Calendar
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Feature\ProductData\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldService
{
    private EntityRepository $customFieldSetRepository;

    public function __construct(EntityRepository $customFieldSetRepository)
    {
        $this->customFieldSetRepository = $customFieldSetRepository;
    }

    public function createCustomFieldSets(Context $context): void
    {
        // Check if custom field sets already exist
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'shipperhq_product'));
        $existingProductSet = $this->customFieldSetRepository->search($criteria, $context)->first();

        // Create product custom field set if it doesn't exist
        if (!$existingProductSet) {
            $this->customFieldSetRepository->upsert([
                [
                    'name' => 'shipperhq_product',
                    'config' => [
                        'label' => [
                            'en-GB' => 'ShipperHQ Product Settings',
                            'de-DE' => 'ShipperHQ Produkteinstellungen',
                            Defaults::LANGUAGE_SYSTEM => 'ShipperHQ Product Settings'
                        ]
                    ],
                    'customFields' => [
                        [
                            'name' => 'shipperhq_shipping_group',
                            'type' => CustomFieldTypes::TEXT,
                            'config' => [
                                'label' => [
                                    'en-GB' => 'Shipping Group',
                                    'de-DE' => 'Versandgruppe',
                                    Defaults::LANGUAGE_SYSTEM => 'Shipping Group'
                                ],
                                'helpText' => [
                                    'en-GB' => 'Group products for shipping purposes',
                                    'de-DE' => 'Produkte für Versandzwecke gruppieren',
                                    Defaults::LANGUAGE_SYSTEM => 'Group products for shipping purposes'
                                ],
                                'customFieldPosition' => 1
                            ]
                        ],
                        [
                            'name' => 'shipperhq_warehouse',
                            'type' => CustomFieldTypes::TEXT,
                            'config' => [
                                'label' => [
                                    'en-GB' => 'Warehouse',
                                    'de-DE' => 'Lager',
                                    Defaults::LANGUAGE_SYSTEM => 'Warehouse'
                                ],
                                'helpText' => [
                                    'en-GB' => 'Warehouse location for this product',
                                    'de-DE' => 'Lagerstandort für dieses Produkt',
                                    Defaults::LANGUAGE_SYSTEM => 'Warehouse location for this product'
                                ],
                                'customFieldPosition' => 2
                            ]
                        ],
                        [
                            'name' => 'ship_separately',
                            'type' => CustomFieldTypes::BOOL,
                            'config' => [
                                'label' => [
                                    'en-GB' => 'Ship Separately',
                                    'de-DE' => 'Getrennt versenden',
                                    Defaults::LANGUAGE_SYSTEM => 'Ship Separately'
                                ],
                                'helpText' => [
                                    'en-GB' => 'Ship this item in a separate package',
                                    'de-DE' => 'Dieses Produkt in einem separaten Paket versenden',
                                    Defaults::LANGUAGE_SYSTEM => 'Ship this item in a separate package'
                                ],
                                'customFieldPosition' => 3
                            ]
                        ],
                        [
                            'name' => 'shipperhq_dim_group',
                            'type' => CustomFieldTypes::TEXT,
                            'config' => [
                                'label' => [
                                    'en-GB' => 'Dimension Group',
                                    'de-DE' => 'Dimensionengruppe',
                                    Defaults::LANGUAGE_SYSTEM => 'Dimension Group'
                                ],
                                'helpText' => [
                                    'en-GB' => 'Group products with similar dimensions',
                                    'de-DE' => 'Produkte mit ähnlichen Abmessungen gruppieren',
                                    Defaults::LANGUAGE_SYSTEM => 'Group products with similar dimensions'
                                ],
                                'customFieldPosition' => 4
                            ]
                        ],
                    ],
                    'relations' => [
                        ['entityName' => 'product']
                    ]
                ]
            ], $context);
        }
    }
}
