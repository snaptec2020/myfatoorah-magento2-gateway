<?php

namespace MyFatoorah\Gateway\Model\System\Config\Backend;

use Magento\Framework\UrlInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use MyFatoorah\Library\API\Payment\MyFatoorahPayment;

class ValidateApplePayRegistered extends MFValue
{
    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param UrlInterface          $urlInterface
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
        UrlInterface $urlInterface,
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
        $this->urlInterface = $urlInterface;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {

        //if any don't register
        if (!$this->getValue() || !$this->getFieldsetDataValue('active')) {
            return parent::beforeSave();
        }

        //check list option
        if ($this->getMFPaymentFieldSet('list_options') == 'myfatoorah') {
            $msg = 'MyFatoorah: registering your domain with MyFatoorah and Apple Pay works only '
                    . 'if you select "List All Enabled Gateways in Checkout Page" '
                    . 'from the "List Payment Options" option.';

            return $this->disableConfigWithMessage($msg, 'Warning');
        }

        //register
        $config = [
            'apiKey'      => $this->getMFPaymentFieldSet('api_key'),
            'isTest'      => (bool) $this->getMFPaymentFieldSet('is_testing'),
            'countryCode' => $this->getMFPaymentFieldSet('countryMode'),
        ];

        $mfObj = new MyFatoorahPayment($config);

        $siteURL = $this->urlInterface->getCurrentUrl();
        try {
            $data = $mfObj->registerApplePayDomain($siteURL);
            if ($data->Message == 'OK') {
                return parent::beforeSave();
            }
            $err = $data->Message;
        } catch (\Exception $ex) {
            $err = 'MyFatoorah: can not register Apple Pay due to: ' . $ex->getMessage();
        }

        //disableWithError
        return $this->disableConfigWithMessage($err);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
