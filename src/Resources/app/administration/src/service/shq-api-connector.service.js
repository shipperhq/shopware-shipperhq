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

        console.log("testConnection");
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

        console.log("testConnection");
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