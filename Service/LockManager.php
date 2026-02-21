<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Service;

use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Model\Config;

class LockManager
{
    private const LOCK_FLAG_NAME = 'cache_warmup_running';

    /**
     * @var FlagManager
     */
    private $flagManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        FlagManager $flagManager,
        LoggerInterface $logger,
        Config $config
    ) {
        $this->flagManager = $flagManager;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Check if another instance is already running
     *
     * @return bool
     */
    public function isAlreadyRunning(): bool
    {
        $lockData = $this->flagManager->getFlagData(self::LOCK_FLAG_NAME);

        // If no lock exists, we can proceed
        if (!$lockData) {
            return false;
        }

        // Parse the stored timestamp
        $timestamp = (int)$lockData;

        // Get timeout from config instead of constant
        $timeout = (int)$this->config->getTimeout();

        // If the lock is too old, remove it and allow execution
        if (time() - $timestamp > $timeout) {
            $this->logger->info('Removing stale cache warmup lock');
            $this->removeLockFlag();
            return false;
        }

        // Lock exists and hasn't timed out - another instance is running
        return true;
    }

    /**
     * Set a lock flag to prevent concurrent execution
     *
     * @return void
     */
    public function createLockFlag(): void
    {
        // Store current timestamp in the flag
        $this->flagManager->saveFlag(self::LOCK_FLAG_NAME, (string)time());
    }

    /**
     * Remove the lock flag
     *
     * @return void
     */
    public function removeLockFlag(): void
    {
        $this->flagManager->deleteFlag(self::LOCK_FLAG_NAME);
    }
}
