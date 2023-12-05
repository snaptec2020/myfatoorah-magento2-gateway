<?php

namespace MyFatoorah\Gateway\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use MyFatoorah\Gateway\Helper\Checkout;
use MyFatoorah\Gateway\Helper\LocaleResolver;
use MyFatoorah\Gateway\Gateway\Config\Config;
use MyFatoorah\Gateway\Model\MyfatoorahInvoice;
use MyFatoorah\Library\MyFatoorah;
use MyFatoorah\Library\API\Payment\MyFatoorahPayment;
use Exception as MFException;

class Index extends MyfatoorahAction
{
    /**
     *
     * @var Checkout
     */
    private $checkoutHelper;

    /**
     * @var LocaleResolver
     */
    private $locale;

    /**
     * @var ProductMetadataInterface
     */
    private $magMetadata;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var MyfatoorahInvoice
     */
    private $mfInvoiceModel;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param Context                  $context
     * @param Config                   $mfConfig
     * @param Session                  $checkoutSession
     * @param Checkout                 $checkoutHelper
     * @param LocaleResolver           $localeResolver
     * @param ProductMetadataInterface $metadata
     * @param ScopeConfigInterface     $scopeConfig
     * @param MyfatoorahInvoice        $mfInvoiceModel
     */
    public function __construct(
        Context $context,
        Config $mfConfig,
        Session $checkoutSession,
        Checkout $checkoutHelper,
        LocaleResolver $localeResolver,
        ProductMetadataInterface $metadata,
        ScopeConfigInterface $scopeConfig,
        MyfatoorahInvoice $mfInvoiceModel
    ) {
        parent::__construct($context, $mfConfig, $checkoutSession);
        $this->checkoutHelper = $checkoutHelper;
        $this->locale         = $localeResolver;
        $this->magMetadata    = $metadata;
        $this->scopeConfig    = $scopeConfig;
        $this->mfInvoiceModel = $mfInvoiceModel;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Process the order
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order) {
            return $this->redirectToCartPage('Unable to get the order. Possibly related to a failed database call');
        }
        if (!$order->getRealOrderId()) {
            return $this->redirectToCartPage('Order Session has been expired');
        }

