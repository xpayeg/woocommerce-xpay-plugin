<?php if (!defined('ABSPATH')) exit; ?>

<style>
    /* Promo Code Styles */
    .xpay-show-promo-button {
        background-color: #f8f9fa;
        color: #0073aa;
        border: 2px dashed #0073aa;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        text-align: center;
        font-weight: 500;
    }

    .xpay-show-promo-button:hover {
        background-color: #0073aa;
        color: white;
        border-style: solid;
    }

    .xpay-promo-code-input {
        flex: 1;
        min-width: 0;
    }

    .xpay-apply-button {
        background-color: #0073aa !important;
        color: white !important;
        border: none !important;
        padding: 8px 15px !important;
        border-radius: 4px !important;
    }
</style>

<div id="xpay_promo_code_wrapper" class="form-row form-row-wide xpay-promo-code-container">
    <button type="button" id="show_promo_code" class="button xpay-show-promo-button">
        <?php echo esc_html__('Have Xpay Promo Code?', 'wc-gateway-xpay'); ?>
    </button>
    <div id="promo_code_input_container" style="display: none; margin-top: 10px;">
        <div style="display: flex; gap: 8px; align-items: center;">
            <input type="text" id="xpay_promo_code" name="xpay_promo_code" 
                class="input-text xpay-promo-code-input" 
                placeholder="<?php echo esc_attr__('Enter promo code', 'wc-gateway-xpay'); ?>">
            <button type="button" id="apply_promo_code" 
                class="button xpay-apply-button"><?php echo esc_html__('Apply', 'wc-gateway-xpay'); ?></button>
        </div>
        <div id="promo_code_message"></div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $("#show_promo_code").click(function() {
        $("#promo_code_input_container").slideToggle(300);
        $(this).text(function(i, text) {
            return text === "<?php echo esc_html__('Have Xpay Promo Code?', 'wc-gateway-xpay'); ?>" 
                ? "<?php echo esc_html__('Hide Promo Code', 'wc-gateway-xpay'); ?>" 
                : "<?php echo esc_html__('Have Xpay Promo Code?', 'wc-gateway-xpay'); ?>";
        });
    });
});
</script>