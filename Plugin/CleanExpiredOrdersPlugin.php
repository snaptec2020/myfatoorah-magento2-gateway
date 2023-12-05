<?php

namespace MyFatoorah\Gateway\Plugin;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use MyFatoorah\Gateway\Controller\Checkout\Success;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;
use Exception as MFException;

/**
 * The class checks MyFatoorah order statuses before cleaning expired quotes by cron
 */
class CleanExpiredOrdersPlugin
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CollectionFactory
     */
    private $orderCollection;

    /**
     * @var Success
     */
    private $successObj;

    /**
     *
     * @var MyFatoorahPayment
     */
    private $mfObj;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface  $scopeConfig
     * @param Success               $successObj
     * @param CollectionFactory     $orderCollection
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Success $successObj,
        CollectionFactory $orderCollection
    ) {

        //used to list stores
        $this->storeManager = $storeManager;
        $this->scopeConfig  = $scopeConfig;

        //used in check MyFatoorah Status
        $this->successObj = $successObj;

        //used in list orders
        $this->orderCollection = $orderCollection;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Clean expired quotes (cron process)
     */
    public function beforeExecute()
    {

        $stores = $this->storeManager->getStores(true);

        /**
         * @var \Magento\Store\Model\Store $store
         */
        $scope = ScopeInterface::SCOPE_STORE;
        foreach ($stores as $storeId => $store) {
            try {
                //get store needed config value
                $path     = 'payment/myfatoorah_payment/';
                $lifetime = $this->scopeConfig->getValue('sales/orders/delete_pending_after', $scope, $storeId);

                $config = [
                    'apiKey'      => $this->scopeConfig->getValue($path . 'api_key', $scope, $storeId),
                    'isTest'      => (bool) $this->scopeConfig->getValue($path . 'is_testing', $scope, $storeId),
                    'countryCode' => $this->scopeConfig->getValue($path . 'countryMode', $scope, $storeId),
                    'loggerObj'   => MYFATOORAH_LOG_FILE
                ];

                $this->mfObj = new MyFatoorahPaymentStatus($config);

                $this->checkPendingOrderByStore($storeId, $lifetime);
            } catch (MFException $ex) {
                // Store doesn't really exist or data is not config, so move on.
                continue;
            }
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get pending orders within the life time
     *
     * @param mixed $storeId
     * @param mixed $lifetime
     */
    public function checkPendingOrderByStore($storeId, $lifetime)
    {
        /**
         * @var $orders \Magento\Sales\Model\ResourceModel\Order\Collection
         */
        $orders = $this->orderCollection->create();
        $orders->addFieldToFilter('store_id', $storeId);
        $orders->addFieldToFilter('status', Order::STATE_PENDING_PAYMENT);
        $orders->getSelect()->where(
            new \Zend_Db_Expr('TIME_TO_SEC(TIMEDIFF(CURRENT_TIMESTAMP, `updated_at`)) >= ' . $lifetime * 60)
        );

        //check MyFatoorah status
        $this->checkMFStatus($orders);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Check MyFatoorah Payment Status
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Collection $orders
     */
    public function checkMFStatus($orders)
    {
        /**
         * @var \Magento\Sales\Model\Order $order
         */
        foreach ($orders as $order) {
            $orderId = $order->getRealOrderId();

            $collection = $this->successObj->mfInvoiceFactory->create()->addFieldToFilter('order_id', $orderId);
            $item       = $collection->getFirstItem()->getData();
            if (empty($item['invoice_id'])) {
                continue;
            }

            $invoiceId = $item['invoice_id'];

            $this->mfObj->log("Order #$orderId ----- Cron Job - Check Order Status with Invoice Id #$invoiceId");
            try {
                $err = $this->successObj->checkStatus($invoiceId, 'InvoiceId', $this->mfObj, '-Cron', false, $orderId);
                $this->mfObj->log('In Cron Block: success request.' . ($err ? "but $err" : ''));
            } catch (MFException $ex) {
                $this->mfObj->log('In Cron Exception Block: ' . $ex->getMessage());
            }
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
