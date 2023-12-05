<?php

namespace MyFatoorah\Gateway\Model;

use MyFatoorah\Gateway\Controller\Checkout\Success;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\Order;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;

class WebHook
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Success
     */
    private $successObj;

    /**
     * @var Order
     */
    private $orderModel;

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Success              $successObj
     * @param Order                $orderModel
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Success $successObj,
        Order $orderModel
    ) {

        $this->scopeConfig = $scopeConfig;

        $this->successObj = $successObj;
        $this->orderModel = $orderModel;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function execute($EventType, $Data)
    {

        //to allow the callback code run 1st.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        sleep(30);

        if ($EventType != 1) {
            return 'event not allowed';
        }

        $error = $this->transactionsStatusChanged($Data);
        if ($error) {
            error_log(PHP_EOL . date('d.m.Y h:i:s') . ' - ' . $error, 3, MYFATOORAH_LOG_FILE);
            return $error;
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Change Order Status due to the Transactions Status Changed
     *
     * @param  array $data
     * @return string
     */
    private function transactionsStatusChanged($data)
    {

        if (empty($data)) {
            return 'No Data';
        }
        $orderId = $data['CustomerReference'];
        try {
            //get the order to get its store
            $order = $this->orderModel->loadByIncrementId($orderId);
            if (!$order->getId()) {
                return 'MyFatoorah returned an order that could not be retrieved';
            }

            //get the order store config
            $scope   = ScopeInterface::SCOPE_STORE;
            $storeId = $order->getStoreId();

            $path = 'payment/myfatoorah_payment/';

            $config = [
                'apiKey'      => $this->scopeConfig->getValue($path . 'api_key', $scope, $storeId),
                'isTest'      => (bool) $this->scopeConfig->getValue($path . 'is_testing', $scope, $storeId),
                'countryCode' => $this->scopeConfig->getValue($path . 'countryMode', $scope, $storeId),
                'loggerObj'   => MYFATOORAH_LOG_FILE
            ];

            $webhookSecretKey = $this->scopeConfig->getValue($path . 'webhookSecretKey', $scope, $storeId);
            if (!$webhookSecretKey) {
                return 'missing info';
            }

            //get lib object
            $mfObj = new MyFatoorahPaymentStatus($config);

            //get MyFatoorah Signature from request headers
            $apache  = apache_request_headers();
            $headers = array_change_key_case($apache);

            if (empty($headers['myfatoorah-signature'])) {
                return 'No signature provided';
            }
            $mfSignature = $headers['myfatoorah-signature'];

            //validate signature
            if (!$mfObj->isSignatureValid($data, $webhookSecretKey, $mfSignature)) {
                return 'Signature is not valid';
            }

            //update order status
            $err = $this->successObj->checkStatus($data['InvoiceId'], 'InvoiceId', $mfObj, '-WebHook', false, $orderId);
            return 'success request.' . ($err ? "but $err" : '');
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
}
