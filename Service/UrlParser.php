<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Service;

use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Api\UrlParserInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Goat\TheCacheWarmer\Service\SitemapParser;
use Goat\TheCacheWarmer\Model\Config;

class UrlParser implements UrlParserInterface
{
    /**
     * @var string
     */
    private $logPattern;

    public function __construct(
        private DirectoryList $directoryList,
        private LoggerInterface $logger,
        private SitemapParser $sitemapParser,
        private Config $config,
        private array $excludePatterns = [],
        private array $fileExtensions = [],
        private array $excludeUrls = []
    ) {
        $this->logPattern = $this->config->getLogFilePattern();
    }

    /**
     * {@inheritdoc}
     */
    public function parseLogAndGenerateCsv(
        string $logFilePath,
    ): string {
        try {
            // Validate log file exists
            if (!file_exists($logFilePath)) {
                throw new \Exception("Log file does not exist: " . $logFilePath);
            }

            // Check configuration for whether log includes base domain
            $logIncludesBaseDomain = (bool)$this->config->getLogIncludeBaseDomain();

            // Get ignored user agents from config
            $ignoredUserAgents = $this->config->getIgnoredUserAgents();

            // Read the log file and extract URLs
            $urlCounts = [];

            // For better performance with large files, we'll read line by line
            $handle = fopen($logFilePath, 'r');

            if (!$handle) {
                throw new \Exception("Could not open log file: " . $logFilePath);
            }

            while (($line = fgets($handle)) !== false) {
                // Check if user agent should be ignored (if we have ignore patterns)
                if (!empty($ignoredUserAgents)) {
                    $userAgentMatched = false;

                    foreach ($ignoredUserAgents as $pattern) {
                        if (is_array($pattern) && isset($pattern['expression'])) {
                            $regexPattern = $pattern['expression'];

                            // If it's not a proper regex pattern, skip
                            if (@preg_match($regexPattern, '') === false) {
                                continue;
                            }

                            if (preg_match($regexPattern, $line)) {
                                $userAgentMatched = true;
                                break;
                            }
                        } elseif (is_string($pattern)) {
                            // If it's a simple string pattern
                            if (strpos($line, $pattern) !== false) {
                                $userAgentMatched = true;
                                break;
                            }
                        }
                    }

                    // Skip this line if user agent should be ignored
                    if ($userAgentMatched) {
                        continue;
                    }
                }

                if ($logIncludesBaseDomain) {
                    // If log includes full domain URL, extract it directly from the line
                    // This assumes nginx logs are formatted like: "GET https://example.com/path HTTP/1.1" 200 ...
                    if (preg_match('/GET\s+(https?:\/\/[^\s]+)\s+HTTP/', $line, $matches)) {
                        $url = trim($matches[1]);

                        // We're interested in the URL part from GET request with 200 status
                        if (!empty($url) && $this->isPageUrl($url)) {
                            if (!isset($urlCounts[$url])) {
                                $urlCounts[$url] = 0;
                            }
                            $urlCounts[$url]++;
                        }
                    }
                } else {
                    // Use existing logic for logs with path-only URLs
                    // Common nginx access log format pattern:
                    // 127.0.0.1 - - [01/Jan/2023:00:00:00 +0000] "GET /path/to/page HTTP/1.1" 200 1234 "-" "Mozilla/5.0..."
                    // Only use the configured pattern if it's not empty
                    if (!empty($this->logPattern) && preg_match($this->logPattern, $line, $matches)) {
                        $url = trim($matches[1]);
                        $statusCode = trim($matches[2]);

                        // We're interested in the URL part from GET request with 200 status
                        if (empty($url) || $statusCode !== '200') {
                            continue;
                        }

                        // Filter out URLs with file extensions or images and REST API URLs
                        if ($this->isPageUrl($url)) {
                            if (!isset($urlCounts[$url])) {
                                $urlCounts[$url] = 0;
                            }
                            $urlCounts[$url]++;
                        }
                    } else {
                        // For non-matching lines, try a more general pattern looking for URLs after GET
                        // This is to handle various log formats that might have different spacing or formatting
                        if (preg_match('/GET\s+(\/[^\s]*)\s+HTTP/', $line, $matches)) {
                            $url = trim($matches[1]);

                            if (!empty($url) && $this->isPageUrl($url)) {
                                if (!isset($urlCounts[$url])) {
                                    $urlCounts[$url] = 0;
                                }
                                $urlCounts[$url]++;
                            }
                        }
                    }
                }
            }

            fclose($handle);

            // Sort URLs by count (descending)
            arsort($urlCounts);

            // Take top 500
            $topUrls = array_slice($urlCounts, 0, 500, true);

            if ($logIncludesBaseDomain) {
                // If log already includes base domain, we can directly write the URLs without sitemap lookup
                // Create CSV file path in media directory for nginx logs
                $mediaPath = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
                $cacheWarmerCsvPath = rtrim($mediaPath, '/') . '/cacheWarmerCsvFiles';
                $nginxPath = $cacheWarmerCsvPath . '/nginx';

                // Create nginx directory if it doesn't exist
                if (!is_dir($nginxPath)) {
                    mkdir($nginxPath, 0755, true);
                }

                // Create CSV file with full URLs (already complete)
                $fullUrlCsvFilePath = $nginxPath . '/top_urls_full_' . date('Y-m-d_H-i-s') . '.csv';

                $fullUrlCsvHandle = fopen($fullUrlCsvFilePath, 'w');

                if (!$fullUrlCsvHandle) {
                    throw new \Exception("Could not create full URL CSV file: " . $fullUrlCsvFilePath);
                }

                // Write header (only URL column)
                fputcsv($fullUrlCsvHandle, ['URL']);

                // Write all URLs directly as they include the complete domain
                foreach ($topUrls as $url => $count) {
                    fputcsv($fullUrlCsvHandle, [$url]);
                }

                fclose($fullUrlCsvHandle);

                // Log file created
                $this->logger->info('Successfully generated CSV file with full domain URLs: ' . $fullUrlCsvFilePath);

                return $fullUrlCsvFilePath;
            } else {
                // Use existing logic for sitemap lookup (backward compatibility)
                // Get sitemap CSV files to check for matching URLs
                $sitemapCsvFiles = [];
                try {
                    $sitemapCsvFiles = $this->sitemapParser->parseSitemapsAndGenerateCsv();
                } catch (\Exception $e) {
                    $this->logger->warning('Could not fetch sitemap CSV files: ' . $e->getMessage());
                }

                // Create a set of all URLs from sitemaps for quick lookup
                $sitemapUrls = [];
                foreach ($sitemapCsvFiles as $csvFile) {
                    try {
                        if (!file_exists($csvFile)) {
                            continue;
                        }

                        $handle = fopen($csvFile, 'r');
                        if (!$handle) {
                            continue;
                        }

                        // Skip header
                        fgetcsv($handle);

                        while (($line = fgetcsv($handle)) !== false) {
                            if (!empty($line[0])) {
                                $sitemapUrls[] = trim($line[0]);
                            }
                        }

                        fclose($handle);
                    } catch (\Exception $e) {
                        $this->logger->warning('Error reading sitemap CSV file ' . $csvFile . ': ' . $e->getMessage());
                    }
                }

                // Create a map of URL paths to full URLs from sitemaps
                $urlPathToFullUrl = [];
                foreach ($sitemapUrls as $fullUrl) {
                    if (parse_url($fullUrl, PHP_URL_PATH)) {
                        $path = parse_url($fullUrl, PHP_URL_PATH);
                        if (!isset($urlPathToFullUrl[$path])) {
                            $urlPathToFullUrl[$path] = $fullUrl;
                        }
                    }
                }

                // Create CSV file path in media directory for nginx logs
                $mediaPath = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
                $cacheWarmerCsvPath = rtrim($mediaPath, '/') . '/cacheWarmerCsvFiles';
                $nginxPath = $cacheWarmerCsvPath . '/nginx';

                // Create nginx directory if it doesn't exist
                if (!is_dir($nginxPath)) {
                    mkdir($nginxPath, 0755, true);
                }

                // Create CSV file with full URLs for matched entries
                $fullUrlCsvFilePath = $nginxPath . '/top_urls_full_' . date('Y-m-d_H-i-s') . '.csv';

                $fullUrlCsvHandle = fopen($fullUrlCsvFilePath, 'w');

                if (!$fullUrlCsvHandle) {
                    throw new \Exception("Could not create full URL CSV file: " . $fullUrlCsvFilePath);
                }

                // Write header (only URL column)
                fputcsv($fullUrlCsvHandle, ['URL']);

                // Write matched URLs with full domain names
                foreach ($topUrls as $url => $count) {
                    if (isset($urlPathToFullUrl[$url])) {
                        fputcsv($fullUrlCsvHandle, [$urlPathToFullUrl[$url]]);
                    }
                }

                fclose($fullUrlCsvHandle);

                // Log both files created
                $this->logger->info('Successfully generated CSV file with full domain URLs: ' . $fullUrlCsvFilePath);

                return $fullUrlCsvFilePath;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error parsing log and generating CSV: ' . $e->getMessage());
            throw new \Exception('Failed to parse log and generate CSV: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteGeneratedCsv(string $csvFilePath): bool
    {
        try {
            // Check if file exists before attempting to delete
            if (!file_exists($csvFilePath)) {
                $this->logger->info('CSV file does not exist, nothing to delete: ' . $csvFilePath);
                return false;
            }

            // Attempt to delete the file
            $result = unlink($csvFilePath);

            if ($result) {
                $this->logger->info('Successfully deleted CSV file: ' . $csvFilePath);
            } else {
                $this->logger->error('Failed to delete CSV file: ' . $csvFilePath);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting CSV file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if URL is a page URL (not an image, file or API endpoint)
     *
     * @param string $url
     * @return bool
     */
    private function isPageUrl(string $url): bool
    {
        // Check if URL matches any exclusion pattern
        foreach ($this->excludePatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }

        // Check if URL ends with a file extension
        foreach ($this->fileExtensions as $extension) {
            if (str_ends_with(strtolower($url), $extension)) {
                return false;
            }
        }

        // Check if URL contains any excluded path
        foreach ($this->excludeUrls as $excludeUrl) {
            if (strpos($url, $excludeUrl) !== false) {
                return false;
            }
        }

        // Filter out URLs with query parameters
        if (strpos($url, '?') !== false) {
            return false;
        }

        // Filter out REST API URLs containing "rest/V1"
        if (strpos($url, '/rest/V1') !== false) {
            return false;
        }

        // If none of the above filters match, it's likely a page URL
        return true;
    }
}
