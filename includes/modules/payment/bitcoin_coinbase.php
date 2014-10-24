<?php
    /*
     Bitcoin payment module for osCommerce
     Designed to interface with Coinbase API

     Original code, influenced by plugins included with osCommerce, as well as plugin from bitpay.com
     Developed as part of my capstone project for WGU, this is released under the MIT License.

     Comments describing functions are taken from this forum post: 
     	http://forums.oscommerce.com/topic/187772-payment-module-development-how-to/
     */

class bitcoin_coinbase {
    var $code, $title, $public_title, $description, $enabled;
    
    function bitcoin_coinbase() {
        global $PHP_SELF, $order; // do i need all of these? $HTTP_GET_VARS,

        // standard entries for modules, compare with other modules for reference
        $this->signature = 'bitcoin|bitcoin_coinbase|1.0|2.3';

        $this->code = 'bitcoin_coinbase';
        $this->title = MODULE_PAYMENT_BITCOIN_COINBASE_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_BITCOIN_COINBASE_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_BITCOIN_COINBASE_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_BITCOIN_COINBASE_SORT_ORDER') ? MODULE_PAYMENT_BITCOIN_COINBASE_SORT_ORDER : 0;
        $this->enabled = defined('MODULE_PAYMENT_BITCOIN_COINBASE_STATUS') && MODULE_PAYMENT_BITCOIN_COINBASE_STATUS ? true : false;
        $this->order_status = defined('MODULE_PAYMENT_BITCOIN_COINBASE_ORDER_STATUS_ID') && ((int)MODULE_PAYMENT_BITCOIN_COINBASE_ORDER_STATUS_ID > 0)
                                ? (int)MODULE_PAYMENT_BITCOIN_COINBASE_ORDER_STATUS_ID : 0;

        /*  Copied from paypal modules, may be worth implementing in the future. Places a link in admin page to test module.
         if (defined('MODULE_PAYMENT_BITCOIN_COINBASE_STATUS')) {
            $this->description .= $this->getTestLinkInfo();
         }
         */
        // disable the module's function and display error message on admin page if API keys are not set
        if ($this->enabled === true) {
            if (!tep_not_null(MODULE_PAYMENT_BITCOIN_COINBASE_API_KEY) || !tep_not_null(MODULE_PAYMENT_BITCOIN_COINBASE_API_SECRET)) {
                $this->description = '<div class="secWarning">' . MODULE_PAYMENT_BITCOIN_COINBASE_TEXT_ERROR_ADMIN_KEYS . '</div>' . $this->description;
                $this->enabled = false;
            }
        }
        // if the order object exists, call update_status method for this module
        if ($this->enabled === true) {
            if (isset($order) && is_object($order)) {
                $this->update_status();
            }
        }
    } // end constructor

