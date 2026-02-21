<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Goat\TheCacheWarmer\Api\CacheWarmerInterface;
use Goat\TheCacheWarmer\Model\Config;
use Goat\TheCacheWarmer\Service\UrlParser;

class WarmupCommand extends Command
{
    /**
     * @var UrlParser
     */
    private $urlParser;

    /**
     * @var CacheWarmerInterface
     */
    private $cacheWarmer;

    /**
     * @var Config
     */
    private $config;


    public function __construct(
        UrlParser $urlParser,
        CacheWarmerInterface $cacheWarmer,
        Config $config,
        string $name = null
    ) {
        $this->urlParser = $urlParser;
        $this->cacheWarmer = $cacheWarmer;
        $this->config = $config;

        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('cache:warmup:manual')
            ->setDescription('Manually generate CSV from access logs and warm up cache with console output')
            ->addOption(
                'log-file',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to nginx access log file (overrides config)',
                ''
            )
            ->addOption(
                'csv-file',
                null,
                InputOption::VALUE_OPTIONAL,
                'Use existing CSV file instead of generating from logs'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln('<info>Starting manual cache warmup...</info>');

            // Get log file path
            $logFilePath = $this->getLogFile($input);

            if (empty($logFilePath)) {
                $output->writeln('<error>Error: Log file path not provided and not configured in admin panel.</error>');
                return 1;
            }

            // Check if log file exists
            if (!file_exists($logFilePath)) {
                $output->writeln("<error>Log file does not exist: {$logFilePath}</error>");
                return 1;
            }

            $csvPath = null;

            // If CSV file is specified, use it directly
            $specifiedCsvFile = $input->getOption('csv-file');
            if ($specifiedCsvFile) {
                if (!file_exists($specifiedCsvFile)) {
                    $output->writeln("<error>Specified CSV file does not exist: {$specifiedCsvFile}</error>");
                    return 1;
                }
                $csvPath = $specifiedCsvFile;
                $output->writeln("<info>Using specified CSV file: {$csvPath}</info>");
            } else {
                // Generate CSV from access log
                $output->writeln("<info>Generating CSV from log file: {$logFilePath}</info>");
                $csvPath = $this->urlParser->parseLogAndGenerateCsv($logFilePath);
                $output->writeln("<info>CSV generated successfully at: {$csvPath}</info>");
            }

            // Execute cache warmup
            $output->writeln('<info>Starting cache warming...</info>');

            $result = $this->cacheWarmer->warmUp($csvPath);

            if ($result['status'] === 'success') {
                $output->writeln('<info>Cache warming completed successfully!</info>');

                // Display results in a formatted way
                if (isset($result['output']) && !empty(trim($result['output']))) {
                    $output->writeln("\n<comment>Output:</comment>");
                    $output->writeln(str_repeat('-', 50));
                    $output->writeln($result['output']);
                    $output->writeln(str_repeat('-', 50));
                } else {
                    $output->writeln('<info>No output from warming process.</info>');
                }
            } else {
                $output->writeln("<error>Cache warming failed: {$result['message']}</error>");

                // Display any error output
                if (isset($result['output']) && !empty(trim($result['output']))) {
                    $output->writeln("\n<comment>Error Output:</comment>");
                    $output->writeln(str_repeat('-', 50));
                    $output->writeln($result['output']);
                    $output->writeln(str_repeat('-', 50));
                }

                return 1;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Unexpected error: " . $e->getMessage() . "</error>");
            $output->writeln("<error>Stack trace:</error>");
            $output->writeln($e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Get log file path from input or configuration
     */
    private function getLogFile(InputInterface $input): string
    {
        // If specified in command line, use that
        $logFile = $input->getOption('log-file');
        if (!empty($logFile)) {
            return $logFile;
        }

        // Otherwise check config
        return $this->config->getLogFile();
    }
}
