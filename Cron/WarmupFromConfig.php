<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Cron;

use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Api\CacheWarmerInterface;
use Goat\TheCacheWarmer\Model\Config;

class WarmupFromConfig
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CacheWarmerInterface
     */
    private $cacheWarmer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        CacheWarmerInterface $cacheWarmer,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->cacheWarmer = $cacheWarmer;
        $this->logger = $logger;
    }

    /**
     * Process CSV files configured in admin panel and trigger cache warming
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            // Get the CSV file paths from configuration (store-specific)
            $csvFilePaths = $this->config->getCsvFiles();

            if (!$csvFilePaths) {
                $this->logger->info('No CSV files configured in admin panel');
                return;
            }

            // Split comma-separated file paths
            $csvPaths = array_map('trim', explode(',', $csvFilePaths));

            foreach ($csvPaths as $csvPath) {
                if (!file_exists($csvPath)) {
                    $this->logger->warning('CSV file does not exist: ' . $csvPath);
                    continue;
                }

                $this->logger->info('Starting cache warming for CSV file: ' . basename($csvPath));

                // Trigger cache warming with the configured CSV file
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
            $this->logger->error('Error during config-based cache warming: ' . $e->getMessage());
        }
    }
}
