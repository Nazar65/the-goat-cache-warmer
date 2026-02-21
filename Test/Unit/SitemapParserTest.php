<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Test\Unit;

use PHPUnit\Framework\TestCase;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Service\SitemapParser;
use ReflectionClass;

class SitemapParserTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|StoreManagerInterface
     */
    private $storeManagerMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|DirectoryList
     */
    private $directoryListMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $loggerMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|State
     */
    private $appStateMock;

    /**
     * @var SitemapParser
     */
    private $sitemapParser;

    protected function setUp(): void
    {
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->directoryListMock = $this->createMock(DirectoryList::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->appStateMock = $this->createMock(State::class);

        $this->sitemapParser = new SitemapParser(
            $this->directoryListMock,
            $this->loggerMock,
            $this->storeManagerMock,
            $this->appStateMock
        );
    }

    public function testParseSitemapsAndGenerateCsvWithActiveStores()
    {
        // Create real store models instead of mocking interface directly
        $store1 = $this->createPartialMock(\Magento\Store\Model\Store::class, ['getId', 'getIsActive', 'getBaseUrl']);
        $store1->method('getId')->willReturn(1);
        $store1->method('getIsActive')->willReturn(true);
        $store1->method('getBaseUrl')->willReturn('https://example.com/');

        $store2 = $this->createPartialMock(\Magento\Store\Model\Store::class, ['getId', 'getIsActive', 'getBaseUrl']);
        $store2->method('getId')->willReturn(2);
        $store2->method('getIsActive')->willReturn(true);
        $store2->method('getBaseUrl')->willReturn('https://example2.com/');

        // Mock store manager to return multiple stores
        $this->storeManagerMock->expects($this->once())
            ->method('getStores')
            ->willReturn([$store1, $store2]);

        // Mock directory list for media path
        $this->directoryListMock->expects($this->any())
            ->method('getPath')
            ->with(DirectoryList::MEDIA)
            ->willReturn('/var/www/html/media');

        // We can't easily mock file_get_contents or simplexml_load_string in unit tests,
        // but we can at least test that the method exists and can be called
        $result = $this->sitemapParser->parseSitemapsAndGenerateCsv();

        // Since we're testing with mocked dependencies, this should return an array
        $this->assertIsArray($result);
    }

    public function testParseSitemapsAndGenerateCsvWithInactiveStore()
    {
        // Create real store model for inactive store
        $store = $this->createPartialMock(\Magento\Store\Model\Store::class, ['getId', 'getIsActive', 'getBaseUrl']);
        $store->method('getId')->willReturn(1);
        $store->method('getIsActive')->willReturn(false); // Inactive store
        $store->method('getBaseUrl')->willReturn('https://example.com/');

        $this->storeManagerMock->expects($this->once())
            ->method('getStores')
            ->willReturn([$store]);

        $result = $this->sitemapParser->parseSitemapsAndGenerateCsv();

        $this->assertIsArray($result);
    }

    public function testParseLocalSitemapSuccess()
    {
        // Create a valid XML sitemap for testing
        $sitemapXml = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/page1</loc>
    </url>
    <url>
        <loc>https://example.com/page2</loc>
    </url>
</urlset>';

        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'sitemap_test');
        file_put_contents($tempFile, $sitemapXml);

        try {
            // Use reflection to test the private method directly
            $reflection = new ReflectionClass(SitemapParser::class);
            $method = $reflection->getMethod('parseLocalSitemap');
            $method->setAccessible(true);

            $result = $method->invoke($this->sitemapParser, $tempFile);

            $this->assertIsArray($result);
            $this->assertCount(2, $result);
        } finally {
            unlink($tempFile); // Clean up
        }
    }

    public function testParseLocalSitemapWithInvalidPath()
    {
        // Use reflection to test the private method directly
        $reflection = new ReflectionClass(SitemapParser::class);
        $method = $reflection->getMethod('parseLocalSitemap');
        $method->setAccessible(true);

        $result = $method->invoke($this->sitemapParser, '/invalid/path/sitemap.xml');

        $this->assertIsArray($result);
        $this->assertCount(0, $result);  // Should return empty array for invalid path
    }

    public function testDeleteGeneratedCsvFiles()
    {
        // Mock directory list to return media path
        $mediaPath = '/var/www/html/media';
        $csvDirectory = $mediaPath . '/cacheWarmerCsvFiles/';

        $this->directoryListMock->expects($this->any())
            ->method('getPath')
            ->with(DirectoryList::MEDIA)
            ->willReturn($mediaPath);

        // Test the method can be called
        $result = $this->sitemapParser->deleteGeneratedCsvFiles();

        $this->assertIsInt($result);
    }

    public function testGetSitemapUrlFromRobotsTxt()
    {
        // We'll test this using reflection since it's a private method
        // This is just to make sure the method exists and can be called

        $reflection = new ReflectionClass(SitemapParser::class);
        $method = $reflection->getMethod('getSitemapUrlFromRobotsTxt');
        $method->setAccessible(true);

        // Test with an invalid URL - this should return null without errors
        $result = $method->invoke($this->sitemapParser, 'https://invalid-domain-that-does-not-exist.com/');

        $this->assertNull($result);
    }
}
