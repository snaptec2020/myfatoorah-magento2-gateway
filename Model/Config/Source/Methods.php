<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace MyFatoorah\Gateway\Model\Config\Source;

/**
 * Class GatewayAction
 */
class Methods implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => __('DHL')],
            ['value' => '2', 'label' => __('Aramex')]
        ];
    }
}
