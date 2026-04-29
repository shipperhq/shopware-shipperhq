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

namespace SHQ\RateProvider\Tests\Unit\Logger;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use SHQ\RateProvider\Logger\ConditionalDebugLogger;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConditionalDebugLoggerTest extends TestCase
{
    private ConditionalDebugLogger $logger;
    private LoggerInterface|MockObject $innerLogger;
    private SystemConfigService|MockObject $systemConfig;

    protected function setUp(): void
    {
        $this->innerLogger = $this->createMock(LoggerInterface::class);
        $this->systemConfig = $this->createMock(SystemConfigService::class);

        $this->logger = new ConditionalDebugLogger(
            $this->innerLogger,
            $this->systemConfig
        );
    }

    public function testDebugIsForwardedWhenDebugEnabled(): void
    {
        // Arrange
        $message = 'Debug message';
        $context = ['key' => 'value'];

        $this->systemConfig->expects($this->once())
            ->method('get')
            ->with('SHQRateProvider.config.debug')
            ->willReturn(true);

        $this->innerLogger->expects($this->once())
            ->method('debug')
            ->with($message, $context);

        // Act
        $this->logger->debug($message, $context);
    }

    public function testDebugIsSuppressedWhenDebugDisabled(): void
    {
        // Arrange
        $message = 'Debug message';
        $context = ['key' => 'value'];

        $this->systemConfig->expects($this->once())
            ->method('get')
            ->with('SHQRateProvider.config.debug')
            ->willReturn(false);

        $this->innerLogger->expects($this->never())
            ->method('debug');

        // Act
        $this->logger->debug($message, $context);
    }

    public function testDebugIsSuppressedWhenDebugNull(): void
    {
        // Arrange
        $message = 'Debug message';
        $context = [];

        $this->systemConfig->expects($this->once())
            ->method('get')
            ->with('SHQRateProvider.config.debug')
            ->willReturn(null);

        $this->innerLogger->expects($this->never())
            ->method('debug');

        // Act
        $this->logger->debug($message, $context);
    }

    public function testErrorAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Error message';
        $context = ['error' => 'details'];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('error')
            ->with($message, $context);

        // Act
        $this->logger->error($message, $context);
    }

    public function testWarningAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Warning message';
        $context = [];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('warning')
            ->with($message, $context);

        // Act
        $this->logger->warning($message, $context);
    }

    public function testInfoAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Info message';
        $context = ['info' => 'data'];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('info')
            ->with($message, $context);

        // Act
        $this->logger->info($message, $context);
    }

    public function testNoticeAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Notice message';
        $context = [];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('notice')
            ->with($message, $context);

        // Act
        $this->logger->notice($message, $context);
    }

    public function testCriticalAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Critical message';
        $context = [];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('critical')
            ->with($message, $context);

        // Act
        $this->logger->critical($message, $context);
    }

    public function testAlertAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Alert message';
        $context = [];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('alert')
            ->with($message, $context);

        // Act
        $this->logger->alert($message, $context);
    }

    public function testEmergencyAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Emergency message';
        $context = [];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('emergency')
            ->with($message, $context);

        // Act
        $this->logger->emergency($message, $context);
    }

    public function testLogWithDebugLevelRespectsToggleWhenEnabled(): void
    {
        // Arrange
        $message = 'Debug via log method';
        $context = [];

        $this->systemConfig->expects($this->once())
            ->method('get')
            ->with('SHQRateProvider.config.debug')
            ->willReturn(true);

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::DEBUG, $message, $context);

        // Act
        $this->logger->log(LogLevel::DEBUG, $message, $context);
    }

    public function testLogWithDebugLevelRespectsToggleWhenDisabled(): void
    {
        // Arrange
        $message = 'Debug via log method';
        $context = [];

        $this->systemConfig->expects($this->once())
            ->method('get')
            ->with('SHQRateProvider.config.debug')
            ->willReturn(false);

        $this->innerLogger->expects($this->never())
            ->method('log');

        // Act
        $this->logger->log(LogLevel::DEBUG, $message, $context);
    }

    public function testLogWithNonDebugLevelAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Error via log method';
        $context = ['error' => 'context'];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::ERROR, $message, $context);

        // Act
        $this->logger->log(LogLevel::ERROR, $message, $context);
    }

    public function testLogWithInfoLevelAlwaysPassesThrough(): void
    {
        // Arrange
        $message = 'Info via log method';
        $context = [];

        $this->systemConfig->expects($this->never())
            ->method('get');

        $this->innerLogger->expects($this->once())
            ->method('log')
            ->with(LogLevel::INFO, $message, $context);

        // Act
        $this->logger->log(LogLevel::INFO, $message, $context);
    }
}
