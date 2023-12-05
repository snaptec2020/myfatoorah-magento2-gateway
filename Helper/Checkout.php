<?php

namespace MyFatoorah\Gateway\Helper;

use Magento\Sales\Model\Order;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use MyFatoorah\Library\MyFatoorah;
use Exception as MFException;

class Checkout
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param ScopeConfigInterface   $scopeConfig
     * @param StoreManagerInterface  $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param TaxHelper              $taxHelper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        TaxHelper $taxHelper
    ) {
        $this->scopeConfig   = $scopeConfig;
        $this->storeManager  = $storeManager;
        $this->priceCurrency = $priceCurrency;
        $this->taxHelper     = $taxHelper;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * GEt MyFatoorah Shipping Data
     *
     * @param  int|null $isMFShipping
     * @param  object   $item
     * @param  object   $product
     * @param  mixed    $productId
     * @param  string   $name
     * @param  mixed    $storeId
     * @param  mixed    $weightRate
     * @return array
     * @throws MFException
     */
    private function getShippingData($isMFShipping, $item, $product, $productId, $name, $storeId, $weightRate)
    {
        $isShippingProduct = ($isMFShipping && $item->getProductType() != 'downloadable' && !$item->getIsVirtual());

        $data = ['weight' => 0, 'width' => 0, 'height' => 0, 'depth' => 0];

        if ($isShippingProduct) {
            //get weight
            $data['weight'] = $item->getWeight() * $weightRate;

            //get dimensions
            $ressource      = $product->getResource();
            $data['width']  = $ressource->getAttributeRawValue($productId, 'width', $storeId);
            $data['height'] = $ressource->getAttributeRawValue($productId, 'height', $storeId);
            $data['depth']  = $ressource->getAttributeRawValue($productId, 'depth', $storeId);

            if (empty($data['weight']) || empty($data['width']) || empty($data['height']) || empty($data['depth'])) {
                $err = __('Kindly, contact the site admin to set weight and dimensions for %1', $name);
                throw new MFException($err);
            }
        }

        return $data;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get Order Items
     *
     * @param  array   $items          order item array
     * @param  float   $mfCurrencyRate currency rate
     * @param  mixed   $isMFShipping   is Shipping integer flag
     * @param  boolean $isPayment      is Payment flag
     * @return array
     */
    public function getOrderItems($items, $mfCurrencyRate, $isMFShipping, $isPayment)
    {
        $scope = ScopeInterface::SCOPE_STORE;

        $store   = $this->storeManager->getStore();
        $storeId = $store->getId();

        //Magento\Tax\Model\Calculation::CALC_UNIT_BASE,
        //Magento\Tax\Model\Calculation::CALC_ROW_BASE,
        //Magento\Tax\Model\Calculation::CALC_TOTAL_BASE,
        $taxBasedOn = $this->scopeConfig->getValue('tax/calculation/algorithm', $scope, $storeId);

        $weightUnit = $this->scopeConfig->getValue('general/locale/weight_unit', $scope, $storeId);
        $weightRate = MyFatoorah::getWeightRate($weightUnit);

        $invoiceItemsArr = [];
        $amount          = 0;
        $objectManager   = ObjectManager::getInstance();
        foreach ($items as $item) {
            $isPhysical = ($isPayment && !$item->getIsVirtual() && $item->getProductType() != 'downloadable');
            if ($item->getProductType() != 'simple' && (!$isPayment || $isPhysical)
            ) {
                continue;
            }

            $productId = $item->getProductId();

            /**
             * @var \Magento\Catalog\Model\Product $product
             */
            //must create a new Product from the object manager for each product
            $product = $objectManager->create(\Magento\Catalog\Model\Product::class)->load($productId);

            $name = $product->getName();
            $qty  = $isPayment ? (int) $item->getQtyOrdered() : (int) $item->getQty();

            $priceExTax = $this->taxHelper->getTaxPrice($product, $product->getFinalPrice(), false);
            if ($taxBasedOn == \Magento\Tax\Model\Calculation::CALC_UNIT_BASE) {
                $priceExTax = $this->taxHelper->getTaxPrice($product, $product->getFinalPrice(), false);
            } else {
                $rowPriceExTax = $this->taxHelper->getTaxPrice($product, ($product->getFinalPrice() * $qty), false);
                $priceExTax    = $rowPriceExTax / $qty;
            }

            $itemPrice    = round($priceExTax * $mfCurrencyRate, 3);
            $shippingData = $this->getShippingData(
                $isMFShipping,
                $item,
                $product,
                $productId,
                $name,
                $storeId,
                $weightRate
            );

            $invoiceItemsArr[] = [
                'ProductName' => $name,
                'Description' => $name,
                'ItemName'    => $name,
                'Quantity'    => $qty,
                'UnitPrice'   => "$itemPrice",
                'weight'      => $shippingData['weight'],
                'Width'       => $shippingData['width'],
                'Height'      => $shippingData['height'],
                'Depth'       => $shippingData['depth']
            ];
            $amount            += $itemPrice * $qty;
        }

        return [
            'invoiceItemsArr' => $invoiceItemsArr,
            'amount'          => $amount
        ];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get Invoice Items
     *
     * @param  Order    $order
     * @param  float    $currencyRate
     * @param  int|null $isMFShipping
     * @param  mixed    $amount
     * @param  boolean  $isPayment
     * @return string
     */
    public function getInvoiceItems($order, $currencyRate, $isMFShipping, &$amount, $isPayment = false)
    {
        $scope   = ScopeInterface::SCOPE_STORE;
        $storeId = $order->getStoreId();

        $priceIncTax = $this->scopeConfig->getValue('tax/calculation/price_includes_tax', $scope, $storeId);

        /**
         * @var \Magento\Sales\Api\Data\OrderItemInterface[]  $items
         */
        $items            = $order->getAllItems();
        $orderItemsReturn = $this->getOrderItems($items, $currencyRate, $isMFShipping, $isPayment); //restore cart
        $invoiceItemsArr  = $orderItemsReturn['invoiceItemsArr'];
        $amount           = $orderItemsReturn['amount'];

        //------------------------------
        //Discounds and Coupon
        $discount1 = $order->getBaseDiscountAmount();
        if (!$priceIncTax) {
            $discount1 += $order->getBaseDiscountTaxCompensationAmount();
        }

        $discount = round($discount1 * $currencyRate, 3);
        if ($discount) {
            $invoiceItemsArr[] = [
                'ItemName'  => 'Discount Amount', 'Quantity'  => '1', 'UnitPrice' => "$discount",
                'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
            $amount            += $discount;
        }

        //------------------------------
        //Shippings
        $mfShipping = 0;

        $shipping1 = $order->getBaseShippingAmount(); // + $order->getBaseShippingTaxAmount();
        $shipping  = round($shipping1 * $currencyRate, 3);
        if ($shipping) {
            if ($isMFShipping) {
                $mfShipping = $shipping;
            } else {
                $invoiceItemsArr[] = [
                    'ItemName'  => 'Shipping Amount', 'Quantity'  => '1', 'UnitPrice' => "$shipping",
                    'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
                $amount            += $shipping;
            }
        }

        //------------------------------
        //Other fees
        //Mageworx
        $fees1 = $order->getBaseMageworxFeeAmount();
        $fees  = round($fees1 * $currencyRate, 3);
        if ($fees) {
            $invoiceItemsArr[] = [
                'ItemName'  => 'Additional Fees', 'Quantity'  => 1, 'UnitPrice' => "$fees",
                'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
            $amount            += $fees;
        }

        $productFees1 = $order->getBaseMageworxProductFeeAmount();
        $productFees  = round($productFees1 * $currencyRate, 3);
        if ($productFees) {
            $invoiceItemsArr[] = [
                'ItemName'  => 'Additional Product Fees', 'Quantity'  => 1, 'UnitPrice' => "$productFees",
                'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
            $amount            += $productFees;
        }

        //        $amount = round($amount, 3);

        /*
          (print_r('FeeAmount' . $order->getBaseMageworxFeeAmount(),1));
          (print_r('FeeInvoiced' . $order->getBaseMageworxFeeInvoiced(),1));
          (print_r('FeeCancelled' . $order->getBaseMageworxFeeCancelled(),1));
          (print_r('FeeTaxAmount' . $order->getBaseMageworxFeeTaxAmount(),1));
          (print_r('FeeDetails' . $order->getMageworxFeeDetails(),1));
          (print_r('FeeRefunded' . $order->getMageworxFeeRefunded(),1));

          (print_r('ProductFeeAmount' . $order->getBaseMageworxProductFeeAmount(),1));
          (print_r('ProductFeeInvoiced' . $order->getBaseMageworxProductFeeInvoiced(),1));
          (print_r('ProductFeeCancelled' . $order->getBaseMageworxProductFeeCancelled(),1));
          (print_r('ProductFeeTaxAmount' . $order->getBaseMageworxProductFeeTaxAmount(),1));
          (print_r('ProductFeeDetails' . $order->getMageworxProductFeeDetails(),1));
          (print_r('ProductFeeRefunded' . $order->getMageworxProductFeeRefunded(),1));
         */

        //------------------------------
        //Tax
        $tax1 = $order->getBaseTotalDue() - $amount - $mfShipping;
        $tax  = round($tax1 * $currencyRate, 3);
        if ($tax) {
            $invoiceItemsArr[] = [
                'ItemName'  => 'Tax Amount', 'Quantity'  => '1', 'UnitPrice' => "$tax",
                'Weight'    => '0', 'Width'     => '0', 'Height'    => '0', 'Depth'     => '0'];
            $amount            += $tax;
        }

        return $invoiceItemsArr;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
