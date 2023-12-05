<?php

namespace MyFatoorah\Gateway\Model\System\Config\Backend;

use MyFatoorah\Library\API\MyFatoorahShipping;
use Magento\Framework\App\Config\ScopeConfigInterface;

class ValidateShippingConfigData extends MFValue
{
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function beforeSave()
    {

        if (!$this->getValue()) {
            return parent::beforeSave();
        }

        //must be call before the code, don't use __construct
        $scope   = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $storeId = $this->getScopeId();

        //check if payment is enabled
        $path = 'payment/myfatoorah_payment/';

        if (!$this->_config->getValue($path . 'active', $scope, $storeId)) {
            return $this->disableConfigWithMessage(
                'MyFatoorah: please, activate the MyFatoorah Payment to enable the shipping model.'
            );
        }

        //check if carriers are selected
        $methods = $this->getMFShippingMethodsFieldSet();

        if (empty($methods)) {
            return $this->disableConfigWithMessage(
                'MyFatoorah: please, select at least one of the carrier methods to enable the shipping model.'
            );
        }

        //check if carriers are correctly configured
        $config = [
            'apiKey'      => (string) $this->_config->getValue($path . 'api_key', $scope, $storeId),
            'isTest'      => (bool) $this->_config->getValue($path . 'is_testing', $scope, $storeId),
            'countryCode' => (string) $this->_config->getValue($path . 'countryMode', $scope, $storeId),
            'loggerObj'   => MFSHIPPING_LOG_FILE
        ];

        $mfObj = new MyFatoorahShipping($config);

        foreach ($methods as $m) {
            try {
                $shippingData = [
                    'ShippingMethod' => $m,
                    'Items'          => [[
                    'ProductName' => 'product',
                    'Description' => 'product',
                    'Weight'      => 10,
                    'Width'       => 10,
                    'Height'      => 10,
                    'Depth'       => 10,
                    'Quantity'    => 1,
                    'UnitPrice'   => '17.234'
                        ]],
                    'CountryCode'    => 'KW',
                    'CityName'       => 'adan',
                    'PostalCode'     => '12345',
                ];

                $mfObj->calculateShippingCharge($shippingData);
            } catch (\Exception $ex) {
                $error = str_replace("\n", '', $ex->getMessage()); // \n must be in "
                $type  = ($m == 1) ? 'DHL' : 'ARAMEX';

                $msg = "MyFatoorah: please, fix the $type error to enable the shipping model: $error";

                return $this->disableConfigWithMessage($msg);
            }
        }
        return parent::beforeSave();
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Return the MyFatoorah shipping config value
     *
     * @return mixed
     */
    private function getMFShippingMethodsFieldSet()
    {
        //must be call before the code
        $scope = $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

        $key   = 'methods';
        $value = $this->getFieldsetDataValue($key);

        if ($scope != ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            if (!isset($value) || (is_string($value) && strlen($value) == 0)) {
                //get the default store value
                $value = $this->_config->getValue('carriers/myfatoorah_shipping/' . $key);
            }
        }

        if (is_string($value) && strlen($value) > 0) {
            $value = explode(',', $value);
        }

        return $value;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
