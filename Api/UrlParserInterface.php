<?php

declare(strict_types=1);
/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

/**
 * @author SharkGaming Team
 */

namespace Goat\TheCacheWarmer\Api;

interface UrlParserInterface
{
    /**
     * Parse nginx access log and generate CSV with top 1000 visited URLs
     *
     * @param string $logFilePath Path to the nginx access.log file
     * @return string Path to generated CSV file
     */
    public function parseLogAndGenerateCsv(string $logFilePath): string;

    /**
     * Delete a generated CSV file
     *
     * @param string $csvFilePath Path to the CSV file to delete
     * @return bool True if file was deleted successfully, false otherwise
     */
    public function deleteGeneratedCsv(string $csvFilePath): bool;
}

