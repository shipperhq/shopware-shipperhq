/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package SHQ\RateProvider
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

import template from './sw-order-details-state-card.html.twig';

Shopware.Component.override('sw-order-detail-details', {
    template,
    methods: {
        formatDate(dateString) {
            if (!dateString) return '';
            
            // Input format: "28-04-2025 19:11:00 -0400"
            const [datePart, timePart, offsetPart] = dateString.split(' ');
            const [day, month, year] = datePart.split('-');
            
            // Reconstruct in ISO format: "2025-04-28T19:11:00-04:00"
            const isoString = `${year}-${month}-${day}T${timePart}${offsetPart.slice(0,3)}:${offsetPart.slice(3)}`;
            const date = new Date(isoString);
            
            return date.toLocaleString();
        }
    }
});
