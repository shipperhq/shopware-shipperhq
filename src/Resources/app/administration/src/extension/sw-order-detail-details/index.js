Shopware.Component.override('sw-order-detail-details', {
    template: `
        {% block sw_order_detail_details %}
            {% parent %}
            
            <sw-card
                class="sw-order-detail-details__shipperhq"
                position-identifier="sw-order-detail-details-shipperhq"
                title="ShipperHQ Information"
            >
                <pre style="white-space: pre-wrap;">{{ orderDeliveries }}</pre>
            </sw-card>
        {% endblock %}
    `,

    computed: {
        orderDeliveries() {
            if (!this.order || !this.order.deliveries) {
                return 'No delivery information available';
            }
            return JSON.stringify(this.order.deliveries, null, 2);
        }
    }
});
