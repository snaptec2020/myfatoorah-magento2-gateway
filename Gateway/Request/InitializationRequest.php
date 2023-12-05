<?php

namespace MyFatoorah\Gateway\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Model\Order;

class InitializationRequest implements BuilderInterface
{
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Builds ENV request
     * From: https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Payment/Model/Method/Adapter.php
     * The $buildSubject contains:
     * 'payment' => $this->getInfoInstance()
     * 'paymentAction' => $paymentAction
     * 'stateObject' => $stateObject
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build(array $buildSubject)
    {
        $stateObject = $buildSubject['stateObject'];

        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);

        return ['IGNORED' => ['IGNORED']];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
