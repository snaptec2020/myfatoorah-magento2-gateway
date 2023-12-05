define(
        [
            'jquery',
            'mageUtils',
            'MyFatoorah_Gateway/js/model/shipping-rates-validation-rules',
            'mage/translate'
        ], function ($, utils, validationRules, $t) {
    'use strict';

    return {
        validationErrors: [],

        /**
         * @param  {Object} address
         * @return {Boolean}
         */
        validate: function (address) {
            var self = this;

            this.validationErrors = [];
            $.each(
                    validationRules.getRules(), function (field, rule) {
                var message;

                if (rule.required && utils.isEmpty(address[field])) {
                    message = $t('Field %1 is required.').replace('%1', field);

                    self.validationErrors.push(message);
                }
            }
            );

            return !this.validationErrors.length;
        }
    };
}
);
