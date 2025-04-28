<?php declare(strict_types=1);

namespace SHQ\RateProvider\Command;

use SHQ\RateProvider\Feature\Checkout\Service\ShippingRateCache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'shq:clear-shipping-rate-cache', description: 'Clears the ShipperHQ shipping rate cache')]
class ClearShippingRateCacheCommand extends Command
{
    private ShippingRateCache $rateCache;

    public function __construct(ShippingRateCache $rateCache)
    {
        parent::__construct();
        $this->rateCache = $rateCache;
    }

    protected function configure(): void
    {
        $this->setDescription('Clears the ShipperHQ shipping rate cache');
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
