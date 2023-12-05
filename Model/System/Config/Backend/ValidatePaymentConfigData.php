<?php

namespace MyFatoorah\Gateway\Model\System\Config\Backend;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use MyFatoorah\Library\API\Payment\MyFatoorahPayment;

class ValidatePaymentConfigData extends MFValue
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param WriterInterface       $configWriter
     * @param ManagerInterface      $msg
     * @param Context               $context
     * @param Registry              $registry
     * @param ScopeConfigInterface  $config
     * @param TypeListInterface     $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null       $resourceCollection
     * @param array                 $data
     */
    public function __construct(
        WriterInterface $configWriter,
        ManagerInterface $msg,
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($msg, $context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->configWriter = $configWriter;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {

        if (!$this->getValue()) {
            $this->disableShipping();
            return parent::beforeSave();
        }

        $config = [
            'apiKey'      => $this->getMFPaymentFieldSet('api_key'),
            'isTest'      => (bool) $this->getMFPaymentFieldSet('is_testing'),
            'countryCode' => $this->getMFPaymentFieldSet('countryMode'),
        ];

        $mfObj = new MyFatoorahPayment($config);

        try {
            $paymentMethods = $mfObj->initiatePayment();
        } catch (\Exception $ex) {
            return $this->disablePaymentWithError(
                'MyFatoorah: can not enable MyFatoorah Payment due to: '
                            . $ex->getMessage()
            );
        }

        if (empty($paymentMethods)) {
            return $this->disablePaymentWithError(
                'MyFatoorah: please, contact your account manager '
                            . 'to activate at least one of the available payment methods in your account '
                            . 'to enable the payment model.'
            );
        }

        return parent::beforeSave();
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Disable the payment module, display the error, and disable the shipping module
     *
     * @param  string $err
     * @return $this
     */
    private function disablePaymentWithError($err)
    {
        $this->disableShipping();

        return $this->disableConfigWithMessage($err);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Disable the shipping
     */
    private function disableShipping()
    {

        //must be call before the code, don't use __construct
        $scope   = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $storeId = $this->getScopeId();

        $key = 'carriers/myfatoorah_shipping/active';

        $isShippingActive = $this->_config->getValue($key, $scope, $storeId);
        if ($isShippingActive) {
            $this->configWriter->save($key, '0', $scope, $storeId);
            $this->messageManager->addWarning(__('Warning: MyFatoorah Shipping is disabled'));
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