        try {
            return $this->postToCheckout($order);
        } catch (MFException $ex) {
            $err = $ex->getMessage();
            $this->cancelCurrentOrder($order, 'Invoice Creation Error - ' . $err);
            return $this->redirectToCartPage($err);
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get the MyFatoorah Post Data
     *
     * @param  Order  $order
     * @param  string $gateway
     * @return array
     * @throws MFException
     */
    private function getPayload($order, $gateway = null)
    {
        $orderId = $order->getRealOrderId();
        $store   = $order->getStore();

        $addressObj = $order->getShippingAddress();
        if (!is_object($addressObj)) {
            $addressObj = $order->getBillingAddress();
            if (!is_object($addressObj)) {
                throw new MFException('Billing Address or Shipping address Data Should be set to create the invoice');
            }
        }

        $addressData = $addressObj->getData();

        $countryCode = ($addressData['country_id']) ?? '';
        $city        = ($addressData['city']) ?? '';
        $postcode    = ($addressData['postcode']) ?? '';
        $region      = ($addressData['region']) ?? '';

        $street1 = ($addressData['street']) ?? '';
        $street  = trim(preg_replace("/[\n]/", ' ', $street1 . ' ' . $region));

        $phoneNo = ($addressData['telephone']) ?? '';

        //$order->getCustomerName()  //$order->getCustomerFirstname() //$order->getCustomerLastname()
        $fName = !empty($addressObj->getFirstname()) ? $addressObj->getFirstname() : '';
        $lName = !empty($addressObj->getLastname()) ? $addressObj->getLastname() : '';

        $email = $order->getData('customer_email'); //$order->getCustomerEmail()

        $lang = $this->locale->getLangCode();

        $phone = MyFatoorah::getPhone($phoneNo);
        $url   = $this->getMfCheckoutUrl('process');
        //or
        //$url   = $store->getBaseUrl() . $this->mfConfig->getCode() . '/checkout/process';

        $isUserDefinedField = ($this->mfConfig->getSaveCard() && $order->getCustomerId());

        $osm        = $order->getShippingMethod();
        $mfShipping = ($osm == 'myfatoorah_shipping_1') ? 1 : (($osm == 'myfatoorah_shipping_2') ? 2 : null);

        $shippingConsignee = !$mfShipping ? '' : [
            'PersonName'   => "$fName $lName",
            'Mobile'       => trim($phone[1]),
            'EmailAddress' => $email,
            'LineAddress'  => trim(preg_replace("/[\n]/", ' ', $street . ' ' . $region)),
            'CityName'     => $city,
            'PostalCode'   => $postcode,
            'CountryCode'  => $countryCode
        ];

        $currency = $this->getCurrencyData($store, $gateway);

        //Invoice Items
        if ($mfShipping || $this->mfConfig->listInvoiceItems()) {
            $amount = 0;
            $items  = $this->checkoutHelper->getInvoiceItems($order, $currency['rate'], $mfShipping, $amount, true);
        } else {
            $amount = round($order->getBaseTotalDue() * $currency['rate'], 3);
            $items  = [[
            'ItemName'  => "Total Amount Order #$orderId",
            'Quantity'  => 1,
            'UnitPrice' => "$amount"
            ]];
        }

        //ExpiryDate
        //get Magento Pending Payment Order Lifetime (minutes)
        $ExpiryDateTxt = '';
        $expireAfter   = $this->getPendingOrderLifetime($order->getStoreId());
        if ($expireAfter) {
            $ExpiryDate    = new \DateTime('now', new \DateTimeZone('Asia/Kuwait'));
            $ExpiryDate->modify("+$expireAfter minute");
            $ExpiryDateTxt = $ExpiryDate->format('Y-m-d\TH:i:s');
        }

        $magVersion = $this->magMetadata->getVersion();
        $mfVersion  = $this->mfConfig->getCode() . ' ' . $this->mfConfig->getVersion();
        return [
            'CustomerName'       => $fName . ' ' . $lName,
            'InvoiceValue'       => "$amount",
            'DisplayCurrencyIso' => $currency['code'],
            'MobileCountryCode'  => trim($phone[0]),
            'CustomerMobile'     => trim($phone[1]),
            'CustomerEmail'      => $email,
            'CallBackUrl'        => $url,
            'ErrorUrl'           => $url,
            'Language'           => $lang,
            'CustomerReference'  => $orderId,
            'CustomerCivilId'    => null,
            'UserDefinedField'   => $isUserDefinedField ? 'CK-' . $order->getCustomerId() : null,
            'ExpiryDate'         => $ExpiryDateTxt,
            'SourceInfo'         => 'Magento2 ' . $magVersion . ' - ' . $mfVersion,
            'CustomerAddress'    => [
                'Block'               => '',
                'Street'              => '',
                'HouseBuildingNo'     => '',
                'Address'             => $city . ', ' . $region . ', ' . $postcode,
                'AddressInstructions' => $street
            ],
            'ShippingConsignee'  => $shippingConsignee,
            'ShippingMethod'     => $mfShipping,
            'InvoiceItems'       => $items
        ];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get the currency code and rate
     *
     * @param \Magento\Store\Model\Store $store
     * @param string                     $gateway
     *
     * @return array
     */
    private function getCurrencyData($store, $gateway = null)
    {
        $KWDcurrencyRate = (double) $store->getBaseCurrency()->getRate('KWD');
        if ($gateway == 'kn' && !empty($KWDcurrencyRate)) {
            $currencyCode = 'KWD';
            $currencyRate = $KWDcurrencyRate;
        } else {
            $currencyCode = $store->getBaseCurrencyCode();
            $currencyRate = 1;
            //(double) getCurrentCurrencyRate;
        }
        return ['code' => $currencyCode, 'rate' => $currencyRate];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Execute the MyFatoorah Payment
     *
     * @param Order $order
     *
     * @return \Magento\Framework\App\ResponseInterface
     *
     * @throws MFException
     */
    private function postToCheckout($order)
    {

        $orderId = $order->getRealOrderId();

        $gatewayId = $this->getRequest()->getParam('pm', 'myfatoorah');
        $sessionId = $this->getRequest()->getParam('sid', null);

        if (!$sessionId && !$gatewayId) {
            throw new MFException('Invalid Payment Session');
        }

        $curlData = $this->getPayload($order);

        $mfObj = $this->mfConfig->getMyfatoorahObject(MyFatoorahPayment::class);
        $data  = $mfObj->getInvoiceURL($curlData, $gatewayId, $orderId, $sessionId);

        //save the invoice id in myfatoorah_invoice table
        $this->mfInvoiceModel->addData(
            [
                    'order_id'     => $orderId,
                    'invoice_id'   => $data['invoiceId'],
                    'gateway_name' => 'MyFatoorah',
                    'invoice_url'  => $data['invoiceURL'],
                ]
        );
        $this->mfInvoiceModel->save();

        return $this->_redirect($data['invoiceURL']);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get Pending Order Lifetime from admin settings
     *
     * @param int|null $storeId
     *
     * @return string
     */
    private function getPendingOrderLifetime($storeId)
    {
        $scope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue('sales/orders/delete_pending_after', $scope, $storeId);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Cancel last placed order with specified comment message
     *
     * @param Order  $order
     * @param string $comment
     *
     * @return bool True if order cancelled, false otherwise
     */
    private function cancelCurrentOrder($order, $comment)
    {
        if ($order && $order->getId()) {
            $message = 'MyFatoorah: ' . $comment;
            if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
                $order->registerCancellation($message);
            } else {
                $order->addStatusHistoryComment($message);
            }
            $order->save();
            return true;
        }
        return false;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