    function update_status() {
        /*
         Here you can implement using payment zones (refer to standard PayPal module as reference).
         Called by module's class constructor, checkout_confirmation.php, checkout_process.php
         */
        // common functionality to disable the module if zones do not match
        global $order;
      
        if (($this->enabled == true) && ((int)MODULE_PAYMENT_BITCOIN_COINBASE_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id='" . MODULE_PAYMENT_BITCOIN_COINBASE_ZONE .
                                        "' and zone_country_id='" . $order->delivery['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }
            if ($check_flag == false) $this->enabled = false;
        }

        // Additional filtering:
        // make sure currency is supported
        $currencies = array_map('trim', explode(",", MODULE_PAYMENT_BITCOIN_COINBASE_CURRENCIES));
        if (array_search($order->info['currency'], $currencies) === false) $this->enabled = false;

    } // end function update_status

    function javascript_validation() {
        /*
         Here you may define client side javascript that will verify any input fields you use in the payment method selection page. Refer to standard cc module as reference (cc.php).
         Called by checkout_payment.php
         */
        return false;
    } // end funtion javascript_validation

    function selection() {
        /*
         This function outputs the payment method title/text and if required, the input fields.
         Called by checkout_payment.php
         */
        // should return an array with fields 'id', and 'module' referring to module's code and title variables respectively
        // can optionally return an array as 'fields' to display additional information
        // each element of 'fields' should contain an array with keys 'title' and 'field'
        //return array('id' => $this->code, 'module' => $this->title);
        // show icon if option is set, otherwise show title
        if (MODULE_PAYMENT_BITCOIN_COINBASE_ICON == 'False') return array('id' => $this->code, 'module' => $this->title);
        return array('id' => $this->code, 'module' => tep_image('https://www.coinbase.com/assets/buttons/buy_now_small.png', 'Pay with Bitcoin'));
    } // end function selection

    function pre_confirmation_check() {
        /*
         Use this function implement any checks of any conditions after payment method has been selected. You most probably don't need to implement anything here.
         Called by checkout_confirmation.php before any page output.
         */
        return false;
    } // end function pre_confirmation_check

    function confirmation() {
        /*
         Implement any checks or processing on the order information before proceeding to payment confirmation. You most probably don't need to implement anything here.
         Called by checkout_confirmation.php
         */
        // can return an array with fields, 'title' and 'fields'.
        // title should contain a string, fields is optional and should contain an array of arrays.
        // each sub-array should contain keys 'title' and 'field' with string suitable values
        
        return false;
    } // end function confirmation

    function process_button() {
        /*
         Outputs the html form hidden elements sent as POST data to the payment gateway.
         Called by checkout_confirmation.php
         */
        // This is where I chose to implement most of my functionality.
        // JQuery is used to interrupt the confirmation button and display the payment modal.
        // JQuery processes the events, so no redirect url's need to be sent to the API.
        global $order, $currency;
        require_once('coinbase-php/lib/Coinbase.php');
        $coinbase = Coinbase::withApiKey(MODULE_PAYMENT_BITCOIN_COINBASE_API_KEY, MODULE_PAYMENT_BITCOIN_COINBASE_API_SECRET);
        // attempt to generate a unique order id to identify this order, since it does not have one yet (gets generated when inserting into database)
        $custom_order_id = md5($order->customer['firstname'] .
                               $order->customer['lastname'] .
                               $order->info['total'] .
                               $order->products[mt_rand(1,sizeof($order->products))-1]['name'] .
                               time());
        $name = STORE_NAME . " Order";
        $description = ''; // todo: possibly list items in order (gets displayed in payment window)
        $total = $order->info['total'];
        //tep_session_register($custom_order_id);
        
        // request payment button from coinbase api
        $response = $coinbase->createButton($name, $total, $currency, $custom_order_id,
                                            array('description' => $description,
                                                  'custom_secure' => true,
                                                  'callback_url' => tep_href_link('coinbase_callback.php',
                                                                        'h='.crypt($custom_order_id, MODULE_PAYMENT_BITCOIN_COINBASE_API_KEY), 'SSL')));

        $buttoncode = $response->button->code; // get the code that will be used for interacting with the api
        $cartLink = tep_href_link(FILENAME_CHECKOUT_PAYMENT,'payment_error='. $this->code) . '&error='.urlencode('PAYMENT_EXPIRED');

        // output html and javascript
        // notes:
        //  the <div> is required to implement the coinbase payment modal
        //  take over the form's onsubmit action to trigger the payment modal
        //  handle two javascript events from coinbase api (these can be intentionally triggered by user, but payment is verified elsewhere)
        //  confirmation should submit the order for processing, expiration should return to cart
        echo <<< EOT
<div class='coinbase-button' data-code="$buttoncode" data-button-style='none'></div><script src='https://api.coinbase.com/assets/button.js' type='text/javascript'></script>
<script type="text/javascript">
        document.checkout_confirmation.onsubmit = function(){
            console.log("confirm_order clicked");
            $(document).trigger("coinbase_show_modal", "$buttoncode");
            return false;
        };
        $(document).on('coinbase_payment_complete', function(event, code) {
                       console.log("Payment completed for button " + code);
                       document.checkout_confirmation.submit();
                       });
        $(document).on('coinbase_payment_expired', function(event, code) {
                       console.log("Payment expired for button " + code);
                       window.location = "$cartLink";
                       });
        $(document).on('coinbase_payment_mispaid', function(event, code) {
                       console.log("Payment error for button " + code);
                       document.checkout_confirmation.submit();
                       });
</script>
EOT;
        return tep_draw_hidden_field('sid', $custom_order_id);
    } // end function process_button

    function before_process() {
        /*
         This is where you will implement any payment verification. This function can be quite complicated to implement. I will elaborate in this later in this post.
         Called by checkout_process.php before order is finalised
         */
        // Mark order as unpaid, status will be updated by callback
        // If malicious user triggered submission, order will be left marked with unpaid status and will need to be manually deleted
        // may attempt verification in the future
        global $order;
        $order->info['order_status'] = tep_db_input(MODULE_PAYMENT_BITCOIN_COINBASE_UNPAID_STATUS_ID);
        return false;
    } // end function before_process

    function after_process() {
        /*
         Here you may implement any post proessing of the payment/order after the order has been finalised. At this point you now have a reference to the created osCommerce order id and you would typically update any custom database tables you may have for your module. You most probably don't need to implement anything here.
         Called by checkout_process.php after order is finalised
         */
        // generating a comment using the custom order id previously generated so callback can identify the order
        global $order, $insert_id, $sid;
        $customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
        $sql_data_array = array('orders_id' => $insert_id,
                                'orders_status_id' => tep_db_input(MODULE_PAYMENT_BITCOIN_COINBASE_UNPAID_STATUS_ID),
                                'date_added' => 'now()',
                                'customer_notified' => $customer_notification,
                                'comments' => $sid);
        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        tep_session_unregister($custom_order_id);
        return false;
    } // end function after_process

    function get_error() {
        /*
         For more advanced error handling. When your module logic returns any errors you will redirect to checkout_payment.php with some error information.
         When implemented corretly, this function can be used to genereate the proper error texts for particular errors. The redirect must be formatted like this:
         
         tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT,'payment_error='. $this->code).'&error='.urlencode('some error');

         Called by checkout_payment.php
         */
        // todo: fix error strings
        global $HTTP_GET_VARS, $language;
        require_once(DIR_WS_LANGUAGES . $language . '/modules/payment/'.$this->code.'.php');
        $error = '';
        $error_text['title'] = MODULE_PAYMENT_BITCOIN_COINBASE_TEXT_ERROR_TITLE;
        if(isset($HTTP_GET_VARS['error']))
            $error = urldecode($HTTP_GET_VARS['error']);
    
        switch ($error) {
            case 'PAYMENT_EXPIRED':
                $error_text['error'] = MODULE_PAYMENT_BITCOIN_COINBASE_TEXT_ERROR_PAYMENT_EXPIRED;
                break;
            case 'CANCEL':
                $error_text['error'] = MODULE_PAYMENT_BITCOIN_COINBASE_TEXT_ERROR_PAYMENT_CANCEL;
                break;
            default:
                $error_text['error'] = MODULE_PAYMENT_BITCOIN_COINBASE_TEXT_ERROR_UNKNOWN;
                break;
        }
        return $error_text;
    } // end function get_error

    function check() {
        /*
         Standard functionlity for osCommerce to see if the module is installed.
         */
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key='MODULE_PAYMENT_BITCOIN_COINBASE_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    } // end function check

    function install() {
        /*
         This is where you define module's configurations (displayed in admin).
    
         Fields and effects:
         configuration_title: Displayed name of configuration option
         configuration_key: Key as listed in keys() array below
         configuration_value: Default value
         configuration_description: Descriptive text for option, displayed while editing
         configuration_group_id: References configuration_group_id table in database, 6 is 'Module Options'
         sort_order: Appears to have no effect, sort order seems to depend on order of items in keys() array below
         set_function: (optional) Use to enable non-text input types
         use_function: (optional) Passed to previous function to auto-populate list
         date_added: Date module was installed, all known examples use now()
         */
        
        // create custom order status entries
      
        $unpaid_status_id = $this->create_order_status("Unpaid [Coinbase]", 0);
        $paid_status_id = $this->create_order_status("Paid [Coinbase]", 1, 1);
        $error_status_id = $this->create_order_status("Payment Error [Coinbase]");
        
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) " .
                     "values ('Enable Bitcoin via Coinbase Module','MODULE_PAYMENT_BITCOIN_COINBASE_STATUS','False','Do you want to accept bitcoin payments via coinbase.com?'," .
                     "'6','0','tep_cfg_select_option(array(\'True\',\'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values " .
                     "('API Key','MODULE_PAYMENT_BITCOIN_COINBASE_API_KEY','','Enter your API key generated at coinbase.com','6','0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values " .
                     "('API Secret Key','MODULE_PAYMENT_BITCOIN_COINBASE_API_SECRET','','Enter your API secret key generated at coinbase.com','6','0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) " .
                     "values ('Unpaid Order Status','MODULE_PAYMENT_BITCOIN_COINBASE_UNPAID_STATUS_ID','" . $unpaid_status_id .
                      "','Automatically set the status of unpaid orders to this value.','6','0','tep_cfg_pull_down_order_statuses(','tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) " .
                     "values ('Paid Order Status','MODULE_PAYMENT_BITCOIN_COINBASE_PAID_STATUS_ID','" . $paid_status_id . "','Automatically set the status of paid orders to this value.','6','0'," .
                     "'tep_cfg_pull_down_order_statuses(','tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) " .
                     "values ('Payment Error Order Status','MODULE_PAYMENT_BITCOIN_COINBASE_ERROR_STATUS_ID','" . $error_status_id .
                     "','Automatically set the status of orders with payment errors to this value.','6','0'," . "'tep_cfg_pull_down_order_statuses(','tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) " .
                     "values ('Payment Zone','MODULE_PAYMENT_BITCOIN_COINBASE_ZONE','0','If a zone is selected, only enable this payment method for that zone.','6','0'," .
                     "'tep_cfg_pull_down_zone_classes(','tep_get_zone_class_title', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values " .
                     "('Sort order of display.','MODULE_PAYMENT_BITCOIN_COINBASE_SORT_ORDER','0','Sort order of display. Lowest is displayed first.','6','0', now())");
        // should find a way to keep this updated
        $cb_currencies = "AFN, ALL, DZD, AOA, ARS, AMD, AWG, AUD, AZN, BSD, BHD, BDT, BBD, BYR, BZD, BMD, BTN, BOB, BAM, BWP, BRL, GBP, BND, BGN, BIF, KHR, CAD, CVE, KYD, XAF, XPF, CLP, CNY, COP, KMF, CDF, CRC, HRK, CUP, CZK, DKK, DJF, DOP, XCD, EGP, ERN, EEK, ETB, EUR, FKP, FJD, GMD, GEL, GHS, GHS, GIP, GTQ, GNF, GYD, HTG, HNL, HKD, HUF, ISK, INR, IDR, IRR, IQD, ILS, JMD, JPY, JPY, JOD, KZT, KES, KWD, KGS, LAK, LVL, LBP, LSL, LRD, LYD, LTL, MOP, MKD, MGA, MWK, MYR, MVR, MRO, MUR, MXN, MDL, MNT, MAD, MZN, MMK, NAD, NPR, ANG, TWD, NZD, NIO, NGN, KPW, NOK, OMR, PKR, PAB, PGK, PYG, PEN, PHP, PLN, QAR, RON, RUB, RWF, SHP, SVC, WST, SAR, RSD, SCR, SLL, SGD, SBD, SOS, ZAR, KRW, LKR, SDG, SRD, SZL, SEK, CHF, SYP, STD, TJS, TZS, THB, TOP, TTD, TND, TRY, TMM, TMM, UGX, UAH, AED, USD, UYU, UZS, VUV, VEF, VND, XOF, YER, ZMK, ZWL";
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values " .
                     "('Currencies','MODULE_PAYMENT_BITCOIN_COINBASE_CURRENCIES','" . $cb_currencies . "'," .
                     "'Only enable this module if one of these currencies is selected. Make sure it is supported by coinbase.com','6','0', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION .
                     " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) " .
                     "values ('Enable Bitcoin Icon displays','MODULE_PAYMENT_BITCOIN_COINBASE_ICON','True','Do you want to show the Pay with Bitcoin icon on the payment select page?'," .
                     "'6','0','tep_cfg_select_option(array(\'True\',\'False\'), ', now())");
    } // end function install

