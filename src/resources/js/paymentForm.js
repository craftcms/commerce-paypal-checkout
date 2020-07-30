function initPaypalCheckout() {
    if (typeof paypal_checkout_sdk === "undefined") {
        setTimeout(initPaypalCheckout, 200);
    } else {
        var $wrapper = $('.paypal-rest-form');
        var $form = $wrapper.parents('form');
        var paymentUrl = $wrapper.data('prepare');
        var completeUrl = $wrapper.data('complete');
        var transactionHash;
        var errorShown = false;

        paypal_checkout_sdk.Buttons({
            createOrder: function(data, actions) {
                // Set up the transaction
                var postData = {};
                var $formElements = $form.find('input[type=hidden]');

                for (var i = 0; i < $formElements.length; i++) {
                    if ($formElements[i].name === 'action') {
                        continue;
                    }
                    postData[$formElements[i].name] = $formElements.get(i).value;
                }

                var form = new FormData($form[0]);

                return fetch(paymentUrl, {
                    method: 'post',
                    body: form,
                    headers: {
                        'Accept': 'application/json'
                    }
                }).then(function(res) {
                    return res.json();
                }).then(function(data) {
                    if (data.error) {
                        var error = JSON.parse(data.error);
                        if (error.details && error.details.length) {
                            throw Error(error.details[0].description);
                        }
                    }
                    transactionHash = data.transactionHash;
                    return data.transactionId; // Use the same key name for order ID on the client and server
                }).catch(function(error) {
                    errorShown = true;
                    alert(error);
                });
            },
            onError: function(err) {
                if (!errorShown) {
                    alert(err);
                }
            },
            onApprove: function(data, actions) {
                var separator = '?';
                if (completeUrl.indexOf('?') >= 0) {
                    separator = '&';
                }

                window.location = completeUrl + separator + 'commerceTransactionHash=' + transactionHash;
            }
        }).render('#paypal-button-container');
    }
}

initPaypalCheckout();