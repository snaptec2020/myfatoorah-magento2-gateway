# MyFatoorah Magento2 Gateway
This is the official MyFatoorah Payment Gateway for Magento2. 
MyFatoorah Magento2 Gateway is based on [myfatoorah/library](https://packagist.org/packages/myfatoorah/library) composer package. 
Both MyFatoorah Magento2 Gateway and PHP library composer packages are developed by [MyFatoorah Technical Team](mailto:tech@myfatoorah.com) to handle myfatoorah API endpoints.

## Main Features
* Create MyFatoorah invoices.
* Check the MyFatoorah payment status for invoice/payment.
* Shipping via DHL and ARAMEX

## Installation
1. Install the module via [myfatoorah/magento2-gateway](https://packagist.org/packages/myfatoorah/magento2-gateway) composer.
```bash
composer require myfatoorah/magento2-gateway
```

2. Run the below Magento commands to enable MyFatoorah Plugin.
```bash
php -f bin/magento module:enable --clear-static-content MyFatoorah_Gateway
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush
```

## Merchant Configurations
In Magento Admin Panel, follow the steps below:

1. Go to Menu Stores → Configuration section
2. Expand Sales Menu → select Payment Methods → MyFatoorah Payment
3. Fill in Gateway configuration and use API key as follows


**Demo configuration**
1. You can use the test API token key mentioned [here](https://myfatoorah.readme.io/docs/test-token).
2. Make sure the test mode is true.
3. You can use one of [the test cards](https://myfatoorah.readme.io/docs/test-cards).

**Live Configuration**
1. You can use the live API token key mentioned [here](https://myfatoorah.readme.io/docs/live-token).
2. Make sure the test mode is false.
3. Make sure to set the country ISO code as mentioned in [this link](https://myfatoorah.readme.io/docs/iso-lookups).
