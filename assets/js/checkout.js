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
    // Add jsprint function
    function jsprint(message, isAlert = false) {
        if (isAlert) {
            alert(message);
        } else {
            console.log('%cXPay Debug:', 'color: #2196F3; font-weight: bold;', message);
        }
    }

    // 1. Core Fee Display Functions
    function OrderBreakdown() {
        const { total_amount, xpay_fees, community_fees, currency } = xpayJSData.prepareAmountData;
        jsprint('OrderBreakdown data: ' + JSON.stringify(xpayJSData.prepareAmountData), false);
                
        // Remove existing fee rows if any
        $('.xpay-fee, .merchant-fee, .discount').remove();
        
        // Get Payment Method name
        const paymentMethodName = $('input[name="xpay_payment_method"]:checked').val();
        const formattedMethodName = paymentMethodName.charAt(0).toUpperCase() + paymentMethodName.slice(1);
        
        // Add XPay fee row
        if (xpay_fees) {
            const xpayFeeRow = `<tr class="xpay-fee">
                <th style="font-family: Arial, sans-serif; font-size: 14px;">XPay Fee for ${formattedMethodName}</th>
                <td><span class="woocommerce-Price-amount">${xpay_fees} ${currency}</span></td>
            </tr>`;
            $('.order-total').before(xpayFeeRow);
        }
        
        // Add merchant fee row
        if (community_fees) {
            const merchantFeeRow = `<tr class="merchant-fee">
                <th style="font-family: Arial, sans-serif; font-size: 14px;">Merchant Fee</th>
                <td><span class="woocommerce-Price-amount">${community_fees} ${currency}</span></td>
            </tr>`;
            $('.order-total').before(merchantFeeRow);
        }
        
        // Update total
        $('.order-total th').text('Total');
        $('.order-total .woocommerce-Price-amount bdi').text(`${parseFloat(total_amount).toFixed(2)} ${currency}`);
        $('.order-total .woocommerce-Price-amount amount').text(parseFloat(total_amount).toFixed(2));
        $('input[name="order_total"]').val(parseFloat(total_amount).toFixed(2));
    }

    // Update the success handler in updatePreparedAmount
    function updatePreparedAmount(paymentMethod) {
        $.ajax({
            url: xpayJSData.ajax.ajax_url,
            method: 'POST',
            dataType: 'json',  // Add this line to automatically parse JSON
            data: {
                action: 'xpay_store_prepared_amount_dynamically',
                payment_method: paymentMethod
            },
            beforeSend: function () {
                $("#xpay_total_amount").text("Updating...");
            },
            success: function (response) {
                if (response.success) {
                    jsprint(`Prepare amount Response data for ${paymentMethod}: ${JSON.stringify(response.data)}`, false);
                    xpayJSData.prepareAmountData = response.data;
                    OrderBreakdown();
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

    function handlePaymentMethodChange(selectedMethod) {
            jsprint("Selected method: " + selectedMethod, false);
            
            // Reset promo code input and message
            $('#xpay_promo_code').val('');
            $('#promo_code_message').empty();
            
            // Remove any existing discount and reset total to original amount
            $('.xpay-fee, .merchant-fee .discount').remove();
            $('.order-total th').text('Total');
            $('.order-total .woocommerce-Price-amount bdi').text(`${xpayJSData.prepareAmountData.total_amount} ${xpayJSData.prepareAmountData.currency}`);
            $('.order-total .woocommerce-Price-amount amount').text(xpayJSData.prepareAmountData.total_amount);
            $('input[name="order_total"]').val(xpayJSData.prepareAmountData.total_amount);
    
            updatePreparedAmount(selectedMethod);
        }
    
        // Detect payment method change (using event delegation)
        $(document).on('change', '.xpay-payment-radio', function () {
            handlePaymentMethodChange($(this).val());
        });

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
            response => jsprint('Promocode ID stored in session: ' + JSON.stringify(response), false),
            (xhr, status, error) => jsprint('Error storing promocode ID: ' + JSON.stringify({ status, error, response: xhr.responseText }), false)
        );
    }

    // Handle successful promo code application
    function handleSuccessfulPromo(response) {
        const formattedAmount = parseFloat(response.data.value).toFixed(2);
        const message = `Promo Code Applied! New total: ${formattedAmount} ${response.data.currency}`;
        displayMessage(message, true);

        const totalAmount = parseFloat(xpayJSData.prepareAmountData.total_amount );
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
    
    // 8. Event Listeners
    $(document.body).on('updated_checkout', function() {
        const selectedMethod = $('input[name="xpay_payment_method"]:checked').val() || 'card';
        updatePreparedAmount(selectedMethod);
    });
    $(document.body).on('updated_checkout', initPromoCodeFunctionality);
});