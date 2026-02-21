<?php

declare(strict_types=1);

namespace Goat\TheCacheWarmer\Test\Unit;

use PHPUnit\Framework\TestCase;
use Goat\TheCacheWarmer\Cron\GenerateCsv;
use Goat\TheCacheWarmer\Api\CacheWarmerInterface;
use Goat\TheCacheWarmer\Service\UrlParser;
use Psr\Log\LoggerInterface;

class CronTest extends TestCase
{
    public function testCronExecute()
    {
        $urlParserMock = $this->createMock(UrlParser::class);
        $cacheWarmerMock = $this->createMock(CacheWarmerInterface::class);
        $loggerMock = $this->createMock(LoggerInterface::class);

        // This is a basic smoke test to check class instantiation and method existance
        $generateCsv = new GenerateCsv(
            $urlParserMock,
            $cacheWarmerMock,
            $loggerMock
        );

        $this->assertInstanceOf(GenerateCsv::class, $generateCsv);
    }
}
