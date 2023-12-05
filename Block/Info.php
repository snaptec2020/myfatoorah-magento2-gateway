<?php

namespace MyFatoorah\Gateway\Block;

use Magento\Payment\Block\ConfigurableInfo;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Gateway\ConfigInterface;
use MyFatoorah\Gateway\Model\ResourceModel\MyfatoorahInvoice\CollectionFactory;

/**
 * Displays the MyFatoorah order information in the admin panel
 */
class Info extends ConfigurableInfo
{
    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'info/default.phtml';

    /**
     * MyFatoorah invoice information object
     *
     * @var CollectionFactory
     */
    protected $mfInvoiceFactory;

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * @param CollectionFactory $mfInvoiceFactory
     * @param Context           $context
     * @param ConfigInterface   $config
     * @param array             $data
     */
    public function __construct(
        CollectionFactory $mfInvoiceFactory,
        Context $context,
        ConfigInterface $config,
        array $data = []
    ) {
        parent::__construct($context, $config, $data);
        $this->mfInvoiceFactory = $mfInvoiceFactory;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Used for emails
     *
     * @return array
     */
    public function getSpecificInformation()
    {

        $item = $this->getInvoiceData();
        if (!$item) {
            return [];
        }

        $data = [
            'Invoice ID'   => $item['invoice_id'],
            'Invoice Ref.' => $item['invoice_reference'],
            'Gateway'      => $item['gateway_name'],
            'Invoice URL'  => $item['invoice_url'],
        ];

        if (isset($item['reference_id'])) {
            $data['Trans. Ref. ID'] = $item['reference_id'];
        }
        if (isset($item['track_id'])) {
            $data['Track ID'] = $item['track_id'];
        }
        if (isset($item['authorization_id'])) {
            $data['Authorization ID'] = $item['authorization_id'];
        }
        if (isset($item['gateway_transaction_id'])) {
            $data['Transaction ID'] = $item['gateway_transaction_id'];
        }
        if (isset($item['payment_id'])) {
            $data['Payment ID'] = $item['payment_id'];
            $data['Invoice URL'] = $this->updateInvoiceUrl($item['invoice_url'], $item['payment_id']);
        }
        return $data;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Used for Admin and user
     *
     * @return array
     */
    public function getMFInformation()
    {

        $payment = [];

        $item = $this->getInvoiceData();
        if (!$item) {
            return $payment;
        }

        $payment['invoice']['id']  = $item['invoice_id'];
        $payment['invoice']['url'] = $item['invoice_url'];

        if (isset($item['invoice_reference'])) {
            $payment['info']['Invoice Ref.'] = $item['invoice_reference'];
        }

        $payment['info']['Gateway'] = $item['gateway_name'];

        if (isset($item['reference_id'])) {
            $payment['info']['Trans. Ref. ID'] = $item['reference_id'];
        }
        if (isset($item['track_id'])) {
            $payment['info']['Track ID'] = $item['track_id'];
        }
        if (isset($item['authorization_id'])) {
            $payment['info']['Auth. ID'] = $item['authorization_id'];
        }
        if (isset($item['gateway_transaction_id'])) {
            $payment['info']['Trans. ID'] = $item['gateway_transaction_id'];
        }
        if (isset($item['payment_id'])) {
            $payment['info']['Payment ID'] = $item['payment_id'];
            $payment['invoice']['url'] = $this->updateInvoiceUrl($item['invoice_url'], $item['payment_id']);
        }

        return $payment;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get MyFatoorah invoice data from DB
     *
     * @return array
     */
    private function getInvoiceData()
    {

        $mfOrder = $this->getInfo()->getOrder();
        $orderId = $mfOrder->getRealOrderId();

        if (!$orderId) {
            return [];
        }

        $collection = $this->mfInvoiceFactory->create()->addFieldToFilter('order_id', $orderId);
        $items      = $collection->getData();

        return $items[0] ?? [];
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Change the link to be the correct link of the payment status
     *
     * @param string $url
     * @param string $paymentId
     *
     * @return string
     */
    private function updateInvoiceUrl($url, $paymentId)
    {
        //to overcome session urls
        $pattern = '/MpgsAuthentication.*|ApplePayComplete.*|GooglePayComplete.*/i';
        return preg_replace($pattern, "Result?paymentId=$paymentId", $url);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
