<?php

declare(strict_types=1);
/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Plugin;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\MessageQueue\PublisherInterface;
use Goat\TheCacheWarmer\Service\QueueMessageChecker;

class CacheFlushPlugin
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
     * @param QueueMessageChecker $queueMessageChecker
     */
    public function __construct(
        LoggerInterface $logger,
        PublisherInterface $publisher,
        QueueMessageChecker $queueMessageChecker
    ) {
        $this->logger = $logger;
        $this->publisher = $publisher;
        $this->queueMessageChecker = $queueMessageChecker;
    }

    /**
     * Execute cache warming after cache flush by publishing to queue only once per execution
     *
     * @return void
     */
    public function afterCleanType(
        TypeList $subject,
        $result
    ) {
        // Only publish once per execution context
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

            // Publish message to queue instead of executing warmup directly
            $this->publisher->publish(self::TOPIC_NAME, '');

            $this->logger->info('Cache warming request published to queue');

            // Mark as published to prevent multiple publishes in this execution
            self::$published = true;
        } catch (\Exception $e) {
            $this->logger->error('Error during cache warm-up publish after flush: ' . $e->getMessage());
        }

        return $result;
    }
}
