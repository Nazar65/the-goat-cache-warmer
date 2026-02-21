<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Service;

use Magento\MysqlMq\Model\QueueManagement;
use Magento\MysqlMq\Model\ResourceModel\Queue as QueueResourceModel;
use Psr\Log\LoggerInterface;

/**
 * Service to check for existing queue messages with specific topic and status
 */
class QueueMessageChecker
{
    /**
     * @var QueueResourceModel
     */
    private $queueResourceModel;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param QueueResourceModel $queueResourceModel
     * @param LoggerInterface $logger
     */
    public function __construct(
        QueueResourceModel $queueResourceModel,
        LoggerInterface $logger
    ) {
        $this->queueResourceModel = $queueResourceModel;
        $this->logger = $logger;
    }

    /**
     * Check if there's already a 'new' message (status 2) for the given topic in queue
     *
     * @param string $topicName
     * @return bool
     */
    public function hasNewMessageForTopic(string $topicName): bool
    {
        try {
            // Use direct SQL query to check if there's a new message with cache.warmup topic
            return $this->hasNewCacheWarmupMessage($topicName);
        } catch (\Exception $e) {
            $this->logger->error('Error checking queue messages: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if there's already a new message (status 2) with cache.warmup topic
     *
     * @param string $topicName
     * @return bool
     */
    private function hasNewCacheWarmupMessage(string $topicName): bool
    {
        $connection = $this->queueResourceModel->getConnection();

        $select = $connection->select()
            ->from(
                ['queue_message' => $this->queueResourceModel->getTable('queue_message')],
                [QueueManagement::MESSAGE_TOPIC => 'topic_name']
            )->join(
                ['queue_message_status' => $this->queueResourceModel->getTable('queue_message_status')],
                'queue_message.id = queue_message_status.message_id',
                [
                    QueueManagement::MESSAGE_QUEUE_RELATION_ID => 'id',
                    QueueManagement::MESSAGE_QUEUE_ID => 'queue_id',
                    QueueManagement::MESSAGE_ID => 'message_id',
                    QueueManagement::MESSAGE_STATUS => 'status',
                    QueueManagement::MESSAGE_UPDATED_AT => 'updated_at',
                    QueueManagement::MESSAGE_NUMBER_OF_TRIALS => 'number_of_trials'
                ]
            )->join(
                ['queue' => $this->queueResourceModel->getTable('queue')],
                'queue.id = queue_message_status.queue_id',
                [QueueManagement::MESSAGE_QUEUE_NAME => 'name']
            )->where(
                'queue_message_status.status = ?',
                QueueManagement::MESSAGE_STATUS_NEW
            )->where('queue.name = ?', 'cache.warmup')
            ->where('queue_message.topic_name = ?', $topicName)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }
}
