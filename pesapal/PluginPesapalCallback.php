<?php
require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'library/CE/NE_Network.php';
require_once 'library/CE/NE_PluginCollection.php';

class PluginPesapalCallback extends PluginCallback
{
    var $params;

    function setCallbackParams($params)
    {
        $this->params = $params;
    }

    function processCallback()
    {
        $this->setCallbackParams($_POST);
        $params = $this->params;

        //Access here the values retuned by the gateway. Make sure to access them as specified by the gateway API.

        //Uncomment these lines for you to see all the available parameters you can use from the $params variable. All you need to do is try to use the plugin to pay an invoice from a customer
        //echo nl2br(print_r($params, true));
        //exit;



        $pluginFolderName = basename(dirname(__FILE__));
        $plugiName = $this->settings->get('plugin_'.$pluginFolderName.'_Plugin Name');

        //Make sure to get and assign the invoice id from the parameters returned from the gateway.
        $invoiceId = $params['invoiceId'];
        $gatewayPlugin = new Plugin($invoiceId, $pluginFolderName, $this->user);

        //If the gateway API provides a way to verify the response, make sure to verify it before performing any actions over the invoice.
        $transactionVerified = true;
        if (!$transactionVerified) {
            $transaction = "$plugiName transaction verification has failed.";
            $gatewayPlugin->PaymentRejected($transaction);
            exit;
        }

        //Make sure to get and assign the transaction id from the parameters returned from the gateway.
        $transactionId = $params['transactionId'];
        $gatewayPlugin->setTransactionID($transactionId);

        //Make sure to get and assign the transaction amount from the parameters returned from the gateway.
        $transactionAmount = $params['transactionAmount'];
        $gatewayPlugin->setAmount($transactionAmount);

        //Make sure to get and assign the credit card last four digits from the parameters returned from the gateway, or set it as 'NA' if not available.
        //Allowed values: 'NA', credit card last four digits
        $transactionLast4 = $params['transactionLast4'];
        $gatewayPlugin->setLast4($transactionLast4);

        //Make sure to get and assign the type of transaction from the parameters returned from the gateway.
        //Allowed values: charge, refund
        $transactionAction = $params['transactionAction'];
        switch($transactionAction) {
            case 'charge':
                $gatewayPlugin->setAction('charge');

                //Make sure to get and assign the transaction status from the parameters returned from the gateway.
                $transactionStatus = $params['transactionStatus'];

                //Make sure to replace the case values in the following switch, to match the status values returned from the gateway.
                switch($transactionStatus) {
                    case 'Completed':
                        $transaction = "$plugiName payment of $transactionAmount was accepted. (Transaction Id: ".$transactionId.")";
                        $gatewayPlugin->PaymentAccepted($transactionAmount, $transaction, $transactionId);
                        break;
                    case 'Pending':
                        $transaction = "$plugiName payment of $transactionAmount was marked 'pending' by $plugiName. (Transaction Id: ".$transactionId.")";
                        $gatewayPlugin->PaymentPending($transaction, $transactionId);
                        break;
                    case 'Failed':
                        $transaction = "$plugiName payment of $transactionAmount was rejected. (Transaction Id: ".$transactionId.")";
                        $gatewayPlugin->PaymentRejected($transaction);
                        break;
                }
                break;
            case 'refund':
                $gatewayPlugin->setAction('refund');

                //If the refund transaction returns a negative amount, make sure to remove the minus sign
                $transactionAmount = str_replace("-", "", $transactionAmount);

                //Make sure to get and assign the parent transaction id (id of the original transaction been refunded) from the parameters returned from the gateway.
                $parentTransactionId = $params['parentTransactionId'];

                if ($parentTransactionId != '') {
                    if ($gatewayPlugin->TransExists($parentTransactionId)) {
                        $newInvoice = $gatewayPlugin->retrieveInvoiceForTransaction($parentTransactionId);

                        if ($newInvoice && ($gatewayPlugin->m_Invoice->isPaid() || $gatewayPlugin->m_Invoice->isPartiallyPaid())) {
                            $transaction = "$plugiName payment of $transactionAmount was refunded. (Transaction Id: ".$transactionId.")";
                            $gatewayPlugin->PaymentRefunded($transactionAmount, $transaction, $transactionId);
                        } elseif (!$gatewayPlugin->m_Invoice->isRefunded()) {
                            CE_Lib::log(1, 'Related invoice not found or not set as paid on the application, when doing the refund.');
                        }
                    } else {
                        CE_Lib::log(1, 'Parent transaction id not matching any existing invoice on the application, when doing the refund.');
                    }
                } else {
                    CE_Lib::log(1, 'Callback is not returning the parent transaction id when refunding.');
                }
                break;
        }
    }
}
?>