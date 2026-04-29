<?php declare(strict_types=1);

namespace SHQ\RateProvider\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ShipperHQException extends ShopwareHttpException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        array $parameters = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function connectionTestFailed(\Throwable $previous): self
    {
        return new self(
            'ShipperHQ connection test failed: {{ message }}',
            'SHIPPERHQ__CONNECTION_TEST_FAILED',
            Response::HTTP_BAD_GATEWAY,
            ['message' => $previous->getMessage()],
            $previous,
        );
    }

    public static function noMethodsReturned(): self
    {
        return new self(
            'No shipping methods returned from ShipperHQ',
            'SHIPPERHQ__NO_METHODS_RETURNED',
            Response::HTTP_BAD_GATEWAY,
        );
    }

    public static function refreshFailed(\Throwable $previous): self
    {
        return new self(
            'Failed to refresh shipping methods: {{ message }}',
            'SHIPPERHQ__REFRESH_FAILED',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ['message' => $previous->getMessage()],
            $previous,
        );
    }
}
