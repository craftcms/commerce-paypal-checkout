function findClosestParent(startElement, fn) {
  var parent = startElement.parentElement;
  if (!parent) return undefined;
  return fn(parent) ? parent : findClosestParent(parent, fn);
}

function initPaypalCheckout() {
  if (typeof paypal_checkout_sdk === 'undefined') {
    setTimeout(initPaypalCheckout, 200);
  } else {
    var $wrapper = document.querySelector('.paypal-rest-form');
    var $renderDiv = $wrapper.firstElementChild;
    var $form = findClosestParent($wrapper, function (element) {
      return element.tagName === 'FORM';
    });
    var completeUrl = $wrapper.dataset.complete;
    var transactionHash;
    var errorShown = false;
    let responseError = false;

    paypal_checkout_sdk
      .Buttons({
        createOrder: function (data, actions) {
          var form = new FormData($form);

          return fetch(window.location.href, {
            method: 'post',
            body: form,
            headers: {
              Accept: 'application/json',
            },
          })
            .then(function (res) {
              if (res.status != 200) {
                responseError = true;
              }

              return res.json();
            })
            .then(function (data) {
              if (responseError) {
                var errorMessage = '';

                try {
                  // Handle PayPal errors
                  var error = JSON.parse(data.error);
                  if (error.details && error.details.length) {
                    errorMessage = error.details[0].description;
                  }
                } catch (e) {
                  // Handle Commerce errors
                  errorMessage = data.message;
                }

                throw Error(errorMessage);
              }

              transactionHash = data.transactionHash;
              return data.transactionId; // Use the same key name for order ID on the client and server
            })
            .catch(function (error) {
              errorShown = true;
              alert(error);
            });
        },
        onError: function (err) {
          $form.dataset.processing = false;
          if (!errorShown) {
            alert(err);
          }
        },
        onApprove: function (data, actions) {
          var separator = '?';
          if (completeUrl.indexOf('?') >= 0) {
            separator = '&';
          }

          window.location =
            completeUrl +
            separator +
            'commerceTransactionHash=' +
            transactionHash;
        },
      })
      .render('#' + $renderDiv.id);
  }
}

initPaypalCheckout();
