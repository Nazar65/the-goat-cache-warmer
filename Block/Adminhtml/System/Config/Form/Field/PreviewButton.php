<?php

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Config\Block\System\Config\Form\Field;

/**
 * Class PreviewButton
 */
class PreviewButton extends Field implements RendererInterface
{
    /**
     * @var string
     */
    protected $_template = 'Goat_TheCacheWarmer::system/config/form/field/preview-button.phtml';


    /**
     * Retrieve Element HTML fragment
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return parent::_getElementHtml($element) . $this->_toHtml();
    }
    /**
     * Get URL for preview action
     *
     * @param string $path
     * @param array $params
     * @return string
     */
    public function getPreviewUrl($path, $params = [])
    {
        return $this->getUrl($path, $params);
    }
}
