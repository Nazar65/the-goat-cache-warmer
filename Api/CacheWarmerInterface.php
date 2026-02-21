<?php declare(strict_types=1);
/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Api;

interface CacheWarmerInterface
{
    /**
     * Warm up cache for the website
     *
     * @param string|null $csvFilePath Optional path to CSV file with URLs to warm up
     * @return array
     */
    public function warmUp(string $csvFilePath = null): array;
}

