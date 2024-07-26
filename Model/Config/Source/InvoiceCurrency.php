<?php

namespace MyFatoorah\Gateway\Model\Config\Source;

class InvoiceCurrency implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'default', 'label' => __('Default Currency')],
            ['value' => 'websites', 'label' => __('Website Currency')],
        ];
    }
}
