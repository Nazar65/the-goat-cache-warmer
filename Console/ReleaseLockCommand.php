<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Console;

use Magento\Framework\FlagManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReleaseLockCommand extends Command
{
    private const LOCK_FLAG_NAME = 'cache_warmup_running';

    /**
     * @var FlagManager
     */
    private $flagManager;

    public function __construct(
        FlagManager $flagManager,
        string $name = null
    ) {
        $this->flagManager = $flagManager;

        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('cache:warmup:release-lock')
            ->setDescription('Manually release the cache warmup lock if it is stuck');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {

            $lockData = $this->flagManager->getFlagData(self::LOCK_FLAG_NAME);

            if (!$lockData) {
                $output->writeln('<info>No cache warmup lock found.</info>');
                return 0;
            }

            // Parse the stored lock data (timestamp and PID)
            $lockParts = explode('|', $lockData);
            if (count($lockParts) < 2) {
                $output->writeln('<comment>Invalid lock format detected. Removing lock anyway.</comment>');
            } else {
                [$timestamp, $pid] = $lockParts;
                $output->writeln("<info>Lock found with timestamp: {$timestamp}, PID: {$pid}</info>");
            }

            // Remove the lock flag
            $this->flagManager->deleteFlag(self::LOCK_FLAG_NAME);
            $output->writeln('<info>Cache warmup lock has been released successfully.</info>');
        } catch (\Exception $e) {
            $output->writeln('<error>Error releasing lock: ' . $e->getMessage() . '</error>');
            return 1;
        }

        return 0;
    }
}
