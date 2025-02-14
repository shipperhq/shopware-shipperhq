<?php declare(strict_types=1);

namespace SHQ\RateProvider\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use ShipperHQ\GraphQL\Client\GraphQLClient;
use ShipperHQ\GraphQL\Request\SecureHeaders;
use ShipperHQ\GraphQL\Types\Input\RMSRatingInfo;

class ShipperHQApiClient
{
    private SystemConfigService $systemConfig;
    private LoggerInterface $logger;
    private GraphQLClient $client;

    public function __construct(
        SystemConfigService $systemConfig,
        LoggerInterface $logger
    ) {
        $this->systemConfig = $systemConfig;
        $this->logger = $logger;
        $this->client = new GraphQLClient();
    }

    public function getRates(array $context): array
    {
        $apiKey = $this->systemConfig->get('SHQRateProvider.config.apiKey');
        
        // Create secure headers
        $headers = new SecureHeaders($apiKey);
        
        // Create rating info object
        $ratingInfo = new RMSRatingInfo();
        // Set rating info properties based on context
        
        // Get shipping quote from ShipperHQ
        $response = $this->client->retrieveShippingQuote(
            $ratingInfo,
            $this->systemConfig->get('SHQRateProvider.config.apiEndpoint'),
            30,
            $headers
        );

        if (isset($response['errors'])) {
            $this->logger->error('ShipperHQ API Error', [
                'errors' => $response['errors']
            ]);
            return [];
        }

        return $response['data']['rates'] ?? [];
    }
} 