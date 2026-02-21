<?php

/**
 * Copyright (c) 2026 TheGoat team. All rights reserved.
 * See https://example.com/license for license details.
 */

namespace Goat\TheCacheWarmer\Block\Adminhtml\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class IgnoredUserAgent extends AbstractFieldArray
{
    /**
     * {@inheritdoc}
     */
    protected function _construct()
    {
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add New Expression');

        $this->addColumn(
            'regex_expression',
            ['label' => __('User-agent'), 'class' => 'expressions required-entry']
        );

        parent::_construct();
    }
}
