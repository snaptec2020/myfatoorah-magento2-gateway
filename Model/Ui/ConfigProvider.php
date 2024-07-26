<?php

namespace MyFatoorah\Gateway\Model\Ui;

use MyFatoorah\Gateway\Gateway\Config\Config;
use MyFatoorah\Gateway\Helper\LocaleResolver;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentEmbedded;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Cart;

class ConfigProvider implements ConfigProviderInterface
{

    /**
     * @var Config
     */
    private $mfConfig;

    /**
     * @var LocaleResolver
     */
    private $locale;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Cart
     */
    private $cart;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param Config          $mfConfig
     * @param LocaleResolver  $localeResolver
     * @param CustomerSession $customerSession
     * @param Cart            $cart
     */
    public function __construct(
        Config $mfConfig,
        LocaleResolver $localeResolver,
        CustomerSession $customerSession,
        Cart $cart
    ) {
        $this->mfConfig        = $mfConfig;
        $this->locale          = $localeResolver;
        $this->customerSession = $customerSession;
        $this->cart            = $cart;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Fill the config array of MyFatoorah module
     *
     * @return array
     */
    public function getConfig()
    {

        $config = [
            'title'       => $this->mfConfig->getTitle(),
            'listOptions' => $this->mfConfig->getKeyGateways(),
        ];

        if ($config['listOptions'] == 'multigateways') {
            try {
                $config = $this->fillMultigatewaysData($config);
            } catch (\Exception $ex) {
                $config['mfError'] = $ex->getMessage();
            }
        }

        return [
            'payment' => [
                Config::CODE => $config
            ]
        ];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Fill the config array for the listing all payment methods on the checkout page
     *
     * @param  array $config
     * @return array
     */
    private function fillMultigatewaysData($config)
    {

        $config['lang']   = $this->locale->getLangCode();
        $config['isTest'] = $this->mfConfig->isTesting();

        $isApRegistered = $this->mfConfig->isApplePayRegistered();

        $mfObj = $this->mfConfig->getMyfatoorahObject(MyFatoorahPaymentEmbedded::class);

        /**
         * @var \Magento\Quote\Model\Quote $quote
         */
        $quote = $this->cart->getQuote();

        $invoiceCurrency = $this->mfConfig->getInvoiceCurrency();
        if ($invoiceCurrency == 'websites') {
            $total    = $quote->getGrandTotal();
            $currency = $quote->getCurrency()->getQuoteCurrencyCode();
        } else {
            $total    = $quote->getBaseGrandTotal();
            $currency = $quote->getBaseCurrencyCode();
        }

        $config['baseGrandTotal'] = $quote->getBaseGrandTotal();
        $config['paymentMethods'] = $mfObj->getCheckoutGateways($total, $currency, $isApRegistered);

        $all = $config['paymentMethods']['all'];
        if (count($all) == 1) {
            $config['title'] = ($config['lang'] == 'ar') ? $all[0]->PaymentMethodAr : $all[0]->PaymentMethodEn;
        }

        //draw form section
        if (!empty($config['paymentMethods']['form']) ||
                !empty($config['paymentMethods']['ap']) ||
                !empty($config['paymentMethods']['gp'])
        ) {
            $customerId = $this->customerSession->getCustomer()->getId();

            $config['height'] = '130';
            $userDefinedField = '';
            if ($this->mfConfig->getSaveCard() && $customerId) {
                $config['height'] = '180';
                $userDefinedField = 'CK-' . $customerId;
            }

            $initSession           = $mfObj->getEmbeddedSession($userDefinedField);
            $config['countryCode'] = $initSession->CountryCode;
            $config['sessionId']   = $initSession->SessionId;
        }
        return $config;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
