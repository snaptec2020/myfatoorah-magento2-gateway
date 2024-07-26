<?php

namespace MyFatoorah\Gateway\Controller\Checkout;

use MyFatoorah\Gateway\Gateway\Config\Config;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

abstract class MyfatoorahAction extends Action
{

    /**
     *
     * @var Config
     */
    protected $mfConfig;

    /**
     * @var Session
     */
    protected $checkoutSession;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param Context $context
     * @param Config  $mfConfig
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        Config $mfConfig,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->mfConfig = $mfConfig;

        $this->checkoutSession = $checkoutSession;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Redirect to the cart/failure page with error
     *
     * @param string $error
     * @param string $orderId
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function redirectToCartPage($error, $orderId = '')
    {
        //restore cart
        $this->checkoutSession->restoreQuote();

        //trans the error
        $tranError = __($error);
        $this->messageManager->addErrorMessage($tranError);

        //redirect to cancel page with error
        $param = [
            '_query'  => (empty($orderId) ? '' : "orderId=$orderId&") . "error=$tranError",
            '_secure' => $this->getRequest()->isSecure(),
        ];

        $failurePage = $this->mfConfig->getFailurePage();
        if ($failurePage == 'checkout_onepage_failure') {
            return $this->_redirect('checkout/onepage/failure', $param);
        }

        //restore cart
        //$this->checkoutSession->restoreQuote();
        return $this->_redirect('checkout/cart', $param);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Return the desired MyFAtoorah checkout URL
     *
     * @param string $path
     *
     * @return string
     */
    protected function getMfCheckoutUrl($path)
    {
        return $this->_url->getBaseUrl() . $this->mfConfig->getCode() . '/checkout/' . $path;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
