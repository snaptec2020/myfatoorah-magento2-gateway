<?php

namespace MyFatoorah\Gateway\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Exception\LocalizedException;
use MyFatoorah\Library\API\MyFatoorahList;
use MyFatoorah\Library\API\MyFatoorahShipping;
use MyFatoorah\Library\MyFatoorah;
use MyFatoorah\Gateway\Helper\Checkout;
use Exception as MFException;

class Shipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * Carrier's code
     *
     * @var string
     */
    protected $_code = 'myfatoorah_shipping';

    /**
     * Whether this carrier has fixed rates calculation
     *
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var MethodFactory
     */
    private $rateMethodFactory;

    /**
     * @var \MyFatoorah\Library\API\MyFatoorahShipping
     */
    private $sMFObj;

    /**
     * @var array
     */
    private $mfShippingMethods = [1 => 'DHL', 2 => 'Aramex'];

    /**
     * @var Checkout
     */
    protected $checkoutHelper;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     *
     * @var Manager
     */
    private $moduleManager;

    /**
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory
     */
    protected $_rateErrorFactory;

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param Manager               $moduleManager
     * @param StoreManagerInterface $storeManager
     * @param ResultFactory         $resultFactory
     * @param MethodFactory         $methodFactory
     * @param ScopeConfigInterface  $scopeConfig
     * @param ErrorFactory          $rateErrorFactory
     * @param LoggerInterface       $logger
     * @param Checkout              $checkoutHelper
     * @param array                 $data
     */
    public function __construct(
        Manager $moduleManager,
        StoreManagerInterface $storeManager,
        ResultFactory $resultFactory,
        MethodFactory $methodFactory,
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        Checkout $checkoutHelper,
        array $data = []
    ) {

        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

        $this->moduleManager = $moduleManager;
        $this->storeManager  = $storeManager;

        $this->rateResultFactory = $resultFactory;
        $this->rateMethodFactory = $methodFactory;
        $this->checkoutHelper    = $checkoutHelper;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Generates list of allowed carrier`s shipping methods to be displayed on cart price rules page
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return [
            $this->getCarrierCode() => $this->getConfigData('title'),
                //            $this->getCarrierCode() => __('Aramex')
        ];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Collect and get rates for storefront
     *
     * @param  RateRequest $request
     * @return DataObject|bool|array
     */
    public function collectRates(RateRequest $request)
    {
        //todo need to fix the cart page
        $configData = $this->getMFPaymentConfigData();

        //return false if no MyFatoorah payment methold is not enabled
        if (!$configData) {
            return false;
        }

        //logging
        MyFatoorah::$loggerObj = MFSHIPPING_LOG_FILE;
        MyFatoorah::log("---------------------------------------------------------------------------------------");

        try {
            $currency = $this->storeManager->getStore()->getBaseCurrency()->getCode();

            $mfListObj    = new MyFatoorahList($configData);
            $currencyRate = $mfListObj->getCurrencyRate($currency);

            $curlData = [
                'Items'       => $this->getShippingInvoiceItems($request),
                'CityName'    => $request->getDestCity(),
                'PostalCode'  => $request->getDestPostcode(),
                'CountryCode' => $request->getDestCountryId()
            ];

            $rateResult = $this->rateResultFactory->create();

            $configMethods    = $this->getConfigData('methods');
            $availableMethods = empty($configMethods) ? [] : explode(',', $configMethods);

            $sMFObj = new MyFatoorahShipping($configData);
            foreach ($availableMethods as $id) {
                $curlData['ShippingMethod'] = $id;

                $json = $sMFObj->calculateShippingCharge($curlData);

                $realVal = floor($json->Fees * 1000) / 1000;

                $shippingAmount = $currencyRate * $realVal;
                $rateResult->append($this->createShippingMethod($shippingAmount, $id));
            }
            return $rateResult;
        } catch (MFException $ex) {
            MyFatoorah::log('In Shipping exception block - ' . $ex->getMessage());
            //return [];
            return $this->getError($ex->getMessage());
        }
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Set error message for the carrier
     *
     * @param  string $message
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Error
     */
    private function getError($message)
    {
        /* @var \Magento\Quote\Model\Quote\Address\RateResult\Error $error */
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier($this->getCarrierCode());
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setErrorMessage($message);
        return $error;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get Invoice Items
     *
     * @param  RateRequest $request
     * @return array
     * @throws LocalizedException
     */
    private function getShippingInvoiceItems($request)
    {
        $items = $request->getAllItems();

        $orderItemsArr = $this->checkoutHelper->getOrderItems($items, 1, true, false);
        return $orderItemsArr['invoiceItemsArr'];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Create Shipping Method
     *
     * @param  mixed $shippingAmount
     * @param  mixed $id
     * @return object
     */
    private function createShippingMethod($shippingAmount, $id)
    {

        $method = $this->rateMethodFactory->create();

        //Set carrier's data
        $method->setCarrier($this->getCarrierCode());
        $method->setCarrierTitle($this->getConfigData('title'));

        //Set method under Carrier
        $method->setMethod($id);
        $method->setMethodTitle(__($this->mfShippingMethods[$id]));

        //set price
        $method->setPrice($shippingAmount);
        $method->setCost($shippingAmount);

        return $method;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Retrieve information from carrier configuration
     *
     * @return array
     */
    private function getMFPaymentConfigData()
    {
        $scope = ScopeInterface::SCOPE_STORE;

        $store   = $this->storeManager->getStore();
        $storeId = $store->getId();

        $module = $this->moduleManager;
        $config = $this->_scopeConfig;

        $path = 'payment/myfatoorah_payment/';

        if (!$module->isEnabled('MyFatoorah_Gateway') || !$config->getValue($path . 'active', $scope, $storeId)) {
            return [];
        }

        return [
            'apiKey'      => $config->getValue($path . 'api_key', $scope, $storeId),
            'isTest'      => (bool) $config->getValue($path . 'is_testing', $scope, $storeId),
            'countryCode' => $config->getValue($path . 'countryMode', $scope, $storeId),
            'loggerObj'   => MFSHIPPING_LOG_FILE
        ];
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
}
