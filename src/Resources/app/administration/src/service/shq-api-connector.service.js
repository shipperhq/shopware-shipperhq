/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Calendar
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API end point "shipperhq/test-connection"
 * @class
 * @extends ApiService
 */
class ShipperHQApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'shq-api-test') {
        super(httpClient, loginService, apiEndpoint);
    }

    testConnection(credentials) {
        const headers = this.getBasicHeaders();

        console.log("testConnection inside service");
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/test-connection`,
                credentials,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    refreshMethods(credentials) {
        const headers = this.getBasicHeaders();

        console.log("refreshMethods");
        return this.httpClient
            .post(
                `_action/${this.getApiBasePath()}/refresh-methods`,
                credentials,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }


}

export default ShipperHQApiService; 
