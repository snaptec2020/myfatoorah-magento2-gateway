<?php

namespace MyFatoorah\Gateway\Plugin;

use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order;

class OrderSenderPlugin
{
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Don't send email if MyFatoorah gateways used and the order is pending
     *
     * @param  OrderSender $subject
     * @param  callable    $proceed
     * @param  Order       $order
     * @param  boolean     $forceSyncMode
     * @return boolean
     */
    public function aroundSend(OrderSender $subject, callable $proceed, Order $order, $forceSyncMode = false)
    {
        $payment = $order->getPayment()->getMethodInstance()->getCode();

        $isMFcode = ($payment === 'myfatoorah_payment');
        if ($isMFcode && $order->getState() === Order::STATE_PENDING_PAYMENT) {
            return false;
        }

        return $proceed($order, $forceSyncMode);
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
