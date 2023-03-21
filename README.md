# woocommerce-xpay-plugin
this is woocommerce based plugin to add xpay payment as payment option for any wordpress website 

# Getting Started
These instructions will guide you on how to integrate and test xpay's payment method with your Woocommerce driven site.
We have setup a development environment for you to test and play around the integration process.

# Pre-requisite
- installed woocommerce on your website 
- get your integration settings from XPAY ( community ID, payment API key, API payment ID ). please contact [Xpay](https://xpay.app/) to get 
this info 

# Installation
Step 1: Download the contents of this repository.

Step 2: Copy the folder "woocommerce-xpay-plugin-1.0", then go to your wordpress site root folder and paste it in the "plugins" folder. This is typically located in {your-wordpress-site-folder}\wp-content\plugins.

![](/screenshots/1.png?raw=true "")

Step 3: add read and execute premissions to "woocommerce-xpay-plugin-1.0/check_transaction.php" and "woocommerce-xpay-plugin-1.0/upate_order.php" to make them accessible from the web.

Step 4: Open your wordpress site's admin page and navigate to Plugins > Installed Plugins. Then find WooCommerce XPAY Gateway and click on Activate

![](/screenshots/2.png?raw=true "")


Step 5: From the admin site, go to Woocommerce > Settings and select the Checkout tab. Then click on Xpay from the list of available payment methods at the top of the page. If you don't see it here, make sure you have activated the Xpay payment plugin as mentioned in Step 3 above.

![](/screenshots/4.png?raw=true "")

&#x1F534;&#x1F534;Note : Don't forget to update your api payment on XPAY with your xpay plugin callback URL.

![](/screenshots/5.png?raw=true "")


Step 6: Once on the Xpay Payment Gateway settings page, fill in the Xpay settings data community ID, payment API key and API payment ID. These values can be obtained from your Xpay contact person as indicated in the pre-requisite section above.

![](/screenshots/3.png?raw=true "")


Note :You have an option to use our Sandbox (staging mode) application to test out your integration before going live. We highly recommend using this feature when testing the integration.



