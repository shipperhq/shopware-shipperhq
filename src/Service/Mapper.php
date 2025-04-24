<?php

namespace SHQ\RateProvider\Service;

use Psr\Log\LoggerInterface;
use ShipperHQ\WS\Rate\Response\RateResponse;
use ShipperHQ\WS\Rate\Response\ResponseSummary;

class Mapper
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function mapResponse($response): array
    {
        $this->logger->debug('SHIPPERHQ: Mapping response', [
            'response' => $response
        ]);

        $rateResponse = new RateResponse();

        // Map errors
        if (isset($response['errors']) && !empty($response['errors'])) {
            $rateResponse->setErrors($response['errors']);
        }

        // Map response summary
        if (isset($response['responseSummary'])) {
            $summary = new ResponseSummary();
            $summary->setDate($response['responseSummary']['date'] ?? null);
            $summary->setVersion($response['responseSummary']['version'] ?? null);
            $summary->setTransactionId($response['responseSummary']['transactionId'] ?? null);
            $summary->setStatus($response['responseSummary']['status'] ?? null);
            $summary->setProfileId($response['responseSummary']['profileId'] ?? null);
            $summary->setProfileName($response['responseSummary']['profileName'] ?? null);
            $summary->setCacheStatus($response['responseSummary']['cacheStatus'] ?? null);
            $summary->setExperimentName($response['responseSummary']['experimentName'] ?? null);
            $rateResponse->setResponseSummary($summary);
        }

        // Map merged rate response
        if (isset($response['mergedRateResponse'])) {
            $rateResponse->setMergedRateResponse($response['mergedRateResponse']);
        }

        // Map global settings
        if (isset($response['globalSettings'])) {
            $rateResponse->setGlobalSettings($response['globalSettings']);
        }

        // Map address validation response
        if (isset($response['addressValidationResponse'])) {
            $rateResponse->setAddressValidationResponse($response['addressValidationResponse']);
        }

        // Map carrier groups
        if (isset($response['carrierGroups'])) {
            $rateResponse->setCarrierGroups($response['carrierGroups']);
        }

        return $rateResponse->toArray();
    }
}
