<?php

namespace MyFatoorah\Gateway\Model\Config\Source;

class GatewayAction implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'myfatoorah', 'label' => __('Redirect to MyFatoorah Invoice Page')],
            ['value' => 'multigateways', 'label' => __('List All Enabled Gateways in Checkout Page')],
        ];
    }
}
