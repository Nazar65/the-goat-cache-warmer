<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Test\Unit;

use PHPUnit\Framework\TestCase;

use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Service\SitemapParser;
use Goat\TheCacheWarmer\Model\Config;
use Goat\TheCacheWarmer\Service\UrlParser;
use ReflectionClass;

class UrlParserTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|DirectoryList
     */
    private $directoryListMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $loggerMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|SitemapParser
     */
    private $sitemapParserMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Config
     */
    private $configMock;

    /**
     * @var UrlParser
     */
    private $urlParser;

    protected function setUp(): void
    {
        $this->directoryListMock = $this->createMock(DirectoryList::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->sitemapParserMock = $this->createMock(SitemapParser::class);
        $this->configMock = $this->createMock(Config::class);

        $this->urlParser = new UrlParser(
            $this->directoryListMock,
            $this->loggerMock,
            $this->sitemapParserMock,
            $this->configMock
        );
    }

    public function testParseLogAndGenerateCsvWithValidLogFile()
    {
        // Create a temporary log file with valid content
        $logContent = "127.0.0.1 - - [01/Jan/2023:00:00:00 +0000] \"GET /page1 HTTP/1.1\" 200 1234 \"-\" \"Mozilla/5.0...\"\n";
        $logContent .= "127.0.0.1 - - [01/Jan/2023:00:00:01 +0000] \"GET /page2 HTTP/1.1\" 200 1234 \"-\" \"Mozilla/5.0...\"\n";
        $logContent .= "127.0.0.1 - - [01/Jan/2023:00:00:02 +0000] \"GET /image.jpg HTTP/1.1\" 200 1234 \"-\" \"Mozilla/5.0...\"\n"; // Should be filtered out
        $logContent .= "127.0.0.1 - - [01/Jan/2023:00:00:03 +0000] \"GET /api/v1/data HTTP/1.1\" 200 1234 \"-\" \"Mozilla/5.0...\"\n"; // Should be filtered out

        $tempLogFile = tempnam(sys_get_temp_dir(), 'log_test');
        file_put_contents($tempLogFile, $logContent);

        // Mock config to return false for log include base domain
        $this->configMock->expects($this->any())
            ->method('getLogFilePattern')
            ->willReturn('/GET\\s+(https?:\\/\\/[^\\s]+)\\s+HTTP/');

        $this->configMock->expects($this->any())
            ->method('getLogIncludeBaseDomain')
            ->willReturn(false);

        $this->configMock->expects($this->any())
            ->method('getIgnoredUserAgents')
            ->willReturn([]);

        // Mock directory list to return media path
        $mediaPath = '/var/www/html/media';
        $this->directoryListMock->expects($this->any())
            ->method('getPath')
            ->with(DirectoryList::MEDIA)
            ->willReturn($mediaPath);

        // Create the expected CSV directory structure
        $cacheWarmerCsvPath = rtrim($mediaPath, '/') . '/cacheWarmerCsvFiles';
        $nginxPath = $cacheWarmerCsvPath . '/nginx';

        // Test that the method can be called without throwing exceptions
        $result = $this->urlParser->parseLogAndGenerateCsv($tempLogFile);

        // Verify the result is a string (file path) or false if error occurred
        $this->assertIsString($result);

        // Clean up temp file
        unlink($tempLogFile);
    }

    public function testParseLogAndGenerateCsvWithFullDomainUrls()
    {
        // Create a temporary log file with full domain URLs
        $logContent = "127.0.0.1 - - [01/Jan/2023:00:00:00 +0000] \"GET https://example.com/page1 HTTP/1.1\" 200 1234 \"-\" \"Mozilla/5.0...\"\n";
        $logContent .= "127.0.0.1 - - [01/Jan/2023:00:00:01 +0000] \"GET https://example.com/page2 HTTP/1.1\" 200 1234 \"-\" \"Mozilla/5.0...\"\n";

        $tempLogFile = tempnam(sys_get_temp_dir(), 'log_test_full');
        file_put_contents($tempLogFile, $logContent);

        // Mock config to return true for log include base domain
        $this->configMock->expects($this->any())
            ->method('getLogFilePattern')
            ->willReturn('/GET\s+(\/[^\s]*)\s+HTTP\/1\.1\"\s+(\d+)\s+/');

        $this->configMock->expects($this->any())
            ->method('getLogIncludeBaseDomain')
            ->willReturn(true);

        $this->configMock->expects($this->any())
            ->method('getIgnoredUserAgents')
            ->willReturn([]);

        // Mock directory list to return media path
        $mediaPath = '/var/www/html/media';
        $this->directoryListMock->expects($this->any())
            ->method('getPath')
            ->with(DirectoryList::MEDIA)
            ->willReturn($mediaPath);

        // Test that the method can be called without throwing exceptions
        $result = $this->urlParser->parseLogAndGenerateCsv($tempLogFile);

        // Verify the result is a string (file path) or false if error occurred
        $this->assertIsString($result);

        // Clean up temp file
        unlink($tempLogFile);
    }

    public function testParseLogAndGenerateCsvWithInvalidFile()
    {
        $invalidFilePath = '/non/existent/log/file.log';

        $this->configMock->expects($this->any())
            ->method('getLogFilePattern')
            ->willReturn('/GET\s+(\/[^\s]*)\s+HTTP\/1\.1\"\s+(\d+)\s+/');

        $this->configMock->expects($this->any())
            ->method('getLogIncludeBaseDomain')
            ->willReturn(false);

        $this->configMock->expects($this->any())
            ->method('getIgnoredUserAgents')
            ->willReturn([]);

        // Should throw an exception for non-existent file
        $this->expectException(\Exception::class);
        $this->urlParser->parseLogAndGenerateCsv($invalidFilePath);
    }

    public function testDeleteGeneratedCsvSuccess()
    {
        // Create a temporary CSV file
        $tempCsvFile = tempnam(sys_get_temp_dir(), 'csv_test');

        // Ensure the file exists
        $this->assertTrue(file_exists($tempCsvFile));

        // Mock logger to verify it's called
        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Successfully deleted CSV file'));

        // Test that deletion works properly
        $result = $this->urlParser->deleteGeneratedCsv($tempCsvFile);

        // Should return true on successful deletion
        $this->assertTrue($result);

        // File should no longer exist
        $this->assertFalse(file_exists($tempCsvFile));
    }

    public function testDeleteGeneratedCsvFileNotExists()
    {
        $nonExistentFile = '/path/to/non/existent/file.csv';

        // Mock logger to verify it's called with the correct message
        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with($this->stringContains('CSV file does not exist, nothing to delete'));

        // Test deletion of non-existent file - should return false
        $result = $this->urlParser->deleteGeneratedCsv($nonExistentFile);

        // Should return false for non-existent files
        $this->assertFalse($result);
    }

    public function testIsPageUrlWithExcludePatterns()
    {
        $urlParser = new UrlParser(
            $this->directoryListMock,
            $this->loggerMock,
            $this->sitemapParserMock,
            $this->configMock,
            ['/admin/', '/api/'] // exclude patterns
        );

        // Use reflection to test the private isPageUrl method
        $reflection = new ReflectionClass($urlParser);
        $method = $reflection->getMethod('isPageUrl');
        $method->setAccessible(true);

        // Test URL that matches an exclusion pattern should return false
        $result = $method->invoke($urlParser, '/admin/dashboard');
        $this->assertFalse($result);

        $result = $method->invoke($urlParser, '/api/v1/data');
        $this->assertFalse($result);

        // Test valid page URL
        $result = $method->invoke($urlParser, '/category/product');
        $this->assertTrue($result);
    }

    public function testIsPageUrlWithFileExtensions()
    {
        $urlParser = new UrlParser(
            $this->directoryListMock,
            $this->loggerMock,
            $this->sitemapParserMock,
            $this->configMock,
            [], // exclude patterns
            ['.jpg', '.png', '.css']  // file extensions to exclude
        );

        // Use reflection to test the private isPageUrl method
        $reflection = new ReflectionClass($urlParser);
        $method = $reflection->getMethod('isPageUrl');
        $method->setAccessible(true);

        // Test URLs with file extensions should return false
        $result = $method->invoke($urlParser, '/image.jpg');
        $this->assertFalse($result);

        $result = $method->invoke($urlParser, '/style.css');
        $this->assertFalse($result);

        // Test valid page URL without extension
        $result = $method->invoke($urlParser, '/category/product');
        $this->assertTrue($result);
    }

    public function testIsPageUrlWithExcludeUrls()
    {
        $urlParser = new UrlParser(
            $this->directoryListMock,
            $this->loggerMock,
            $this->sitemapParserMock,
            $this->configMock,
            [], // exclude patterns
            [],  // file extensions
            ['/cart', '/checkout'] // exclude URLs
        );

        // Use reflection to test the private isPageUrl method
        $reflection = new ReflectionClass($urlParser);
        $method = $reflection->getMethod('isPageUrl');
        $method->setAccessible(true);

        // Test URL containing excluded path should return false
        $result = $method->invoke($urlParser, '/cart/view');
        $this->assertFalse($result);

        $result = $method->invoke($urlParser, '/checkout/process');
        $this->assertFalse($result);

        // Test valid page URL
        $result = $method->invoke($urlParser, '/category/product');
        $this->assertTrue($result);
    }

    public function testIsPageUrlWithQueryParameters()
    {
        $urlParser = new UrlParser(
            $this->directoryListMock,
            $this->loggerMock,
            $this->sitemapParserMock,
            $this->configMock
        );

        // Use reflection to test the private isPageUrl method
        $reflection = new ReflectionClass($urlParser);
        $method = $reflection->getMethod('isPageUrl');
        $method->setAccessible(true);

        // URLs with query parameters should return false
        $result = $method->invoke($urlParser, '/page?param=value');
        $this->assertFalse($result);

        $result = $method->invoke($urlParser, '/product?id=123&category=electronics');
        $this->assertFalse($result);
    }

    public function testIsPageUrlWithRestApiUrls()
    {
        // Create a simple direct instance to test the functionality
        $urlParser = new UrlParser(
            $this->directoryListMock,
            $this->loggerMock,
            $this->sitemapParserMock,
            $this->configMock
        );

        // Use reflection to test the private isPageUrl method
        $reflection = new ReflectionClass($urlParser);
        $method = $reflection->getMethod('isPageUrl');
        $method->setAccessible(true);
        // Test URLs containing REST API - they should return false (filtered out)
        $result1 = $method->invoke($urlParser, '/rest/V1/cart');
        $this->assertFalse($result1, 'URL /rest/V1/cart should be filtered out as a REST API URL');

        $result2 = $method->invoke($urlParser, '/rest/V1/shipping-methods');
        $this->assertFalse($result2);
    }

    public function testIsPageUrlValidPage()
    {
        $urlParser = new UrlParser(
            $this->directoryListMock,
            $this->loggerMock,
            $this->sitemapParserMock,
            $this->configMock
        );

        // Use reflection to test the private isPageUrl method
        $reflection = new ReflectionClass($urlParser);
        $method = $reflection->getMethod('isPageUrl');
        $method->setAccessible(true);

        // Valid page URLs should return true
        $result = $method->invoke($urlParser, '/category/product');
        $this->assertTrue($result);

        $result = $method->invoke($urlParser, '/about-us');
        $this->assertTrue($result);

        $result = $method->invoke($urlParser, '/');
        $this->assertTrue($result);
    }
}
