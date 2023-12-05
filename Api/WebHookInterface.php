<?php

namespace MyFatoorah\Gateway\Api;

/**
 * Webhook feature is used to trigger events each time the order status changes at the MyFatoorah side.
 */
interface WebHookInterface
{
    /**
     * Webhook feature will recover the lost orders due to connection loss or delayed callbacks.
     *
     * @param  integer  $EventType
     * @param  string[] $Data
     * @return string
     */
    public function execute($EventType, $Data);
}
