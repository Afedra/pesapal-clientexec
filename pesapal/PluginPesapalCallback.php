<?php
require_once 'modules/admin/models/PluginCallback.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'library/CE/NE_Network.php';
require_once 'library/CE/NE_PluginCollection.php';
require_once 'checkStatus.php'; 

class PluginPesapalCallback extends PluginCallback
{
	var $params;

    function setCallbackParams($params)
    {
        $this->params = $params;
    }

    function processCallback()
    {
        $this->setCallbackParams($_REQUEST);
        $params = $this->params;
        
        $pluginFolderName = basename(dirname(__FILE__));        
        $plugiName = $this->settings->get('plugin_'.$pluginFolderName.'_Plugin Name');

        $invoiceId = $params['pesapal_merchant_reference'];
        $gatewayPlugin = new Plugin($invoiceId, $pluginFolderName, $this->user);
        
        $transactionId = $params['pesapal_transaction_tracking_id'];
        $gatewayPlugin->setTransactionID($transactionId);
        
        // TODO: Add key , secret
        $pesapal = new pesapalCheckStatus("IZFskPTNZw0KdBIfCRddN2Du4efwRJrk", "dx3JPpK99W9FQKvI3tRuGe8nWTk=");
        $transactionStatus = $pesapal->checkTransactionStatus($invoiceId, $transactionId);
        
        $transactionAmount = $gatewayPlugin->m_Invoice->m_SubTotal;
        $gatewayPlugin->setAmount($transactionAmount);
        $gatewayPlugin->setAction('charge');
                
        switch($transactionStatus) {
            case 'COMPLETED':            
                $transaction = "$plugiName payment of $transactionAmount was accepted. (Transaction Id: ".$transactionId.")";
                $gatewayPlugin->PaymentAccepted($transactionAmount, $transaction, $transactionId);
                break;
            case 'PENDING':
                $transaction = "$plugiName payment of $transactionAmount was marked 'pending' by $plugiName. (Transaction Id: ".$transactionId.")";
                $gatewayPlugin->PaymentPending($transaction, $transactionId);
                break;
            case 'FAILED':
                $transaction = "$plugiName payment of $transactionAmount was rejected. (Transaction Id: ".$transactionId.")";
                $gatewayPlugin->PaymentRejected($transaction);
                break;
            case 'INVALID':            
                if ($recipients = $this->settings->get('Application Error Notification')) {
                    include_once 'library/CE/NE_MailGateway.php';
                    $mailGateway = new NE_MailGateway();
                    $body = "A Pesapal payment for invoice ".$invoiceId." has been received.\n"
                        ."The payment amount was ".$transactionAmount." and the Pesapal Transaction Id was ".$transactionId.".\n"
                        ."In order to avoid frauds with this kind of payments, please take a look on your pesapal plugin configuration and set a proper 'Secret Word'.\n"
                        ."Thank you.\n";
                    $recipients = explode("\r\n", $recipients);
    
                    foreach ($recipients as $recipient) {
                        $mailSend = $mailGateway->mailMessageEmail(
                            array('HTML' => null, 'plainText' => $body),
                            $this->settings->get('Support E-mail'),
                            $this->settings->get('Support E-mail'),
                            $recipient,
                            '',
                            'ClientExec Pesapal Security Risk Notification',
                            1
                        );
                    }

                }
                $transaction = "$plugiName payment of $transactionAmount was invalid. (Transaction Id: ".$transactionId.")";
                $gatewayPlugin->PaymentRejected($transaction);
                break;
        }

        $returnURL = CE_Lib::getSoftwareURL()."/index.php?fuse=billing&paid=1&controller=invoice&view=invoice&id=" . $invoiceId;

        header("Location: " . $returnURL);


    }

}
?>