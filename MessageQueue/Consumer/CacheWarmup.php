<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\MessageQueue\Consumer;

use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Cron\WarmupFromSitemap;
use Goat\TheCacheWarmer\Model\Config;

readonly class CacheWarmup
{
    /**
     * @param WarmupFromSitemap $warmupFromSitemap
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private WarmupFromSitemap $warmupFromSitemap,
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    /**
     * Process cache warming request from queue
     *
     * @return void
     */
    public function process(): void
    {

        // Check if the module is enabled in configuration
        if (!$this->config->isEnabled()) {
            $this->logger->info('Cache warmer module is disabled, skipping execution');
            return;
        }

        try {

            // Add delay if configured (to allow Fastly or other cache systems to clear properly)
            $delay = (int) $this->config->getDelay();
            if ($delay > 0) {
                $this->logger->info("Waiting {$delay} seconds before starting cache warmup");
                sleep($delay);
            }

            $this->logger->info('Starting cache warming from sitemap source');
            $this->warmupFromSitemap->execute();


            $this->logger->info('Cache warming completed successfully via queue');
        } catch (\Exception $e) {
            $this->logger->error('Error during cache warm-up via queue: ' . $e->getMessage());
        }
    }
}
