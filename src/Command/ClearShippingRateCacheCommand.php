<?php declare(strict_types=1);

namespace SHQ\RateProvider\Command;

use SHQ\RateProvider\Service\ShippingRateCache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearShippingRateCacheCommand extends Command
{
    public static $defaultName = 'shq:clear-shipping-rate-cache';
    public static $defaultDescription = 'Clears the ShipperHQ shipping rate cache';

    private ShippingRateCache $rateCache;

    public function __construct(ShippingRateCache $rateCache)
    {
        parent::__construct();
        $this->rateCache = $rateCache;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->rateCache->clearCache();
            $io->success('Shipping rate cache cleared successfully');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to clear shipping rate cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 