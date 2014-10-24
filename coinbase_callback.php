<?php
    require_once('coinbase-php/lib/Coinbase.php');
    require_once('includes/application_top.php');


    $debug = false;
    $callback = verifyCallback();
    
    if (is_string($callback)) {
        error_log('bitcoin-coinbase callback error: '.$callback);
        header("HTTP/1.1 500 Internal Server Error");
    } else {
        error_log('bitcoin-coinbase callback success!');
        if ($debug) print_object($callback);
    }

    
    function verifyCallback() {
        try {
            $json = json_decode(file_get_contents('php://input'));
        } catch (Exception $e) {
            return 'error decoding json: ' . e;
        }
        if (is_string($json))
            return $json;

        if (!is_object($json->order))
            return "invalid json request";
        $hash = $_GET['h'];

        // do additional verification (securing callbacks)
        $coinbase = Coinbase::withApiKey(MODULE_PAYMENT_BITCOIN_COINBASE_API_KEY, MODULE_PAYMENT_BITCOIN_COINBASE_API_SECRET);
        $coinbaseOrderId = $json->order->id;
      
        // make sure order exists with coinbase
        try {
            $coinbaseOrder = $coinbase->getOrder($coinbaseOrderId);
        } catch (Exception $e) {
            return "incorrect Coinbase order ID '$coinbaseOrderId'";
        }
        if (!$coinbaseOrder)
            return "incorrect Coinbase order ID '$coinbaseOrderId'";
        // custom order id, stored as custom in coinbase order
        $customId = $coinbaseOrder->custom;
      
        // check hash passed in url
        if ($hash != crypt($customId, MODULE_PAYMENT_BITCOIN_COINBASE_API_KEY))
            return "incorrect security hash";

        // make sure local order exists by loading it
        // delay to make sure the order gets inserted first
        // callback gets re-sent if it fails, just relying on that
        //sleep(10);
        $order_query = tep_db_query("select orders_id from " . TABLE_ORDERS_STATUS_HISTORY . " where comments = '" . $customId . "'");
        if(tep_db_num_rows($order_query) == 1) {
            $result = tep_db_fetch_array($order_query);
            $orderId = $result['orders_id'];
        } else {
            return "unable to find local order with comment $customId";
        }
        if ($coinbaseOrder->status == "completed") {
            update_status($orderId, $coinbaseOrder->id, MODULE_PAYMENT_BITCOIN_COINBASE_PAID_STATUS_ID);
        } else {
            update_status($orderId, $coinbaseOrder->id, MODULE_PAYMENT_BITCOIN_COINBASE_ERROR_STATUS_ID);
        }
        //process_payment($orderId, $coinbaseOrder);
        return $json->order;

    } // end function verifyCallback
  
    function update_status($orderId, $cbOrderId, $status) {
        $custNotified = (SEND_EMAILS == 'true') ? '1' : '0';
        if ($status == MODULE_PAYMENT_BITCOIN_COINBASE_PAID_STATUS_ID) {
            $commentString = "Paid with Coinbase order " . $cbOrderId;
        } else {
            $commentString = "Problem with Coinbase order " . $cbOrderId;
        }
        // set the status in the orders table
        tep_db_query("update " . TABLE_ORDERS . " set orders_status = " . $status . " where orders_id = " . intval($orderId));

        // create a new entry in status history log with coinbase order id and updated status
        $sql_data_array = array('orders_id' => intval($orderId),
                                'orders_status_id' => $status,
                                'date_added' => 'now()',
                                'customer_notified' => $custNotified,
                                'comments' => $commentString);
        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        // todo: send email updates if desired
    } // end function update_status
    
    // print_object function for debugging
    // iterates through an object and prints its contents to the error log
    function print_object($object, $tabs='') {
        if (is_object($object)) {
            foreach ($object as $key => $value) {
                if (is_object($value)) {
                    error_log($tabs . $key);
                    print_object($value, $tabs."\t");
                } else {
                    error_log($tabs . $key . ": " . $value);
                }
            }
        }
    }
?>
