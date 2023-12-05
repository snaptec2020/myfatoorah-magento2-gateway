<?php

namespace MyFatoorah\Gateway\Plugin;

use Magento\Payment\Model\MethodList;
use Magento\Quote\Api\Data\CartInterface;

class MethodListPlugin
{
    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Display only MyFatooah gateways for MyFatoorah Shipping
     *
     * @param MethodList    $subject
     * @param array         $availableMethods
     * @param CartInterface $quote
     *
     * @return array
     */
    public function afterGetAvailableMethods(MethodList $subject, $availableMethods, CartInterface $quote = null)
    {

        $shippingMethod = $quote ? $quote->getShippingAddress()->getShippingMethod() : '';

        if ($shippingMethod == 'myfatoorah_shipping_1' || $shippingMethod == 'myfatoorah_shipping_2') {
            foreach ($availableMethods as $key => $method) {
                if ($method->getCode() != 'myfatoorah_payment') {
                    unset($availableMethods[$key]);
                }
            }
        }
        return $availableMethods;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
}
