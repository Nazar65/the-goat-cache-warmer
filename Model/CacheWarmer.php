<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license information.
 */

namespace Goat\TheCacheWarmer\Model;

use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use Goat\TheCacheWarmer\Api\CacheWarmerInterface;
use \Magento\Framework\Module\Dir\Reader;

class CacheWarmer implements CacheWarmerInterface
{
    /**
     * @param Config $config
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     */
    public function __construct(
        private Config $config,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
        private Reader $modulereader,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function warmUp(string $csvFilePath = null): array
    {
        if (!$this->config->isEnabled()) {
            return ['status' => 'disabled', 'message' => 'Cache warmer is disabled'];
        }

        $pythonPath = $this->config->getPythonPath();
        $configOption = $this->config->getConfigOption();
        $timeout = (int) $this->config->getTimeout();
        $threads = (int) $this->config->getThreads();
        $scriptPath = $this->modulereader->getModuleDir('', 'Goat_TheCacheWarmer') . "/src/warmer.py";

        // Use the passed CSV file path or fall back to config
        $csvSourceFilePaths = '';
        if ($csvFilePath !== null) {
            // Validate that the provided CSV file exists
            if (!file_exists($csvFilePath)) {
                return [
                    'status' => 'error',
                    'message' => 'CSV file does not exist: ' . $csvFilePath
                ];
            }
            $csvSourceFilePaths = escapeshellarg($csvFilePath);
        } else {
            // Use configured CSV files from admin panel
            $csvFilesConfig = $this->config->getCsvFiles();

            if (!empty($csvFilesConfig)) {
                // Split by comma to get individual file paths and create absolute paths
                $csvFiles = explode(',', $csvFilesConfig);
                $absolutePaths = [];

                foreach ($csvFiles as $file) {
                    $file = trim($file);
                    if (!empty($file)) {
                        // Check if it's an absolute path or relative to media directory
                        if (strpos($file, '/') === 0) {
                            $absolutePaths[] = $file;
                        } else {
                            // If relative path, make it absolute using media directory
                            $mediaPath = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)->getAbsolutePath();
                            $absolutePaths[] = rtrim($mediaPath, '/') . '/' . ltrim($file, '/');
                        }
                    }
                }

                if (!empty($absolutePaths)) {
                    // Join file paths with spaces for command line
                    $csvSourceFilePaths = implode(' ', array_map('escapeshellarg', $absolutePaths));
                }
            }
        }

        if (empty($csvSourceFilePaths)) {
            $this->logger->info(
                'Cache warming not executed, empty warming list files.'
            );

            return [
                'status' => 'empty_source_files',
                'message' => 'Cache warming not executed, empty warming list files.'
            ];
        }

        // Build the command with all available options
        $command = sprintf(
            '%s %s --json-config %s --files %s',
            escapeshellcmd($pythonPath),
            escapeshellcmd($scriptPath),
            escapeshellarg($configOption),
            $csvSourceFilePaths
        );

        // Add timeout if specified (if it's a valid integer > 0)
        if ($timeout > 0) {
            $command = sprintf('%s --timeout %d', $command, $timeout);
        }

        // Add rate limit if specified (if it's a valid integer >= 0)
        $rateLimit = $this->config->getRateLimit();
        if ($rateLimit >= 0) {
            $command = sprintf('%s --rate-limit %d', $command, $rateLimit);
        }

        // Check if threading should be used
        if ($this->config->useThreads()) {
            // Add threads count if specified (if it's a valid integer > 0)
            if ($threads > 0) {
                $command = sprintf('%s --threads %d', $command, $threads);
            }
        } else {
            // Disable threading by adding --without-async flag
            $command = sprintf('%s --without-async 1', $command);
        }

        try {
            $output = [];
            $returnCode = 0;

            // Execute the command and capture output
            exec($command, $output, $returnCode);

            $this->logger->info(
                'Cache warming executed with command: ' . $command,
                [
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode
                ]
            );

            return [
                'status' => $returnCode === 0 ? 'success' : 'error',
                'message' => 'Cache warming completed',
                'output' => implode("\n", $output),
                'return_code' => $returnCode
            ];
        } catch (\Exception $e) {
            $this->logger->error('Cache warming failed: ' . $e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Cache warming failed: ' . $e->getMessage()
            ];
        }
    }
}
