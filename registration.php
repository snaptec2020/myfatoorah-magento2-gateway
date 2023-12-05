<?php

/**
 * Copyright © MyFatoorah. All rights reserved.
 * See GPL-3.0 for license details.
 */

if (!defined('BP')) {
    // phpcs:ignore Magento2.Functions.DiscouragedFunction
    define('BP', dirname(getcwd()));
}

if (!defined('MYFATOORAH_LOG_FILE')) {
    define('MYFATOORAH_LOG_FILE', BP . '/var/log/myfatoorah.log');
}
if (!defined('MFSHIPPING_LOG_FILE')) {
    define('MFSHIPPING_LOG_FILE', BP . '/var/log/myfatoorah_shipping.log');
}

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'MyFatoorah_Gateway',
    __DIR__
);
