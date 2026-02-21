<?php

declare(strict_types=1);
/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Cron;

use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Api\CacheWarmerInterface;
use Goat\TheCacheWarmer\Service\SitemapParser;
use Goat\TheCacheWarmer\Service\LockManager;

class WarmupFromSitemap
{
    /**
     * @var SitemapParser
     */
    private $sitemapParser;

    /**
     * @var CacheWarmerInterface
     */
    private $cacheWarmer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LockManager
     */
    private $lockManager;

    public function __construct(
        SitemapParser $sitemapParser,
        CacheWarmerInterface $cacheWarmer,
        LoggerInterface $logger,
        LockManager $lockManager
    ) {
        $this->sitemapParser = $sitemapParser;
        $this->cacheWarmer = $cacheWarmer;
        $this->logger = $logger;
        $this->lockManager = $lockManager;
    }

    /**
     * Process sitemaps from all stores and trigger cache warming
     *
     * @return void
     */
    public function execute(): void
    {
        // Check for existing lock flag to prevent concurrent executions
        if ($this->lockManager->isAlreadyRunning()) {
            $this->logger->info('Cache warmup process is already running, skipping this execution');
            return;
        }

        try {
            // Set a lock flag with timestamp and PID
            $this->lockManager->createLockFlag();

            try {
                // Parse sitemaps and generate CSV files for this specific store
                $csvFiles = $this->sitemapParser->parseSitemapsAndGenerateCsv();

                if (empty($csvFiles)) {
                    $this->logger->info("No CSV files generated from sitemaps");
                }

                foreach ($csvFiles as $csvPath) {
                    $this->logger->info('Starting cache warming for CSV file: ' . basename($csvPath));

                    // Trigger cache warming with the generated CSV file
                    $result = $this->cacheWarmer->warmUp($csvPath);

                    if ($result['status'] === 'success') {
                        $this->logger->info('Cache warming completed successfully for CSV: ' . basename($csvPath));
                    } else {
                        $this->logger->error(
                            'Cache warming failed for CSV ' . basename($csvPath) . ': ' .
                                ($result['message'] ?? 'Unknown error')
                        );
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error("Error during sitemap processing and cache warming " . $e->getMessage());
            } finally {
                $this->sitemapParser->deleteGeneratedCsvFiles();
            }
        } catch (\Exception $e) {
            $this->logger->error('Error during sitemap processing and cache warming: ' . $e->getMessage());
        } finally {
            // Always remove the lock flag
            $this->lockManager->removeLockFlag();
        }
    }
}
