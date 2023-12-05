<?php

namespace MyFatoorah\Gateway\Console\Command;

//use Magento\Sales\Model\Order;
use Magento\Framework\App\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use MyFatoorah\Library\API\Payment\MyFatoorahPaymentStatus;
use MyFatoorah\Gateway\Controller\Checkout\Success;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\DeploymentConfig;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

class RetrieveOrderData extends Command
{
    /**
     *
     * @var string
     */
    public const NAME_ARGUMENT = 'OrderIds';

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setName('myfatoorah:update');
        $this->setDescription(
            'Force update the status of the order even '
                . 'if it is confirmed, completed, shipped, or processing '
                . 'with the last invoice status in MyFatoorah vendor account.'
        );
        $this->setDefinition(
            [
                    new InputArgument(self::NAME_ARGUMENT, InputArgument::IS_ARRAY, 'OrderIds')
                ]
        );
        parent::configure();
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectManager = ObjectManager::getInstance();

        //To avoid the Area code is not set of the send email command
        $state = $objectManager->get(State::class);

        //\Magento\Framework\App\Area::AREA_ADMINHTML, depending on your needs
        $state->setAreaCode(\Magento\Framework\App\Area::AREA_FRONTEND);

        //Create Success Object
        $successObj = $objectManager->create(Success::class);

        $ScopeConfigInterface = $objectManager->create(ScopeConfigInterface::class);

        $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

        //get db connection
        $connection = $objectManager->get(ResourceConnection::class)
                ->getConnection('\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION');

        //get table name
        $deploymentConfig = $objectManager->get(DeploymentConfig::class);
        $prefix           = ($deploymentConfig->get('db/table_prefix'));
        $tableName        = $prefix . 'myfatoorah_invoice';

        //Get Pending orderss
        $orderCollectionFactory = $objectManager->create(CollectionFactory::class);

        $orders = $orderCollectionFactory->create()
                ->addFieldToFilter('increment_id', ['in' => $input->getArgument(self::NAME_ARGUMENT)]);

        if (empty($orders->getAllIds())) {
            $output->writeln('No Order Recoreds found');
        }

        //Update Status
        /**
         * @var \Magento\Sales\Model\Order $order
         */
        foreach ($orders as $order) {
            $orderId = $order->getRealOrderId();
            $msg     = "Order #$orderId ----- Command -";

            $result1 = $connection->fetchAll("SELECT invoice_id FROM $tableName WHERE order_id=$orderId");

            if (empty($result1[0]['invoice_id'])) {
                $output->writeln("$msg Not a MyFatoorah recored");
                continue;
            }

            $invoiceId = $result1[0]['invoice_id'];
            $output->writeln("$msg Check Order Status with Invoice Id #$invoiceId");

            try {
                $storeId = $order->getStoreId();
                $path    = 'payment/myfatoorah_payment/';

                $config = [
                    'apiKey'      => $ScopeConfigInterface->getValue($path . 'api_key', $scope, $storeId),
                    'isTest'      => (bool) $ScopeConfigInterface->getValue($path . 'is_testing', $scope, $storeId),
                    'countryCode' => $ScopeConfigInterface->getValue($path . 'countryMode', $scope, $storeId),
                    'loggerObj'   => MYFATOORAH_LOG_FILE
                ];

                $mfObj = new MyFatoorahPaymentStatus($config);
                $mfObj->log("$msg Check Order Status with Invoice Id #$invoiceId");

                $mfError = $successObj->checkStatus($invoiceId, 'InvoiceId', $mfObj, '-Cmd', true, $orderId);

                $output->writeln("$msg " . (($mfError) ? "Faild with error: $mfError" : "Success"));
                return 0;
            } catch (\Exception $ex) {
                $err = $ex->getMessage();
                $output->writeln("$msg Excption $err");
            }
        }
        return 1;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
