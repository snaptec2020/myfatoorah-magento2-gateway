/*browser:true*/
/*global define*/
define(
        [
            'mfSessionFile', // here the session.js file is mapped
            'mfAppleFile', // here the session.js file is mapped
            'mfGoogleFile', // here the session.js file is mapped
            'jquery',
            'Magento_Checkout/js/view/payment/default',
            'Magento_Checkout/js/model/quote',
            'mage/url'
        ],
        function (
                mfSessionFile,
                mfAppleFile,
                mfGoogleFile,
                $,
                Component,
                quote,
                url
                ) {
            'use strict';
            var self;

            var urlCode = 'myfatoorah_payment';
            var checkoutConfig = window.checkoutConfig.payment.myfatoorah_payment;

            var mfData = 'pm=myfatoorah';

            var paymentMethods = checkoutConfig.paymentMethods;
            var listOptions = checkoutConfig.listOptions;
            var mfLang = checkoutConfig.lang;

            var mfError = checkoutConfig.mfError;

            var baseGrandTotal = checkoutConfig.baseGrandTotal;

            var isApSession = window.ApplePaySession; //to facilitate the test

            return Component.extend({
                redirectAfterPlaceOrder: false,
                defaults: {
                    template: 'MyFatoorah_Gateway/payment/form'
                },
                initialize: function () {
                    this._super();
                    self = this;
                },
                initObservable: function () {
                    this._super().observe([
                        'gateways',
                        'transactionResult'
                    ]);

                    return this;

                },
                getCode: function () {
                    return urlCode;
                },
                getData: function () {
                    return {
                        'method': this.item.method,
                        'additional_data': {
                            'gateways': this.gateways(),
                            'transaction_result': this.transactionResult()
                        }
                    };
                },
                validate: function () {
                    return true;
                },
                getTitle: function () {
                    return checkoutConfig.title;
                },
                getDescription: function () {
                    return checkoutConfig.description;
                },
                afterPlaceOrder: function () {
                    window.location.replace(url.build(urlCode + '/checkout/index?' + mfData));
                },
                PlaceOrderMyFatoorah: function () {
                    if (!self.placeOrder()) {
                        $('body').loader('hide');
                    }
                    return;
                },
                placeOrderCard: function (paymentMethodId) {
                    if (mfError) {
                        return false;
                    }

                    $('body').loader('show');

                    mfData = 'pm=' + paymentMethodId;
                    return self.PlaceOrderMyFatoorah();
                },
                placeOrderForm: function () {
                    if (mfError) {
                        return false;
                    }

                    $('body').loader('show');

                    if (listOptions === 'myfatoorah' || paymentMethods.all.length === 0) {
                        mfData = 'pm=myfatoorah';
                        return self.PlaceOrderMyFatoorah();
                    }

                    if (paymentMethods.cards.length === 1 && paymentMethods.all.length === 1) {
                        mfData = 'pm=' + paymentMethods['cards'][0]['PaymentMethodId'];
                        return self.PlaceOrderMyFatoorah();
                    }

                    myFatoorah.submit().then(
                            function (response) {
                                // On success
                                mfData = 'sid=' + response.sessionId;
                                return self.PlaceOrderMyFatoorah();
                            },
                            function (error) {
                                //to load this error in the trans file
                                $.mage.__('Card details are invalid or missing!');

                                // In case of errors
                                $('body').loader('hide');
                                self.messageContainer.addErrorMessage({message: $.mage.__(error)});

                                return false;
                            }
                    );
                },
                getUpdatedPaymentMethods: function () {
                    var totals = quote.getTotals()();
                    var baseGrandTotalNew = (totals ? totals : quote)['base_grand_total'];

                    if (baseGrandTotal == baseGrandTotalNew) {
                        return paymentMethods;
                    }

                    $.ajax({
                        showLoader: true,
                        url: url.build(urlCode + '/checkout/payment'),
                        async: false,
                        cache: false,
                        data: {
                            ajax: 1,
                            //baseGrandTotal: baseGrandTotalNew
                        },
                        type: "POST",
                        dataType: 'json'
                    }).done(function (data) {

                        if (data.error === null) {
                            paymentMethods = $.parseJSON(data.paymentMethods);
                            baseGrandTotal = baseGrandTotalNew;
                        } else {
                            self.messageContainer.addErrorMessage({
                                message: data.error
                            });
                            $("#mfSubmitPayment").attr("disabled", "disabled");
                        }
                    });
                    return paymentMethods;

                },
                isSectionVisible: function (section) {
                    if (section === 'ap' && !isApSession) {
                        return false;
                    }
                    return !jQuery.isEmptyObject(paymentMethods[section]);
                },
                isOrVisible: function (section) {
                    if (jQuery.isEmptyObject(paymentMethods['ap']) && jQuery.isEmptyObject(paymentMethods['gp'])) {
                        if (section === 'cards') {
                            return false;

                        } else if (section === 'form' && jQuery.isEmptyObject(paymentMethods['cards'])) {
                            return false;
                        }
                    }
                    return true;
                },
                isContainerVisible: function () {
                    if (mfError) {
                        self.messageContainer.addErrorMessage({message: mfError});
                        $("#mfSubmitPayment").attr("disabled", "disabled");
                        return false;
                    }

                    if (listOptions === 'myfatoorah') {
                        return false;
                    }

                    if (paymentMethods.all.length === 0) {
                        $('[data-mfVersion]').remove();
                        return false;
                    }

                    if (paymentMethods.all.length === 1 && paymentMethods.cards.length === 1) {
                        return false;
                    }

                    return true;
                },
                updateApplePaySession: function () {
                    if (listOptions === 'myfatoorah' || paymentMethods.all.length === 0) {
                        return;
                    }

                    if (isApSession) {
                        return;
                    }

                    //remove ap as a card
                    $('[data-mfPmCode="ap"]').remove();

                    if (jQuery.isEmptyObject(paymentMethods['gp'])) {
                        $('#mf-or-cardsDivider').remove();
                    }

                    //is there any cards left?
                    if ($('.mf-card-container').length === 0) {
                        $('#mf-sectionCard').remove();

                        if (jQuery.isEmptyObject(paymentMethods['gp'])) {
                            $('#mf-or-formDivider').remove();
                        }

                        if (paymentMethods.form.length === 0 && jQuery.isEmptyObject(paymentMethods['gp'])) {
                            if (paymentMethods.cards.length !== 1 || paymentMethods['cards'][0]['PaymentMethodCode'] === 'ap') {
                                $('[data-mfVersion]').remove();
                            }
                        }
                    }
                },
                getCardTitle: function (mfCard) {
                    return (mfLang === 'ar') ? mfCard.PaymentMethodAr : mfCard.PaymentMethodEn;
                },
                getForm: function () {
                    var mfConfig = {
                        countryCode: checkoutConfig.countryCode,
                        sessionId: checkoutConfig.sessionId,
                        cardViewId: "mf-form-element",
                        // The following style is optional.
                        style: {
                            cardHeight: checkoutConfig.height,
                            direction: (mfLang === 'ar') ? 'rtl' : 'ltr',
                            input: {
                                color: "black",
                                fontSize: "13px",
                                fontFamily: "sans-serif",
                                inputHeight: "32px",
                                inputMargin: "-1px",
                                borderColor: "c7c7c7",
                                borderWidth: "1px",
                                borderRadius: "0px",
                                boxShadow: "",
                                placeHolder: {
                                    holderName: $.mage.__('Name On Card'),
                                    cardNumber: $.mage.__('Card Number'),
                                    expiryDate: $.mage.__('MM / YY'),
                                    securityCode: $.mage.__('CVV')
                                }
                            },
                            label: {
                                display: false,
                                color: "black",
                                fontSize: "13px",
                                fontFamily: "sans-serif",
                                text: {
                                    holderName: "Card Holder Name",
                                    cardNumber: "Card Number",
                                    expiryDate: "ExpiryDate",
                                    securityCode: "Security Code"
                                }
                            },
                            error: {
                                borderColor: "red",
                                borderRadius: "8px",
                                boxShadow: "0px"
                            }
                        }
                    };
                    myFatoorah.init(mfConfig);
                    window.addEventListener("message", myFatoorah.recievedMessage, false);
                },
                getCards: function () {
                    paymentMethods = self.getUpdatedPaymentMethods();

                    return paymentMethods['cards'];
                },
                getApple: function () {
                    paymentMethods = self.getUpdatedPaymentMethods();

                    //to avoid dublicate draw
                    jQuery('#mf-ap-element').html('');

                    var mfApConfig = {
                        sessionId: checkoutConfig.sessionId,
                        countryCode: checkoutConfig.countryCode,
                        currencyCode: paymentMethods['ap']['GatewayData']['GatewayCurrency'],
                        amount: paymentMethods['ap']['GatewayData']['GatewayTotalAmount'],
                        cardViewId: "mf-ap-element",
                        callback: mfApPayment
                    };

                    myFatoorahAP.init(mfApConfig);
                    //window.addEventListener("message", myFatoorahAP.recievedMessage, false);

                    function mfApPayment(response)
                    {
                        if (mfError) {
                            return false;
                        }

                        $('body').loader('show');

                        mfData = 'sid=' + response.sessionId;
                        return self.PlaceOrderMyFatoorah();
                    }
                },
                getGoogle: function () {
                    paymentMethods = self.getUpdatedPaymentMethods();

                    //to avoid dublicate draw
                    jQuery('#mf-gp-element').html('');

                    var mfGpConfig = {
                        sessionId: checkoutConfig.sessionId,
                        countryCode: checkoutConfig.countryCode,
                        currencyCode: paymentMethods['gp']['GatewayData']['GatewayCurrency'],
                        amount: paymentMethods['gp']['GatewayData']['GatewayTotalAmount'],
                        cardViewId: "mf-gp-element",
                        isProduction: !checkoutConfig.isTest,
                        callback: mfGpPayment
                    };
                    myFatoorahGP.init(mfGpConfig);
                    //window.addEventListener("message", myFatoorahGP.recievedMessage, false);

                    function mfGpPayment(response)
                    {
                        if (mfError) {
                            return false;
                        }

                        $('body').loader('show');

                        mfData = 'sid=' + response.sessionId;
                        return self.PlaceOrderMyFatoorah();
                    }
                }
            });
        }
);