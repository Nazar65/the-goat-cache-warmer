<?php

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Class Preview
 */
class Preview extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @param Action\Context $context
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    /**
     * Preview config file content
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        // Get the file path from request parameter
        $filePath = $this->getRequest()->getParam('file_path');

        if (!$filePath) {
            return $resultJson->setData([
                'error' => 'No configuration file specified.'
            ]);
        }

        // Validate that this is a config file (to prevent directory traversal)
        $configDir = BP . '/pub/media/cacheWarmerConfig/';

        // Read and return the content of config file
        try {
            $content = file_get_contents($configDir . $filePath);

            if ($content === false) {
                return $resultJson->setData([
                    'error' => 'Could not read configuration file.'
                ]);
            }

            // Return JSON with file content
            return $resultJson->setData([
                'content' => htmlspecialchars_decode($content)
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'error' => 'Error reading configuration file: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Check if the user has required permissions
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Goat_TheCacheWarmer::config');
    }
}
