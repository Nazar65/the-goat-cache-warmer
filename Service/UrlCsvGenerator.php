<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Service;

use Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem\DirectoryList;

class UrlCsvGenerator
{
    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        DirectoryList $directoryList,
        LoggerInterface $logger
    ) {
        $this->directoryList = $directoryList;
        $this->logger = $logger;
    }

    /**
     * Generate a CSV file from a single URL pattern
     *
     * @param string $url The URL to generate CSV for
     * @return string Path to the generated CSV file
     */
    public function generateCsvFromUrl(string $url): string
    {
        try {
            // Validate that URL starts with http/https
            if (!preg_match('/^https?:\/\//', $url)) {
                throw new \Exception('Invalid URL format: ' . $url);
            }

            // Create CSV file path in media directory
            $mediaPath = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
            $cacheWarmerCsvPath = rtrim($mediaPath, '/') . '/cacheWarmerCsvFiles';
            $urlPath = $cacheWarmerCsvPath . '/urls';

            // Create directory if it doesn't exist
            if (!is_dir($urlPath)) {
                mkdir($urlPath, 0755, true);
            }

            // Generate filename with timestamp
            $csvFileName = 'url_' . date('Y-m-d_H-i-s') . '.csv';
            $csvFilePath = $urlPath . '/' . $csvFileName;

            // Create CSV file with single URL
            $csvHandle = fopen($csvFilePath, 'w');

            if (!$csvHandle) {
                throw new \Exception("Could not create CSV file: " . $csvFilePath);
            }

            // Write header (only URL column)
            fputcsv($csvHandle, ['URL']);

            // Write the single URL
            fputcsv($csvHandle, [$url]);

            fclose($csvHandle);

            $this->logger->info('Successfully generated CSV file from URL: ' . $csvFilePath);

            return $csvFilePath;
        } catch (\Exception $e) {
            $this->logger->error('Error generating CSV from URL: ' . $e->getMessage());
            throw new \Exception('Failed to generate CSV from URL: ' . $e->getMessage());
        }
    }

    /**
     * Delete generated CSV file
     *
     * @param string $csvFilePath
     * @return bool
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
}
