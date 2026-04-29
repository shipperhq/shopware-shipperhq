<?php declare(strict_types=1);
/*
 * ShipperHQ
 *
 * @category ShipperHQ
 * @package SHQ\RateProvider
 * @copyright Copyright (c) 2025 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace SHQ\RateProvider\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * ConditionalDebugLogger
 *
 * A logger decorator that gates debug()-level log messages behind the
 * SHQRateProvider.config.debug admin toggle. All other log levels pass through unconditionally.
 *
 * DOMAIN: Debug logging in production can be expensive with ~60 debug() calls across the plugin.
 * This decorator allows admins to enable/disable verbose logging without code changes.
 */
class ConditionalDebugLogger implements LoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $innerLogger,
        private readonly SystemConfigService $systemConfig
    ) {}

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->innerLogger->emergency($message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->innerLogger->alert($message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->innerLogger->critical($message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->innerLogger->error($message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->innerLogger->warning($message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->innerLogger->notice($message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->innerLogger->info($message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        if ($this->isDebugEnabled()) {
            $this->innerLogger->debug($message, $context);
        }
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if ($level === LogLevel::DEBUG && !$this->isDebugEnabled()) {
            return;
        }

        $this->innerLogger->log($level, $message, $context);
    }

    private function isDebugEnabled(): bool
    {
        return (bool) $this->systemConfig->get('SHQRateProvider.config.debug');
    }
}