    function remove() {
        /*
         Standard functionality to uninstall the module.
         */
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    } // end function remove

    function keys() {
        /*
         This array must include all the configuration setting keys defined in your install() function.
         */
        return array(
                     'MODULE_PAYMENT_BITCOIN_COINBASE_STATUS',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_API_KEY',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_API_SECRET',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_UNPAID_STATUS_ID',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_PAID_STATUS_ID',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_ERROR_STATUS_ID',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_CURRENCIES',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_SORT_ORDER',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_ZONE',
                     'MODULE_PAYMENT_BITCOIN_COINBASE_ICON');
    } // end function keys

    // creates custom order status entries
    // takes the text for the status entry, and optionally a public and download flag
    // returns new entry's id
    function create_order_status($status_string, $public = 1, $downloads = 0) {
        // first check to see if status entry already exists
        $check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = '$status_string' limit 1");
        
        // create the entries if they don't exist
        if (tep_db_num_rows($check_query) < 1) {
            // get next available id
            $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
            $status = tep_db_fetch_array($status_query);
            
            $status_id = $status['status_id']+1;
            
            // get language list and add status entries for each
            $languages = tep_get_languages();
            
            foreach ($languages as $lang) {
                $sql_data_array = array('orders_status_id' => $status_id,
                                        'language_id' => $lang['id'],
                                        'orders_status_name' => $status_string);
                tep_db_perform(TABLE_ORDERS_STATUS, $sql_data_array);
            }
            
            // make sure public_flag field exists, then update flags for new entry
            $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
            if (tep_db_num_rows($flags_query) == 1) {
                $sql_data_array = array('public_flag' => $public,
                                        'downloads_flag' => $downloads);
                tep_db_perform(TABLE_ORDERS_STATUS, $sql_data_array, 'update', "orders_status_id = '" . $status_id . "'");
            }
        } else {
            // if the entry already exists, get its id
            $check = tep_db_fetch_array($check_query);
            
            $status_id = $check['orders_status_id'];
        }
        return $status_id;
    } // end function create_order_status
    
} // end class bitcoin_coinbase
?>
