<?php

namespace MyFatoorah\Gateway\Model\Config\Source;

class FailurePage implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'cart', 'label' => __('Redirect to Cart Page')],
            ['value' => 'checkout_onepage_failure', 'label' => __('Redirect to Checkout Onepage Failure Page')],
        ];
    }
}
