<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\App\State;

class SitemapParser
{
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var State
     */
    private $appState;

    public function __construct(
        DirectoryList $directoryList,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        State $appState
    ) {
        $this->directoryList = $directoryList;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->appState = $appState;
    }

    /**
     * Parse sitemap.xml from each store and generate CSV files for cache warming
     *
     * @return array List of generated CSV file paths
     */
    public function parseSitemapsAndGenerateCsv(): array
    {
        $csvFiles = [];

        try {
            // Get all stores
            $stores = $this->storeManager->getStores();

            foreach ($stores as $store) {
                if (!$store->getIsActive()) {
                    continue;
                }

                try {
                    // Start emulation for the current store to ensure proper context
                    $storeId = $store->getId();
                    $storeBaseUrl = $store->getBaseUrl();

                    // Get sitemap URL from robots.txt
                    $sitemapUrl = $this->getSitemapUrlFromRobotsTxt($storeBaseUrl);

                    if (!$sitemapUrl) {
                        $this->logger->warning("No sitemap found for store ID {$storeId} in robots.txt: {$storeBaseUrl}");
                        continue;
                    }

                    // Fetch sitemap XML content
                    $xmlContent = $this->fetchFileContent($sitemapUrl);

                    if ($xmlContent === false) {
                        $this->logger->warning("Failed to fetch sitemap for store ID {$storeId}: {$sitemapUrl}");
                        continue;
                    }

                    try {
                        // Parse XML content
                        $xml = simplexml_load_string($xmlContent);
                    } catch (\Exception $e) {
                        $this->logger->warning("Failed to load xml file by url: {$storeId}: {$sitemapUrl}");
                        continue;
                    }

                    if ($xml === false) {
                        $this->logger->warning("Failed to parse XML for store ID {$storeId}: {$sitemapUrl}");
                        continue;
                    }

                    // Extract URLs from sitemap
                    $urls = [];
                    foreach ($xml->url as $urlElement) {
                        $loc = (string)$urlElement->loc;

                        // Only include non-empty and valid URLs
                        if (!empty($loc)) {
                            $urls[] = $loc;
                        }
                    }

                    if (empty($urls)) {
                        $this->logger->info("No URLs found in sitemap for store ID {$storeId}: {$sitemapUrl}");
                        continue;
                    }

                    // Create CSV file path and ensure directory exists
                    $mediaPath = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
                    $csvDirectory = rtrim($mediaPath, '/') . '/cacheWarmerCsvFiles/';

                    // Ensure the directory exists
                    if (!is_dir($csvDirectory)) {
                        mkdir($csvDirectory, 0755, true);
                    }

                    $csvFileName = "sitemap_urls_store_{$storeId}_" . date('Y-m-d_H-i-s') . '.csv';
                    $csvFilePath = $csvDirectory . $csvFileName;

                    // Create CSV file
                    $csvHandle = fopen($csvFilePath, 'w');

                    if (!$csvHandle) {
                        throw new \Exception("Could not create CSV file: " . $csvFilePath);
                    }

                    // Write header (only URL column)
                    fputcsv($csvHandle, ['URL']);

                    // Write URLs
                    foreach ($urls as $url) {
                        fputcsv($csvHandle, [$url]);
                    }

                    fclose($csvHandle);

                    $this->logger->info("Successfully generated CSV file for store ID {$storeId}: " . basename($csvFilePath));
                    $csvFiles[] = $csvFilePath;
                } catch (\Exception $e) {
                    $this->logger->error("Error processing sitemap for store ID {$storeId} ({$sitemapUrl}): " . $e->getMessage());
                    continue;
                } finally {
                    // Always stop emulation to restore original context
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error during sitemap parsing and CSV generation: ' . $e->getMessage());
        }

        return $csvFiles;
    }

    /**
     * Get sitemap URL from robots.txt for a given store base URL
     *
     * @param string $baseUrl Store base URL
     * @return string|null Sitemap URL or null if not found
     */
    private function getSitemapUrlFromRobotsTxt(string $baseUrl): ?string
    {
        try {
            // Construct robots.txt URL
            $robotsUrl = rtrim($baseUrl, '/') . '/robots.txt';

            // Fetch robots.txt content using common method
            $robotsContent = $this->fetchFileContent($robotsUrl);

            if ($robotsContent === false) {
                return null;
            }

            // Parse robots.txt to find Sitemap: directive
            $lines = explode("\n", $robotsContent);
            foreach ($lines as $line) {
                $line = trim($line);
                if (stripos($line, 'Sitemap:') === 0) {
                    $sitemapUrl = trim(substr($line, 8)); // Remove "Sitemap:" prefix
                    return $sitemapUrl;
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->info(
                'Unable to get robots.txt file for store: %1 %2',
                [
                    $baseUrl,
                    $e->getMessage()
                ]
            );
            return null;
        }
    }

    /**
     * Fetch content from URL with appropriate SSL context based on developer mode
     *
     * @param string $url The URL to fetch content from
     * @return string|false Content or false if failed
     */
    private function fetchFileContent(string $url)
    {
        try {
            // Check if we're in developer mode to determine SSL context
            $isDeveloperMode = $this->appState->getMode() === \Magento\Framework\App\State::MODE_DEVELOPER;

            if ($isDeveloperMode) {
                // In developer mode, use custom stream context with SSL verification disabled
                $arrContextOptions = array(
                    "ssl" => array(
                        "allow_self_signed" => true,
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                    ),
                );
                $streamContext = stream_context_create($arrContextOptions);
                // Fetch content with custom context
                return file_get_contents($url, false, $streamContext);
            } else {
                // In production mode, use standard file_get_contents
                return file_get_contents($url);
            }
        } catch (\Exception $e) {
            $this->logger->warning("Failed to fetch content from URL: {$url}. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alternative method to get sitemap from local filesystem if needed
     *
     * @param string $sitemapPath Path to local sitemap file
     * @return array List of URLs extracted from the sitemap
     */
    public function parseLocalSitemap(string $sitemapPath): array
    {
        try {
            if (!file_exists($sitemapPath)) {
                throw new \Exception("Sitemap file does not exist: " . $sitemapPath);
            }

            // Parse XML content
            $xml = simplexml_load_file($sitemapPath);

            if ($xml === false) {
                throw new \Exception("Failed to parse XML from path: " . $sitemapPath);
            }

            // Extract URLs from sitemap
            $urls = [];
            foreach ($xml->url as $urlElement) {
                $loc = (string)$urlElement->loc;

                // Only include non-empty and valid URLs
                if (!empty($loc)) {
                    $urls[] = $loc;
                }
            }

            return $urls;
        } catch (\Exception $e) {
            $this->logger->error('Error parsing local sitemap: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete all generated CSV files
     *
     * @return int Number of deleted files
     */
    public function deleteGeneratedCsvFiles(): int
    {
        try {
            $mediaPath = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
            $csvDirectory = rtrim($mediaPath, '/') . '/cacheWarmerCsvFiles/';

            // Check if directory exists
            if (!is_dir($csvDirectory)) {
                return 0;
            }

            $deletedCount = 0;
            $files = glob($csvDirectory . '*.csv');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $deletedCount++;
                }
            }

            $this->logger->info("Successfully deleted {$deletedCount} CSV files from " . basename($csvDirectory));
            return $deletedCount;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting generated CSV files: ' . $e->getMessage());
            return 0;
        }
    }
}
