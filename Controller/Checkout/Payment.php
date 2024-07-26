<?php

namespace MyFatoorah\Gateway\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Cart;
use MyFatoorah\Gateway\Gateway\Config\Config;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentEmbedded;
use Exception as MFException;

class Payment extends Action
{

    /**
     * @var Config
     */
    private $mfConfig;

    /**
     * @var Cart
     */
    private $cart;

    /**
     *
     * @var JsonFactory
     */
    private $resultJsonFactory;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param Context     $context
     * @param JsonFactory $resultJsonFactory
     * @param Config      $mfConfig
     * @param Cart        $cart
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Config $mfConfig,
        Cart $cart
    ) {
        $this->mfConfig          = $mfConfig;
        $this->cart              = $cart;
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Ajax request to recalculate the displayed amount of MyFatoorah gateways
     *
     * @return mixed
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        if (!$this->getRequest()->isAjax()) {
            return;
        }

        try {
            $isApRegistered = $this->mfConfig->isApplePayRegistered();

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

            $mfObj = $this->mfConfig->getMyfatoorahObject(MyFatoorahPaymentEmbedded::class);

            $paymentMethods = $mfObj->getCheckoutGateways($total, $currency, $isApRegistered);
            $error          = null;
        } catch (MFException $exc) {
            $paymentMethods = [];
            $error          = $exc->getMessage();
        }

        $data = [
            'paymentMethods' => json_encode($paymentMethods),
            'error'          => $error
        ];
        return $result->setData($data);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
