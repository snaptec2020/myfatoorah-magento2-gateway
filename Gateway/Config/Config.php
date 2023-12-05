<?php

namespace MyFatoorah\Gateway\Gateway\Config;

/**
 * Class Config.
 * Values returned from Magento\Payment\Gateway\Config\Config.getValue()
 * are taken by default from ScopeInterface::SCOPE_STORE
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    public const CODE                                 = 'myfatoorah_payment';
    public const PLUGIN_VERSION                       = '2.2.6';
    public const KEY_ACTIVE                           = 'active';
    public const KEY_TITLE                            = 'title';
    public const KEY_COUNTRY_MODE                     = 'countryMode';
    public const KEY_IS_TESTING                       = 'is_testing';
    public const KEY_API_KEY                          = 'api_key';
    public const KEY_TOKENIZATION                     = 'save_card';
    public const KEY_LISTINVOICEITEMS                 = 'listInvoiceItems';
    public const KEY_GATEWAYS                         = 'list_options';
    public const KEY_IS_APPLE_PAY_REGISTERED          = 'isApplePayRegistered';
    public const KEY_MYFATOORAH_APPROVED_ORDER_STATUS = 'myfatoorah_approved_order_status';
    public const KEY_AUTOMATIC_INVOICE                = 'automatic_invoice';
    public const KEY_EMAIL_CUSTOMER                   = 'email_customer';
    public const KEY_FAILURE_PAGE                     = 'failurePage';

    /**
     * Get Title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getValue(self::KEY_TITLE);
    }

    /**
     * Get the MyFatoorah refund gateway URL
     *
     * @return string
     */
    public function getRefundUrl()
    {
        return ('https://' . ( $this->isTesting() ? 'apitest' : 'api' ) . '.myfatoorah.com/v2/MakeRefund');
    }

    /**
     * Get API Key
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->getValue(self::KEY_API_KEY);
    }

    /**
     * Get MyFatoorah Approved Order Status
     *
     * @return string
     */
    public function getMyFatoorahApprovedOrderStatus()
    {
        return $this->getValue(self::KEY_MYFATOORAH_APPROVED_ORDER_STATUS);
    }

    /**
     * Check if customer is to be notified
     *
     * @return int
     */
    public function isEmailCustomer()
    {
        return $this->getValue(self::KEY_EMAIL_CUSTOMER);
    }

    /**
     * Check if customer is to be notified
     *
     * @return boolean
     */
    public function isAutomaticInvoice()
    {
        return (bool) $this->getValue(self::KEY_AUTOMATIC_INVOICE);
    }

    /**
     * Get Payment configuration status
     *
     * @return bool
     */
    public function isActive()
    {
        return (bool) $this->getValue(self::KEY_ACTIVE);
    }

    /**
     * Get if doing test transactions (request send to sandbox gateway)
     *
     * @return boolean
     */
    public function isTesting()
    {
        return (bool) $this->getValue(self::KEY_IS_TESTING);
    }

    /**
     * Get if doing test transactions (request send to sandbox gateway)
     *
     * @return string
     */
    public function getCounrtyMode()
    {
        return $this->getValue(self::KEY_COUNTRY_MODE);
    }

    /**
     * Get the version number of this plugin itself
     *
     * @return string
     */
    public function getVersion()
    {
        return self::PLUGIN_VERSION;
    }

    /**
     * Get the plugin code
     *
     * @return string
     */
    public function getCode()
    {
        return self::CODE;
    }

    /**
     * Get Key gateways
     *
     * @return string
     */
    public function getKeyGateways()
    {
        return $this->getValue(self::KEY_GATEWAYS);
    }

    /**
     * Get isApplePayRegistered configuration status
     *
     * @return bool
     */
    public function isApplePayRegistered()
    {
        return (bool) $this->getValue(self::KEY_IS_APPLE_PAY_REGISTERED);
    }

    /**
     * Get Save Card
     *
     * @return string
     */
    public function getSaveCard()
    {
        return $this->getValue(self::KEY_TOKENIZATION);
    }

    /**
     * Get List Invoice Item
     *
     * @return boolean
     */
    public function listInvoiceItems()
    {
        return (bool) $this->getValue(self::KEY_LISTINVOICEITEMS);
    }

    /**
     * Get List Invoice Item
     *
     * @return boolean
     */
    public function getFailurePage()
    {
        return $this->getValue(self::KEY_FAILURE_PAGE);
    }

    /**
     * Get MyFatoorah object
     *
     * @param object $class
     *
     * @return object
     */
    public function getMyfatoorahObject($class)
    {
        $config = [
            'apiKey'      => $this->getApiKey(),
            'isTest'      => $this->isTesting(),
            'countryCode' => $this->getCounrtyMode(),
            'loggerObj'   => MYFATOORAH_LOG_FILE
        ];

        return new $class($config);
    }
}
