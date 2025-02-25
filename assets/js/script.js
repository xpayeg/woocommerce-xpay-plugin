jQuery(document).ready(function ($) {
    console.log("%cHello from script.js file", "color: green; font-weight: bold;");

    // Function to initialize the promo code functionality
    function initPromoCodeFunctionality() {

        $('#apply_promo_code').on('click', function (e) {
            e.preventDefault();
            var promoCode = $('#xpay_promo_code').val();
            if (!promoCode) {
                $('#promo_code_message').html('<p style="color: red;">Please enter a promo code.</p>');
                return;
            }
            var data = {
                action: 'validate_xpay_promo_code',
                security: xpayJSData.ajax.nonce,
                url: xpayJSData.promoCodeRequestData.iframe_base_url + "/api/promocodes/validate/",
                name: promoCode,
                community_id: xpayJSData.promoCodeRequestData.community_id,
                amount: xpayJSData.promoCodeRequestData.amount,
                currency: xpayJSData.promoCodeRequestData.currency,
                phone_number: $('input[name="billing_phone"]').val(),
                payment_for: 'API_PAYMENT',
                variable_amount_id: xpayJSData.promoCodeRequestData.variable_amount_id
            };

            console.log("Sending AJAX request with data:", data);

            $.ajax({
                type: 'POST',
                url: xpayJSData.ajax.ajax_url,
                dataType: 'json',
                data: data,
                beforeSend: function () {
                    $('#apply_promo_code').prop('disabled', true).text('Validating...');
                },
                success: function (response) {
                    console.log('AJAX response received:', response);
                    $('#apply_promo_code').prop('disabled', false).text('Apply');

                    if (response.success) {
                        $('#promo_code_message').html('<p style="color: green;">Promo Code Applied: ' + 
                            response.data.value + ' ' + response.data.currency + '</p>');

                        // Ensure cart_total is defined and properly formatted
                        if (xpayJSData.cart_total) {
                            var cartTotal = parseFloat(xpayJSData.cart_total.replace(/[^0-9.-]+/g,""));
                            var newTotal = cartTotal - parseFloat(response.data.value);
                            console.log('New total:', newTotal.toFixed(2));
                            $('#order_total').text(newTotal.toFixed(2) + ' ' + response.data.currency);
                        } else {
                            console.error('cart_total is not defined or improperly formatted');
                        }

                        $('body').trigger('update_checkout');

                        // Send promocode_id to the server to store in WooCommerce session
                        $.ajax({
                            type: 'POST',
                            url: xpayJSData.ajax.ajax_url,
                            data: {
                                action: 'store_promocode_id',
                                security: xpayJSData.ajax.nonce,
                                promocode_id: response.data.promocode_id
                            },
                            success: function (response) {
                                console.log('%c Promocode ID stored in session:', 'color: green;', response);
                            },
                            error: function (xhr, status, error) {
                                console.error('Error storing promocode ID:', {
                                    status: status,
                                    error: error,
                                    response: xhr.responseText
                                });
                            }
                        });
                    } else {
                        $('#promo_code_message').html('<p style="color: red;">' + 
                            (response.data ? response.data.message : 'Invalid promo code') + '</p>');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Ajax error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    $('#apply_promo_code').prop('disabled', false).text('Apply');
                    $('#promo_code_message').html('<p style="color: red;">Error validating promo code.</p>');
                }
            });
        });
    }

    // Initialize the promo code functionality on document ready
    initPromoCodeFunctionality();

    // Reinitialize the promo code functionality after WooCommerce updates the checkout form
    $(document.body).on('updated_checkout', function () {
        initPromoCodeFunctionality();
    });
});