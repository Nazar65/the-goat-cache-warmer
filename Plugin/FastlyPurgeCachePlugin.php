<?php

declare(strict_types=1);
/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Plugin;

use Psr\Log\LoggerInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Fastly\Cdn\Model\PurgeCache;
use Goat\TheCacheWarmer\Api\CacheWarmerInterface;
use Goat\TheCacheWarmer\Service\UrlCsvGenerator;
use Goat\TheCacheWarmer\Service\QueueMessageChecker;

class FastlyPurgeCachePlugin
{
    /**
     * @var string
     */
    private const TOPIC_NAME = 'cache.warmup';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var CacheWarmerInterface
     */
    private $cacheWarmer;

    /**
     * @var UrlCsvGenerator
     */
    private $urlCsvGenerator;

    /**
     * @var QueueMessageChecker
     */
    private $queueMessageChecker;

    /**
     * Flag to track if warming has already been published for this execution
     *
     * @var bool
     */
    private static $published = false;

    /**
     * @param LoggerInterface $logger
     * @param PublisherInterface $publisher
     * @param CacheWarmerInterface $cacheWarmer
     * @param UrlCsvGenerator $urlCsvGenerator
     * @param QueueMessageChecker $queueMessageChecker
     */
    public function __construct(
        LoggerInterface $logger,
        PublisherInterface $publisher,
        CacheWarmerInterface $cacheWarmer,
        UrlCsvGenerator $urlCsvGenerator,
        QueueMessageChecker $queueMessageChecker
    ) {
        $this->logger = $logger;
        $this->publisher = $publisher;
        $this->cacheWarmer = $cacheWarmer;
        $this->urlCsvGenerator = $urlCsvGenerator;
        $this->queueMessageChecker = $queueMessageChecker;
    }

    /**
     * Execute cache warming after Fastly purge request by publishing to queue only once per execution
     *
     * @param PurgeCache $subject
     * @param array|bool|\Magento\Framework\Controller\Result\Json $result
     * @param string $pattern
     * @return array|bool|\Magento\Framework\Controller\Result\Json
     */
    public function afterSendPurgeRequest(
        PurgeCache $subject,
        $result,
        $pattern = ''
    ) {
        // Only publish once per execution context to avoid duplicate warming
        if (self::$published) {
            return $result;
        }

        try {
            // Check if there's already a 'cache.warmup' message with status 2 (new)
            if ($this->queueMessageChecker->hasNewMessageForTopic(self::TOPIC_NAME)) {
                $this->logger->info('Cache warming request already exists in queue, skipping publish');
                self::$published = true;
                return $result;
            }

            // If pattern is empty, trigger same warming as existing plugins
            if (empty($pattern)) {
                $this->publisher->publish(self::TOPIC_NAME, '');
                $this->logger->info('Cache warming request published to queue for full purge');
                self::$published = true;
                return $result;
            }

            // If pattern is a single URL (starts with http), generate CSV for that URL and warm cache
            if (!is_array($pattern) && strpos($pattern, 'http') === 0) {
                // Generate CSV from the single URL
                $csvFilePath = $this->urlCsvGenerator->generateCsvFromUrl($pattern);

                // Use existing cache warming functionality to process this CSV file
                $warmupResult = $this->cacheWarmer->warmUp($csvFilePath);

                if ($warmupResult['status'] === 'success') {
                    $this->logger->info('Cache warming completed successfully for single URL: ' . $pattern);
                } else {
                    $this->logger->error('Cache warming failed for single URL: ' . $pattern . ', Error: ' . $warmupResult['message']);
                }

                $this->urlCsvGenerator->deleteGeneratedCsv($csvFilePath);
                self::$published = true;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error during cache warm-up process after Fastly purge: ' . $e->getMessage());
        }

        return $result;
    }
}
