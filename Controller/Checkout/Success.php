<?php

namespace MyFatoorah\Gateway\Controller\Checkout;

use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\CatalogInventory\Api\StockManagementInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\DB\Transaction;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Magento\Quote\Model\QuoteFactory;
use MyFatoorah\Gateway\Gateway\Config\Config;
use MyFatoorah\Gateway\Model\ResourceModel\MyfatoorahInvoice\CollectionFactory;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;
use MyFatoorah\Library\MyFatoorah;
use Exception as MFException;

class Success extends MyfatoorahAction
{

    /**
     * @var boolean
     */
    private $isAddHistory = false;

    /**
     *
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var CollectionFactory
     */
    public $mfInvoiceFactory;

    /**
     *
     * @var OrderSender
     */
    private $emailSender;

    /**
     *
     * @var Status
     */
    private $orderStatus;

    /**
     *
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     *
     * @var Transaction
     */
    private $dbTransaction;

    /**
     *
     * @var StockManagementInterface
     */
    private $stockManagement;

    /**
     *
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     *
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     *
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var \MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus
     */
    protected $mfObj;

    /**
     * @var string
     */
    protected $orderId;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     *
     * @param Context                  $context
     * @param Config                   $mfConfig
     * @param Session                  $checkoutSession
     * @param OrderFactory             $orderFactory
     * @param CollectionFactory        $mfInvoiceFactory
     * @param OrderSender              $emailSender
     * @param Status                   $orderStatus
     * @param InvoiceService           $invoiceService
     * @param Transaction              $dbTransaction
     * @param StockManagementInterface $stockManagementInterface
     * @param ResourceConnection       $resourceConnection
     * @param DeploymentConfig         $deploymentConfig
     * @param QuoteFactory             $quoteFactory
     */
    public function __construct(
        Context $context,
        Config $mfConfig,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        CollectionFactory $mfInvoiceFactory,
        OrderSender $emailSender,
        Status $orderStatus,
        InvoiceService $invoiceService,
        Transaction $dbTransaction,
        StockManagementInterface $stockManagementInterface,
        ResourceConnection $resourceConnection,
        DeploymentConfig $deploymentConfig,
        QuoteFactory $quoteFactory
    ) {
        parent::__construct($context, $mfConfig, $checkoutSession);

        $this->orderFactory       = $orderFactory;
        $this->mfInvoiceFactory   = $mfInvoiceFactory;
        $this->emailSender        = $emailSender;
        $this->orderStatus        = $orderStatus;
        $this->invoiceService     = $invoiceService;
        $this->dbTransaction      = $dbTransaction;
        $this->stockManagement    = $stockManagementInterface;
        $this->resourceConnection = $resourceConnection;
        $this->deploymentConfig   = $deploymentConfig;
        $this->quoteFactory       = $quoteFactory;

        MyFatoorah::$loggerObj = MYFATOORAH_LOG_FILE;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get Order By IncrementId
     *
     * @param  mixed $orderId
     * @return mixed
     */
    public function getOrderById($orderId)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        return $order->getId() ? $order : null;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Process the order after payment
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {

        $error = $this->validateMFData();
        if (!$error) {
            //redirect to success page
            $this->messageManager->addSuccessMessage(__('Your payment is complete'));
            $param = [
                '_query'  => "orderId=$this->orderId",
                '_secure' => $this->getRequest()->isSecure(),
            ];
            return $this->_redirect('checkout/onepage/success', $param);
        }

        return $this->redirectToCartPage($error, $this->orderId);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Validate and get MyFatoorah Data
     *
     * @return string
     */
    private function validateMFData()
    {
        $paymentId = $this->getRequest()->getParam('paymentId');
        if (!$paymentId) {
            return 'Order not found.';
        }

        try {
            $mfObj = $this->mfConfig->getMyfatoorahObject(MyFatoorahPaymentStatus::class);
            return $this->checkStatus($paymentId, 'paymentId', $mfObj);
        } catch (MFException $ex) {
            $error = $ex->getMessage();
            MyFatoorah::log('In Exception Block: ' . $error);
            return $error;
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get MyFatoorah Payment Status
     *
     * @param  mixed   $keyId
     * @param  string  $KeyType
     * @param  object  $mfObj
     * @param  string  $source
     * @param  boolean $escape
     * @param  mixed   $orderId
     * @return mixed
     * @throws MFException
     */
    public function checkStatus($keyId, $KeyType, $mfObj, $source = '', $escape = false, $orderId = null)
    {

        //check for the invoice or payment id
        $data = $mfObj->getPaymentStatus($keyId, $KeyType, $orderId);

        //get the order
        /**
         * @var Order $order
         */
        $order = $this->getOrderById($data->CustomerReference);
        if (!$order) {
            throw new MFException('Order not found.');
        }

        $this->orderId = $order->getRealOrderId();

        //order is not pending or canceled
        $status = $order->getState();

        if (!$escape && $status !== Order::STATE_PENDING_PAYMENT && $status !== Order::STATE_CANCELED) {
            return false;
        }

        $message = "MyFatoorah$source: $data->InvoiceStatus Payment. ";
        if (isset($data->focusTransaction)) {
            $paymentId      = $data->focusTransaction->PaymentId;
            $paymentGateway = $data->focusTransaction->PaymentGateway;

            $message .= 'Payment Id #' . $paymentId . '. Gateway used is ' . $paymentGateway . '. ';
        }

        if ($data->InvoiceStatus == 'Paid') {
            $this->savePaymentData($data);
            $this->processPaidPayment($order, $data->InvoiceId, $message);
        } elseif ($data->InvoiceStatus == 'Failed') {
            $this->savePaymentData($data);
            $this->processCancelPayment($order, $message . 'Gateway error is ' . $data->InvoiceError);
        } elseif ($data->InvoiceStatus == 'Expired') {
            $this->processCancelPayment($order, $message . $data->InvoiceError);
        }

        return $data->InvoiceError;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Save Payment Data in DB
     *
     * @param  object $data
     * @return void
     */
    private function savePaymentData($data)
    {

        $orderId = $data->CustomerReference;
        //save the invoice id in myfatoorah_invoice table
        //see this sol: https://stackoverflow.com/questions/12570752/how-do-i-select-a-single-row-in-magento-in-a-custom-database-for-display-in-a-bl
        //$collection = Mage::getModel('brands/brands')->getCollection();

        $collection = $this->mfInvoiceFactory->create()->addFieldToFilter('invoice_id', $data->InvoiceId);
        $item       = $collection->getFirstItem();
        $itemData   = $item->getData();

        if (empty($itemData['invoice_id'])) {
            MyFatoorah::log(
                'Order #' . $orderId . ' ----- Get Payment Status - '
                    . 'can not save transaction information into database due to pending payment or worng order id'
            );
            return;
        }

        //save payment data
        $transaction        = $data->focusTransaction;
        $this->isAddHistory = false;

        if ($data->InvoiceStatus == 'Paid' || (empty($itemData['payment_id']) || $itemData['payment_id'] != $transaction->PaymentId)) {
            $item->setData('gateway_name', $transaction->PaymentGateway);
            $item->setData('gateway_transaction_id', $transaction->TransactionId);
            $item->setData('payment_id', $transaction->PaymentId);
            $item->setData('authorization_id', $transaction->AuthorizationId);
            $item->setData('reference_id', $transaction->ReferenceId);
            $item->setData('track_id', $transaction->TrackId);

            $item->setData('invoice_reference', $data->InvoiceReference);

            $this->isAddHistory = true;
        }

        $item->save();
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Set Order Status for Success Payment
     *
     * @param Order  $order
     * @param string $invoiceId
     * @param string $message
     */
    private function processPaidPayment($order, $invoiceId, $message)
    {
        if ($order->isCanceled()) {
            $this->unCancelorder($order, "MyFatoorah: remove the cancel status");
        }

        $orderStatus = $this->mfConfig->getMyFatoorahApprovedOrderStatus();
        if (!$this->isStatusExists($orderStatus)) {
            $orderStatus = $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING);
        }
        //important in case the unCancelorder did not work
        if ($order->isCanceled()) {
            $order->setState(Order::STATE_HOLDED)
                    ->setStatus(Order::STATE_HOLDED)
                    ->addStatusHistoryComment($message);
            $order->save();
            // $order->hold()->save();
        } else {
            //set order status
            $order->setState(Order::STATE_PROCESSING)
                    ->setStatus($orderStatus)
                    ->addStatusHistoryComment($message)
                    ->setIsCustomerNotified($this->mfConfig->isEmailCustomer());
            $order->save();

            //set payment
            $payment = $order->getPayment();
            $payment->setTransactionId($invoiceId);
            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE, null, true);
            $order->save();

            if ($this->mfConfig->isAutomaticInvoice()) {
                $this->createMagentoInvoice($order, $invoiceId);
            }

            //send email
            $this->emailSender->send($order);
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Set Order Status for Failure Payment
     *
     * @param Order  $order
     * @param string $message
     */
    private function processCancelPayment($order, $message)
    {
        if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
            $order->registerCancellation($message)->save();
        } elseif ($this->isAddHistory) {
            $order->addStatusHistoryComment($message)->save();
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Check Status If Exists
     *
     * @param  string $orderStatus
     * @return boolean
     */
    private function isStatusExists($orderStatus)
    {
        $statuses = $this->orderStatus->getResourceCollection()->getData();
        foreach ($statuses as $status) {
            if ($orderStatus === $status['status']) {
                return true;
            }
        }

        return false;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Create Magento Invoice
     *
     * @param Order  $order
     * @param string $invoiceId
     */
    private function createMagentoInvoice($order, $invoiceId)
    {
        $orderId = $order->getRealOrderId();

        $msgLog = "Order #$orderId ----- Get Payment Status";

        MyFatoorah::log("$msgLog - In Create Invoice");
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            if ($invoice->getTotalQty()) {
                MyFatoorah::log("$msgLog - Can create an invoice.");
            } else {
                MyFatoorah::log("$msgLog - Can't create an invoice without products.");
            }

            /*
             * Look Magento/Sales/Model/Order/Invoice.register() for CAPTURE_OFFLINE explanation.
             * Basically, if
             * !config/can_capture and
             * config/is_gateway and
             * CAPTURE_OFFLINE and
             * Payment.IsTransactionPending => pay (Invoice.STATE = STATE_PAID...)
             */
            $invoice->setTransactionId($invoiceId);
            $invoice->setRequestedCaptureCase(Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();

            $transaction = $this->dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
            $transaction->save();
        } else {
            $err = 'Can\'t create the invoice.';
            $order->addStatusHistoryComment("MyFatoorah: $err");
            MyFatoorah::log("$msgLog - $err");
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Change order status from cancel to pending
     *
     * Check https://magento.stackexchange.com/questions/297133/magento-2-2-10-change-cancelled-order-to-pending
     *
     * @param Order  $order
     * @param string $comment
     */
    public function unCancelorder($order, $comment)
    {

        $productStockQty = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $productStockQty[$item->getProductId()] = $item->getQtyCanceled();
            foreach ($item->getChildrenItems() as $child) {
                $productStockQty[$child->getProductId()] = $item->getQtyCanceled();
                $child->setQtyCanceled(0);
                $child->setTaxCanceled(0);
                $child->setDiscountTaxCompensationCanceled(0);
            }
            $item->setQtyCanceled(0);
            $item->setTaxCanceled(0);
            $item->setDiscountTaxCompensationCanceled(0);
        }

        $order->setSubtotalCanceled(0);
        $order->setBaseSubtotalCanceled(0);
        $order->setTaxCanceled(0);
        $order->setBaseTaxCanceled(0);
        $order->setShippingCanceled(0);
        $order->setBaseShippingCanceled(0);
        $order->setDiscountCanceled(0);
        $order->setBaseDiscountCanceled(0);
        $order->setTotalCanceled(0);
        $order->setBaseTotalCanceled(0);

        /* Reverting inventory */
        try {
            $this->stockManagement->registerProductsSale(
                $productStockQty,
                $order->getStore()->getWebsiteId()
            );

            /**
             * @var \Magento\Framework\DB\Adapter\AdapterInterface $connection
             */
            $connection = $this->resourceConnection->getConnection();

            //get table name
            $prefix    = $this->deploymentConfig->get('db/table_prefix');
            $tableName = $prefix . 'inventory_reservation';

            foreach ($order->getAllItems() as $item) {
                $sku     = $item->getSku();
                $orderId = $order->getId();

                $metadata = "{\"event_type\":\"order_canceled\",\"object_type\":\"order\",\"object_id\":\"$orderId\"%";

                $selectQuery = "SELECT * FROM $tableName WHERE sku='$sku' AND metadata LIKE '$metadata'";
                $result      = $connection->fetchAll($selectQuery);

                if ($result && count($result) > 0) {
                    $deleteQuery = "DELETE FROM $tableName WHERE sku='$sku' AND metadata LIKE '$metadata'";
                    $connection->query($deleteQuery);
                }
            }
            $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT);

            if (!empty($comment)) {
                $order->addStatusHistoryComment($comment);
            }
            $order->setInventoryProcessed(true);

            if ($order->save()) {
                $quote = $this->quoteFactory->create()->load($order->getQuoteId());
                $quote->setIsActive(false)->save();
            }
        } catch (MFException $e) {
            $order->addStatusHistoryComment($e->getMessage());
            $order->save();
        }
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
