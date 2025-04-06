<?php

namespace ShipperHQ\WS\Rate\Response\Shipping;

class CarrierGroupResponse
{
    private string $carrierGroupId;
    private string $carrierGroupName;
    private array $carrierRates = [];

    public function setCarrierGroupId(string $carrierGroupId): void
    {
        $this->carrierGroupId = $carrierGroupId;
    }

    public function setCarrierGroupName(string $carrierGroupName): void
    {
        $this->carrierGroupName = $carrierGroupName;
    }

    public function setCarrierRates(array $carrierRates): void
    {
        $this->carrierRates = $carrierRates;
    }

    public function getCarrierGroupId(): string
    {
        return $this->carrierGroupId;
    }

    public function getCarrierGroupName(): string
    {
        return $this->carrierGroupName;
    }

    public function getCarrierRates(): array
    {
        return $this->carrierRates;
    }
} 