<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class Config
{
    public const CONFIG_PATH_ENABLED = 'goat_cache_warmer/general/enabled';
    public const CONFIG_PATH_PYTHON_PATH = 'goat_cache_warmer/general/python_path';
    public const CONFIG_PATH_CONFIG_OPTION = 'goat_cache_warmer/general/config_option';
    public const CONFIG_PATH_CSV_FILES = 'goat_cache_warmer/general/csv_files';
    public const CONFIG_PATH_TIMEOUT = 'goat_cache_warmer/general/timeout';
    public const CONFIG_PATH_THREADS = 'goat_cache_warmer/general/threads';
    public const CONFIG_PATH_USE_THREADS = 'goat_cache_warmer/general/use_threads';
    public const CONFIG_PATH_DELAY = 'goat_cache_warmer/general/delay';
    public const CONFIG_PATH_CRON_SCHEDULE = 'goat_cache_warmer/cron/schedule';
    public const CONFIG_PATH_LOG_FILE_PATH = 'goat_cache_warmer/general/log_file_path';
    public const CONFIG_FOLDER_NAME = 'cacheWarmerConfig';
    public const CSV_SOURCE_FOLDER_NAME = 'cacheWarmerCsvFiles';
    public const CONFIG_PATH_LOCK_TIMEOUT = 'goat_cache_warmer/general/lock_timeout';
    public const CONFIG_PATH_RATE_LIMIT = 'goat_cache_warmer/general/rate_limit';
    public const CONFIG_PATH_LOG_PATTERN = 'goat_cache_warmer/general/log_pattern';
    public const CONFIG_PATH_LOG_INCLUDE_BASE_DOMAIN = 'goat_cache_warmer/general/log_include_base_domain';
    public const CONFIG_PATH_IGNORED_USER_AGENTS = 'goat_cache_warmer/general/ignored_user_agents';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private DirectoryList $directoryList
    ) {}

    /**
     * @return bool
     */
    public function isEnabled(int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ENABLED, 'store', $storeId);
    }

    /**
     * @return string
     */
    public function getPythonPath(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_PYTHON_PATH, 'store', $storeId);
    }

    /**
     * @return string
     */
    public function getConfigOption(int $storeId = null): string
    {
        $filePath =  (string) $this->scopeConfig->getValue(self::CONFIG_PATH_CONFIG_OPTION, 'store', $storeId);

        if ($filePath) {
            $mediaPath = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

            return $mediaPath . '/' . self::CONFIG_FOLDER_NAME . '/' . $filePath;
        }
        return '';
    }

    /**
     * @return string
     */
    public function getCsvFiles(int $storeId = null): string
    {
        $filePath =  (string) $this->scopeConfig->getValue(self::CONFIG_PATH_CSV_FILES, 'store', $storeId);

        if ($filePath) {
            $mediaPath = $this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);

            return $mediaPath . '/' . self::CSV_SOURCE_FOLDER_NAME . '/' . $filePath;
        }

        return '';
    }

    /**
     * @return string
     */
    public function getTimeout(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_LOCK_TIMEOUT, 'store', $storeId);
    }

    /**
     * Get cron schedule from configuration
     *
     * @return string
     */
    public function getCronSchedule(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_CRON_SCHEDULE, 'store', $storeId);
    }

    /**
     * Get nginx access log file path from configuration
     *
     * @return string
     */
    public function getLogFile(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_LOG_FILE_PATH, 'store', $storeId);
    }

    /**
     * Get threads count from configuration
     *
     * @return string
     */
    public function getThreads(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_THREADS, 'store', $storeId);
    }

    /**
     * Check if threading should be used
     *
     * @return bool
     */
    public function useThreads(int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(self::CONFIG_PATH_USE_THREADS, 'store', $storeId);
    }

    /**
     * Get rate limit from configuration
     *
     * @return int
     */
    public function getRateLimit(): int
    {
        return (int)$this->scopeConfig->getValue(self::CONFIG_PATH_RATE_LIMIT);
    }

    /**
     * Get delay in seconds for cache warming process
     *
     * @return string
     */
    public function getDelay(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_DELAY, 'store', $storeId);
    }

    /**
     * Get log file pattern from configuration
     *
     * @return string
     */
    public function getLogFilePattern(int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::CONFIG_PATH_LOG_PATTERN, 'store', $storeId);
    }

    /**
     * Check if nginx log includes base domain URL
     *
     * @return bool
     */
    public function getLogIncludeBaseDomain(int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(self::CONFIG_PATH_LOG_INCLUDE_BASE_DOMAIN, 'store', $storeId);
    }

    /**
     * Get ignored user agents from configuration
     *
     * @return array
     */
    public function getIgnoredUserAgents(int $storeId = null): array
    {
        $value = $this->scopeConfig->getValue(self::CONFIG_PATH_IGNORED_USER_AGENTS, 'store', $storeId);

        if (is_string($value)) {
            // If value is a serialized string, unserialize it
            return @unserialize($value) ?: [];
        }

        return is_array($value) ? $value : [];
    }
}
