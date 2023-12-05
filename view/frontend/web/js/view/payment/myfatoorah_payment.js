define(
        [
            'uiComponent',
            'Magento_Checkout/js/model/payment/renderer-list'
        ],
        function (
                Component,
                rendererList
                ) {
            'use strict';
            rendererList.push(
                    {
                        type: 'myfatoorah_payment',
                        component: 'MyFatoorah_Gateway/js/view/payment/method-renderer/myfatoorah_payment'
                    }
            );
            /**
             * Add view logic here if needed
             */
            return Component.extend({});
        }
);
