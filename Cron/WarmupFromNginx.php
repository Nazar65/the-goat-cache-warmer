<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Cron;

use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Api\CacheWarmerInterface;
use Goat\TheCacheWarmer\Service\UrlParser;
use Goat\TheCacheWarmer\Model\Config;

class WarmupFromNginx
{
    /**
     * @var UrlParser
     */
    private $urlParser;

    /**
     * @var CacheWarmerInterface
     */
    private $cacheWarmer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        UrlParser $urlParser,
        CacheWarmerInterface $cacheWarmer,
        LoggerInterface $logger,
        Config $config
    ) {
        $this->urlParser = $urlParser;
        $this->cacheWarmer = $cacheWarmer;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Generate CSV file from access log and trigger cache warming
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            // Get the path to nginx access log from configuration (store-specific)
            $logFilePath = $this->config->getLogFile();

            if (!$logFilePath) {
                $this->logger->warning('Access log file path is not configured in admin panel');
                return;
            }

            if (!file_exists($logFilePath)) {
                $this->logger->warning('Access log file does not exist: ' . $logFilePath);
                return;
            }

            // Generate CSV from access log
            $csvPath = $this->urlParser->parseLogAndGenerateCsv($logFilePath);

            $this->logger->info('Generated CSV file for cache warming: ' . $csvPath);

            // Trigger cache warming with the generated CSV file
            $result = $this->cacheWarmer->warmUp($csvPath);

            if ($result['status'] === 'success') {
                $this->logger->info('Cache warming completed successfully for generated CSV');
            } else {
                $this->logger->error(
                    'Cache warming failed for generated CSV: ' . ($result['message'] ?? 'Unknown error')
                );
            }
            $this->urlParser->deleteGeneratedCsv($csvPath);
        } catch (\Exception $e) {
            $this->logger->error('Error during CSV generation and cache warming: ' . $e->getMessage());
        }
    }
}
