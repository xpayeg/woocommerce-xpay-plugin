/**
 * XPay WooCommerce Checkout Handler
 * 
 * This file handles all checkout-related functionality for the XPay payment gateway:
 * - Payment method selection and fee calculations
 * - Promo code validation and application
 * - Order total updates and display
 * - Session storage for payment data
 * 
 * Dependencies:
 * - jQuery
 * - WooCommerce checkout
 * - XPay API integration
 * 
 * Global objects used:
 * - xpayJSData: Contains configuration and payment data
 * - xpayJSData.prepareAmountData: Payment amounts and currency info
 * - xpayJSData.promoCodeRequestData: Promo code validation configuration
 */

jQuery(document).ready(function ($) {
    
    let paymentMethodsData = {
        total_amount: 0,
        xpay_fees: 0,
        community_fees: 0,
        currency: 'EGP'
    };

    // Add jsprint function
    function jsprint(message, isAlert = false, color = '#2196F3') {
        if (isAlert) {
            alert(message);
        } else {
            console.log('%cXPay Debug:', `color: ${color}; font-weight: bold;`, message);
        }
    }

    function OrderBreakdown(selectedMethod = null) {
        let paymentMethodName = selectedMethod || $('input[name="xpay_payment_method"]:checked').val();
        paymentMethodName = paymentMethodName ? paymentMethodName.toUpperCase() : null;
        
        jsprint('Selected payment method for OrderBreakdown is : ' + paymentMethodName, false, "#00FF00");
        
        if (!paymentMethodName || !paymentMethodsData) {
            jsprint('No payment method selected or data available for, returning', false, '#00FF00');
            return;
        }

        const methodData = paymentMethodsData[paymentMethodName];

        if (!methodData || !methodData.total_amount) {
            jsprint('No data found for payment method: ' + paymentMethodName, false,"#00FF00");
            // Clear all fee rows and reset total to initial checkout amount
            $('.xpay-fee, .merchant-fee, .discount').remove();
            $('.order-total th').text('Total');
            $('.order-total .woocommerce-Price-amount bdi').text(`${parseFloat(xpayJSData.initialData.subtotal_amount).toFixed(2)} ${xpayJSData.initialData.currency}`);
            $('.order-total .woocommerce-Price-amount amount').text(parseFloat(xpayJSData.initialData.total_amount).toFixed(2));
            $('input[name="order_total"]').val(parseFloat(xpayJSData.initialData.total_amount).toFixed(2));
            return;
        }

        const { 
            total_amount, 
            xpay_fees_amount, 
            community_fees_amount,
            total_amount_currency 
        } = methodData;

        jsprint(paymentMethodName +' - Fees: ' + JSON.stringify(methodData), false,"#00FF00");
                
        // Rest of the function remains the same
        $('.xpay-fee, .merchant-fee, .discount').remove();
        
        // Add XPay fee row if exists
        if (xpay_fees_amount > 0) {
            const xpayFeeRow = `<tr class="xpay-fee">
                <th style="font-family: Arial, sans-serif; font-size: 14px;">XPay Fee for ${paymentMethodName}</th>
                <td><span class="woocommerce-Price-amount">${xpay_fees_amount} ${total_amount_currency}</span></td>
            </tr>`;
            $('.order-total').before(xpayFeeRow);
        }
        
        // Add merchant fee row
        if (community_fees_amount) {
            const merchantFeeRow = `<tr class="merchant-fee">
                <th style="font-family: Arial, sans-serif; font-size: 14px;">Merchant Fee</th>
                <td><span class="woocommerce-Price-amount">${community_fees_amount} ${total_amount_currency}</span></td>
            </tr>`;
            $('.order-total').before(merchantFeeRow);
        }
        
        // Update total
        $('.order-total th').text('Total');
        $('.order-total .woocommerce-Price-amount bdi').text(`${parseFloat(total_amount).toFixed(2)} ${total_amount_currency}`);
        $('.order-total .woocommerce-Price-amount amount').text(parseFloat(total_amount).toFixed(2));
        $('input[name="order_total"]').val(parseFloat(total_amount).toFixed(2));
    }

    function getPaymentMethodsFees(paymentMethod = null) {
        $.ajax({
            url: xpayJSData.ajax.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'xpay_get_payment_methods_fees',
                payment_method: paymentMethod
            },
            beforeSend: function () {
                $("#xpay_total_amount").text("Updating...");
            },
            success: function (response) {
                if (response.success) {
                    // Add root level data as CARD payment method
                    const responseData = {
                        ...response.data,
                        CARD: {
                            total_amount: response.data.total_amount,
                            xpay_fees_amount: response.data.xpay_fees_amount,
                            community_fees_amount: response.data.community_fees_amount,
                            total_amount_currency: response.data.total_amount_currency
                        }
                    };
                    paymentMethodsData = responseData;
                    OrderBreakdown(selectedMethod='card');
                } else {
                    jsprint("Failed to update amount: " + response.message, false);
                }
            },
            error: function (xhr, status, error) {
                const isDebug = xpayJSData.ajax.debug || false;
                jsprint("Failed to update the order amount breakdown.", isDebug);
            }
        });
    }
    // 3. UI Helper Functions
    function displayMessage(message, isSuccess = false) {
        const color = isSuccess ? 'green' : 'red';
        $('#promo_code_message').html(`<p style="color: ${color};">${message}</p>`);
    }

    // Toggle button state
    function toggleButtonState(isLoading = false) {
        $('#apply_promo_code').prop('disabled', isLoading)
            .text(isLoading ? 'Validating...' : 'Apply');
    }

    // Prepare promo code validation data
    function getPromoCodeData(promoCode) {
        return {
            action: 'validate_xpay_promo_code',
            security: xpayJSData.ajax.nonce,
            url: xpayJSData.promoCodeRequestData.iframe_base_url + "/api/promocodes/validate/",
            name: promoCode,
            community_id: xpayJSData.promoCodeRequestData.community_id,
            amount: xpayJSData.prepareAmountData.total_amount,
            currency: xpayJSData.promoCodeRequestData.currency,
            phone_number: $('input[name="billing_phone"]').val(),
            payment_for: 'API_PAYMENT',
            variable_amount_id: xpayJSData.promoCodeRequestData.variable_amount_id
        };
    }

    // 4. Promo Code Core Functions
    function validatePromoCode(promoCode) {
        const data = getPromoCodeData(promoCode);
        const originalTotal = parseFloat(xpayJSData.prepareAmountData.total_amount);
        const currency = xpayJSData.promoCodeRequestData.currency;

        $.ajax({
            type: 'POST',
            url: xpayJSData.ajax.ajax_url,
            dataType: 'json',
            data: data,
            beforeSend: () => toggleButtonState(true),
            success: function(response) {
                jsprint('Promocode response received: ' + JSON.stringify(response), false);
                toggleButtonState(false);

                if (response.success) {
                    handleSuccessfulPromo(response);
                } else {
                    displayMessage(response.data ? response.data.message : 'Invalid promo code');
                    // Only remove discount row and reset total, keep original total label
                    $('.discount').remove();
                    $('.order-total th').text('Total'); // Reset to original text
                    $('.order-total .woocommerce-Price-amount bdi').text(`${originalTotal.toFixed(2)} ${currency}`);
                    $('.order-total .woocommerce-Price-amount amount').text(originalTotal.toFixed(2));
                    $('input[name="order_total"]').val(originalTotal.toFixed(2));
                }
            }
        });
    }

    // 5.  Order Discount Function
    function OrderDiscount(totalAmount, totalAfterDiscount, currency) {
        $('.discount').remove();        
        if (totalAmount && totalAfterDiscount) {
            const discountAmount = totalAmount - totalAfterDiscount;            
            if (discountAmount > 0) {
                const discountRow = `<tr class="discount">
                    <th>Discount </th>
                    <td><span class="woocommerce-Price-amount" style="color: red;">-${discountAmount.toFixed(2)} ${currency}</span></td>
                </tr>`;
                
                // Insert all rows before the order total
                $('.order-total').before(discountRow);
                
                // Update WooCommerce total elements and rename the total label
                $('.order-total th').text('Total after discount');
                $('.order-total .woocommerce-Price-amount bdi').text(`${totalAfterDiscount.toFixed(2)} ${currency}`);
                $('.order-total .woocommerce-Price-amount amount').text(totalAfterDiscount.toFixed(2));
                $('input[name="order_total"]').val(totalAfterDiscount.toFixed(2));
            }
        }
    }

    // Store promocode in session
    function storePromocode(promocodeId, discountAmount) {
        return $.ajax({
            type: 'POST',
            url: xpayJSData.ajax.ajax_url,
            data: {
                action: 'store_promocode_id',
                security: xpayJSData.ajax.nonce,
                promocode_id: promocodeId,
                discount_amount: discountAmount
            }
        }).then(
            response => jsprint('Promocode ID and Discount Amount stored  session: ' + JSON.stringify(response), false),
            (xhr, status, error) => jsprint('Error storing promocode ID: ' + JSON.stringify({ status, error, response: xhr.responseText }), false)
        );
    }

    // Handle successful promo code application
    function handleSuccessfulPromo(response) {
        const formattedAmount = parseFloat(response.data.value).toFixed(2);
        const message = `Promo Code Applied! New total: ${formattedAmount} ${response.data.currency}`;
        displayMessage(message, true);

        const totalAmount = parseFloat(paymentMethodsData.total_amount);
        const totalAfterDiscount = parseFloat(response.data.value);
        const currency = response.data.currency
        
        OrderDiscount(totalAmount, totalAfterDiscount, currency);
        // $(document.body).trigger('update_checkout');
        
        storePromocode(response.data.promocode_id, response.data.value);
    }

    // 6. Initialization Functions
    function initPromoCodeFunctionality() {
        $('#apply_promo_code').on('click', function(e) {
            e.preventDefault();
            const promoCode = $('#xpay_promo_code').val();
            
            if (!promoCode) {
                displayMessage('Please enter a promo code');
                return;
            }
            
            validatePromoCode(promoCode);
        });
    }

    // 7. Initialize Components
    initPromoCodeFunctionality();
    
    // Detect payment method change (using event delegation)
    $(document).on('change', '.xpay-payment-radio', function () {
        const selectedPaymentMethod = $('input[name="xpay_payment_method"]:checked').val();
        jsprint('Payment method changed to: '+ selectedPaymentMethod, false,"#00FFFF");
        OrderBreakdown(selectedPaymentMethod);
    });

    $(document.body).on('updated_checkout', function() {
        getPaymentMethodsFees();      
    });

    // Initialize promo code functionality once
    $(document.body).on('updated_checkout', initPromoCodeFunctionality);
});