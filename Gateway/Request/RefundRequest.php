<?php

namespace MyFatoorah\Gateway\Gateway\Request;

use MyFatoorah\Gateway\Gateway\Config\Config;
use MyFatoorah\Library\API\MyFatoorahRefund;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundRequest implements BuilderInterface
{
    /**
     * @var Config
     */
    private $mfConfig;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param Config $mfConfig
     */
    public function __construct(
        Config $mfConfig
    ) {
        $this->mfConfig = $mfConfig;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Builds ENV request
     * From: https://github.com/magento/magento2/blob/2.1.3/app/code/Magento/Payment/Model/Method/Adapter.php
     * The $buildSubject contains:
     * 'payment' => $this->getInfoInstance()
     * 'paymentAction' => $paymentAction
     * 'stateObject' => $stateObject
     * 'amount'
     *
     * @param array $buildSubject
     *
     * @return array
     */
    public function build(array $buildSubject)
    {
        return [
            'GATEWAY_REFUND_GATEWAY_URL' => $this->mfConfig->getRefundUrl(),
            'GATEWAY_MF_REFUND_OBJ'      => $this->mfConfig->getMyfatoorahObject(MyFatoorahRefund::class),
        ];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
