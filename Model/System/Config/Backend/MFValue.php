<?php

namespace MyFatoorah\Gateway\Model\System\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;

class MFValue extends Value
{

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var string
     */
    protected $scope;

    /**
     * @var string|integer
     */
    protected $storeId;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_config;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param ManagerInterface      $messageManager
     * @param Context               $context
     * @param Registry              $registry
     * @param ScopeConfigInterface  $config
     * @param TypeListInterface     $cacheTypeList
     * @param AbstractResource|null $resource
     * @param AbstractDb|null       $resourceCollection
     * @param array                 $data
     */
    public function __construct(
        ManagerInterface $messageManager,
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->messageManager = $messageManager;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Disable the payment module, and display the message
     *
     * @param  string $msg
     * @param  string $type
     * @return $this
     */
    protected function disableConfigWithMessage($msg, $type = 'Error')
    {
        $this->messageManager->{"add$type"}(__($msg));
        $this->setValue('0');
        return parent::beforeSave();
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Return the MyFatoorah shipping config value
     *
     * @param  string $key
     * @return mixed
     */
    protected function getMFPaymentFieldSet($key)
    {
        $value = $this->getFieldsetDataValue($key);
        if (!isset($value)) {
            //get the default store value
            $value = $this->_config->getValue('payment/myfatoorah_payment/' . $key);
        }

        return $value;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
