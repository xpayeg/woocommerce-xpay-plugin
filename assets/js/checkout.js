jQuery(document).ready(function ($) {
    // 1. Core Fee Display Functions
    function displayFees() {
        // put defults for bellow Values
        const currency = xpayJSData.prepareAmountData.currency ;
        const totalAmount = parseFloat(xpayJSData.prepareAmountData.total_amount) ;

        // mak logs here
        console.log(xpayJSData.prepareAmountData)
        
        // Remove existing fee rows if any
        $('.xpay-fee, .merchant-fee, .discount').remove();
        
        // Create fee rows
        const paymentMethodName = $('input[name="xpay_payment_method"]:checked').val();
        const formattedMethodName = paymentMethodName.charAt(0).toUpperCase() + paymentMethodName.slice(1);
        
        const xpayFeeRow = `<tr class="xpay-fee">
            <th style="font-family: Arial, sans-serif; font-size: 14px;">XPay Fee for ${formattedMethodName}</th>
            <td><span class="woocommerce-Price-amount">${xpayJSData.prepareAmountData.xpay_fees_amount || '0.00'} ${currency}</span></td>
        </tr>`;
        
        const merchantFeeRow = `<tr class="merchant-fee">
            <th style="font-family: Arial, sans-serif; font-size: 14px;">Merchant Fee</th>
            <td><span class="woocommerce-Price-amount">${xpayJSData.prepareAmountData.community_fees_amount || '0.00'} ${currency}</span></td>
        </tr>`;
        
        // Insert fee rows before the order total
        $('.order-total').before(xpayFeeRow + merchantFeeRow);

        // Update the order total
        $('.order-total .woocommerce-Price-amount bdi').text(`${totalAmount.toFixed(2)} ${currency}`);
        $('.order-total .woocommerce-Price-amount amount').text(totalAmount.toFixed(2));
        $('input[name="order_total"]').val(totalAmount.toFixed(2));
    }

    // Function to update prepared amount dynamically based on selected payment method
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
                    console.log("Response data:", response.data); // Debug full response
                    xpayJSData.prepareAmountData.total_amount = response.data.total_amount;
                    xpayJSData.prepareAmountData.xpay_fees_amount = response.data.xpay_fees;
                    xpayJSData.prepareAmountData.community_fees_amount = response.data.community_fees;
                    displayFees();
                } else {
                    alert("Failed to update amount: " + response.message);
                }
            },
            error: function () {
                alert("Error updating amount.");
            }
        });
    }

    // Detect payment method change (using event delegation)
    $(document).on('change', '.xpay-payment-radio', function () {
        console.log("Payment method changed");
        var selectedMethod = $(this).val();
        console.log("Selected method:", selectedMethod);
        
        // Reset promo code input and message
        $('#xpay_promo_code').val('');
        $('#promo_code_message').empty();
        
        // // Remove any existing discount
        // Remove any existing discount and reset total to original amount
        $('.xpay-fee, .merchant-fee .discount').remove();
        $('.order-total th').text('Total');
        $('.order-total .woocommerce-Price-amount bdi').text(`${xpayJSData.prepareAmountData.total_amount} ${xpayJSData.prepareAmountData.currency}`);
        $('.order-total .woocommerce-Price-amount amount').text(xpayJSData.prepareAmountData.total_amount);
        $('input[name="order_total"]').val(xpayJSData.prepareAmountData.total_amount);

        updatePreparedAmount(selectedMethod);
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
                console.log('Promocode response received:', response);
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

    // 5. Order Summary Functions
    function updateOrderSummary(totalAmount, totalAfterDiscount, currency) {
        // Remove existing fee and discount rows if any
        $('.xpay-fee, .merchant-fee, .discount').remove();
        
        // Only proceed if we have valid amounts
        if (totalAmount && totalAfterDiscount) {
            const discountAmount = totalAmount - totalAfterDiscount;
            
            // Only show summary if there's a valid discount
            if (discountAmount > 0) {
                // Add rows...
                const xpayFeeRow = `<tr class="xpay-fee">
                    <th style="font-family: 'Linotte', Arial, sans-serif; font-size: 14px;">XPay Fee</th>
                    <td><span class="woocommerce-Price-amount">${xpayJSData.prepareAmountData.xpay_fees_amount || '0.00'} ${currency}</span></td>
                    </tr>`;
            
                 const merchantFeeRow = `<tr class="merchant-fee">
                    <th style="font-family: 'Linotte', Arial, sans-serif; font-size: 14px;">Merchant Fee</th>
                    <td><span class="woocommerce-Price-amount">${xpayJSData.prepareAmountData.community_fees_amount || '0.00'} ${currency}</span></td>
                    </tr>`;
                
                // Add discount row
                const discountRow = `<tr class="discount">
                    <th>Discount </th>
                    <td><span class="woocommerce-Price-amount" style="color: red;">-${discountAmount.toFixed(2)} ${currency}</span></td>
                </tr>`;
                
                // Insert all rows before the order total
                $('.order-total').before(xpayFeeRow + merchantFeeRow + discountRow);
                
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
            response => console.log('%c Promocode ID stored in session:', 'color: green;', response),
            (xhr, status, error) => console.error('Error storing promocode ID:', { status, error, response: xhr.responseText })
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
        
        updateOrderSummary(totalAmount, totalAfterDiscount, currency);
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