<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Goat\TheCacheWarmer\Api\CacheWarmerInterface;
use Goat\TheCacheWarmer\Service\SitemapParser;
use Goat\TheCacheWarmer\Service\LockManager;

class WarmupFromSitemapCommand extends Command
{
    /**
     * @var SitemapParser
     */
    private $sitemapParser;

    /**
     * @var CacheWarmerInterface
     */
    private $cacheWarmer;

    /**
     * @var LockManager
     */
    private $lockManager;

    public function __construct(
        SitemapParser $sitemapParser,
        CacheWarmerInterface $cacheWarmer,
        LockManager $lockManager,
        string $name = null
    ) {
        $this->sitemapParser = $sitemapParser;
        $this->cacheWarmer = $cacheWarmer;
        $this->lockManager = $lockManager;

        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('cache:warmup:sitemap')
            ->setDescription('Manually generate CSV from sitemaps and warm up cache with console output');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Check for existing lock flag to prevent concurrent executions
        if ($this->lockManager->isAlreadyRunning()) {
            $output->writeln('<comment>Cache warmup process is already running, skipping this execution</comment>');
            return 1;
        }

        try {
            // Set a lock flag with timestamp and PID
            $this->lockManager->createLockFlag();

            $output->writeln('<info>Starting manual cache warmup from sitemaps...</info>');

            // Parse sitemaps and generate CSV files
            $output->writeln('<info>Generating CSV files from sitemaps...</info>');
            $csvFiles = $this->sitemapParser->parseSitemapsAndGenerateCsv();

            if (empty($csvFiles)) {
                $output->writeln('<comment>No CSV files were generated from sitemaps.</comment>');
                return 0;
            }

            // Display the generated CSV file paths
            $output->writeln("<info>Generated " . count($csvFiles) . " CSV file(s):</info>");
            foreach ($csvFiles as $csvFile) {
                $output->writeln("  <comment>" . basename($csvFile) . "</comment>");
            }

            // Execute cache warmup for each generated CSV file
            $output->writeln('<info>Starting cache warming...</info>');

            $successCount = 0;
            $errorCount = 0;

            foreach ($csvFiles as $csvPath) {
                $result = $this->cacheWarmer->warmUp($csvPath);

                if ($result['status'] === 'success') {
                    $output->writeln("<info>Cache warming completed successfully for: " . basename($csvPath) . "</info>");
                    $successCount++;
                } else {
                    $output->writeln("<error>Cache warming failed for " . basename($csvPath) . ": {$result['message']}</error>");
                    $errorCount++;

                    // Display any error output
                    if (isset($result['output']) && !empty(trim($result['output']))) {
                        $output->writeln("\n<comment>Error Output:</comment>");
                        $output->writeln(str_repeat('-', 50));
                        $output->writeln($result['output']);
                        $output->writeln(str_repeat('-', 50));
                    }
                }
            }

            $this->sitemapParser->deleteGeneratedCsvFiles();
            // Display summary
            $output->writeln("");
            $output->writeln("<info>Cache warming Summary:</info>");
            $output->writeln("  <comment>Total files processed: " . count($csvFiles) . "</comment>");
            $output->writeln("  <info>Successful warmups: " . $successCount . "</info>");
            $output->writeln("  <error>Failed warmups: " . $errorCount . "</error>");

            if ($errorCount > 0) {
                return 1;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Unexpected error: " . $e->getMessage() . "</error>");
            $output->writeln("<error>Stack trace:</error>");
            $output->writeln($e->getTraceAsString());
            return 1;
        } finally {
            // Always remove the lock flag
            $this->lockManager->removeLockFlag();
        }

        return 0;
    }
}
