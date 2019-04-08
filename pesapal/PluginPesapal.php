<?php
require_once 'modules/admin/models/GatewayPlugin.php';
require_once 'modules/billing/models/class.gateway.plugin.php';
require_once 'OAuth.php'; 

/**
* @package Plugins
*/
class PluginPesapal extends GatewayPlugin
{
    function getVariables()
    {

        /* Specification
              itemkey     - used to identify variable in your other functions
              type        - hidden, text, password, textarea, yesno, options. Hiddens are not visable to the user
              description - description of the variable, displayed in Clientexec
              value       - default value
        */
        $variables = array(
            // Parameters. Configure this values directly in Clientexec in the plugin configuration. Make sure to set "Demo Mode" to "No" before accepting actual payments through this plugin.
                lang('Signup Name') => array(
                    'type'        => 'text',
                    'description' => lang('Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card.'),
                    'value'       => 'Pesapal Payments'
                ),
                lang('Demo Mode') => array(
                    'type'        => 'yesno',
                    'description' => lang('Select YES if you want to set this plugin into Demo Mode for testing.<br><b>NOTE:</b> You must set to NO before accepting actual payments through this processor.'),
                    'value'       => '0'
                ),
                lang('Invoice After Signup') => array(
                    'type'        => 'yesno',
                    'description' => lang('Select YES if you want an invoice sent to the customer after signup is complete.'),
                    'value'       => '1'
                ),
            // Parameters. Configure this values directly in Clientexec in the plugin configuration. Make sure to set "Demo Mode" to "No" before accepting actual payments through this plugin.

            // Hidden parameters. Set here in the code the values required by the plugin. Make sure to set "Dummy Plugin" to 0 when creating a new working plugin.
                lang('Plugin Name') => array(
                    'type'        => 'hidden',
                    'description' => lang('How CE sees this plugin ( not to be confused with the Signup Name )'),
                    'value'       => 'Pesa Pal'
                ),
                lang('Dummy Plugin') => array(
                    'type'        => 'hidden',
                    'description' => lang('1 = Only used to specify a billing type for a customer. 0 = full fledged plugin requiring complete functions'),
                    'value'       => '0'
                ),
                lang('Auto Payment') => array(
                    'type'        => 'hidden',
                    'description' => lang('1 = The plugin allows to charge invoices without intervention of the customer. 0 = The plugin requires some actions from the customer in order to charge invoices'),
                    'value'       => '0'
                ),
            // Hidden parameters. Set here in the code the values required by the plugin. Make sure to set "Dummy Plugin" to 0 when creating a new working plugin.



            // Custom parameters for this specific plugin. Add here any additional parameter required by the plugin.
                lang('Consumer Key') => array(
                    'type'        => 'text',
                    'description' => lang('Please Enter your consumer key'),
                    'value'       => ''
                ),
                lang('Consumer Secret') => array(
                    'type'        => 'text',
                    'description' => lang('Please Enter your consumer key'),
                    'value'       => ''
                ),
    

            // Custom parameters for this specific plugin. Add here any additional parameter required by the plugin.
        );
        return $variables;
    }

    function credit($params)
    {
        $params['refund'] = true;
        return $this->singlePayment($params);
    }

    function singlepayment($params)
    {
        //Uncomment these lines for you to see all the available parameters you can use from the $params variable. All you need to do is try to use the plugin to pay an invoice from a customer
        // echo nl2br(print_r($params, true));
        // exit;



        $pluginFolderName = basename(dirname(__FILE__));

        //Pass this variable to your gateway to let it know where to send a callback.
        $urlFix = mb_substr($params['clientExecURL'], -1, 1) == "//" ? '' : '/';
        $callbackUrl = $params['clientExecURL'].$urlFix.'plugins/gateways/'.$pluginFolderName.'/callback.php';

        //Pass this variables to your gateway to let it know where to return after placing the payment, either if success ($returnUrl) or if fails or cancel ($returnCancelUrl).
        //Need to check to see if user is coming from signup
        if ($params['isSignup'] == 1) {
            // Actually handle the signup URL setting
            if ($this->settings->get('Signup Completion URL') != '') {
                //Pass this variables to your gateway to let it know where to return after placing the payment, either if success ($returnUrl) or if fails or cancel ($returnCancelUrl).
                $returnUrl = $this->settings->get('Signup Completion URL').'?success=1';
                $returnCancelUrl = $this->settings->get('Signup Completion URL');
            }else{
                //Pass this variables to your gateway to let it know where to return after placing the payment, either if success ($returnUrl) or if fails or cancel ($returnCancelUrl).
                $returnUrl = $params["clientExecURL"]."/order.php?step=complete&pass=1";
                $returnCancelUrl = $params["clientExecURL"]."/order.php?step=3";
            }
        }else {
            //Pass this variables to your gateway to let it know where to return after placing the payment, either if success ($returnUrl) or if fails or cancel ($returnCancelUrl).
            $returnUrl = $params["invoiceviewURLSuccess"];
            $returnCancelUrl = $params["invoiceviewURLCancel"];
        }

        //Use this variable to set the URL where you need to send the request, as specified by the gateway API.
        if ($this->getVariable('Demo Mode')) {
            $requestUrl = "https://demo.pesapal.com";
        } else {
            $requestUrl = "https://pesapal.com";
        }

        $token = NULL;
        $iframelink 	    = $requestUrl.'/api/PostPesapalDirectOrderV4';
        $iframelink_mobile  = $requestUrl.'/api/PostPesapalDirectOrderMobile';
        $consumer_key 	    = $params['plugin_pesapal_Consumer Key'];
        $consumer_secret    = $params['plugin_pesapal_Consumer Secret'];
        $signature_method   = new OAuthSignatureMethod_HMAC_SHA1();
        $consumer 	    = new OAuthConsumer($consumer_key, $consumer_secret);
        $amount             = $params['invoiceTotal'];
        $amount 	    = number_format($amount, 2);//format amount to 2 decimal places
        $desc 		    = $params['invoiceDescription'];
        $type 		    = 'MERCHANT';	
        $first_name 	    = $params['userFirstName'];
        $last_name 	    = $params['userLastName'];
        $email 		    = $params['userEmail'];
        $phonenumber	    = $params['userPhone'];
        $currency 	    = $params['userCurrency'];
        $reference 	    = $params['invoiceNumber'];
        $callback_url 	    = $callbackUrl;
        $post_xml	    = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
                                <PesapalDirectOrderInfo 
                                    xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" 
                        xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" 
                        Currency=\"".$currency."\" 
                        Amount=\"".$amount."\" 
                        Description=\"".$desc."\" 
                        Type=\"".$type."\" 
                        Reference=\"".$reference."\" 
                        FirstName=\"".$first_name."\" 
                        LastName=\"".$last_name."\" 
                        Email=\"".$email."\" 
                        PhoneNumber=\"".$phonenumber."\" 
                    xmlns=\"http://www.pesapal.com\" />";
        $post_xml = htmlentities($post_xml);
            
        //post transaction to pesapal
        $iframe_src = OAuthRequest::from_consumer_and_token($consumer, $token, "GET", $iframelink, null);
        $iframe_src->set_parameter("oauth_callback", $callback_url);
        $iframe_src->set_parameter("pesapal_request_data", $post_xml);
        $iframe_src->sign_request($signature_method, $consumer, $token);

        //Place here your source code for requesting a payment in your gateway.
        //Use this variable to create the form with all the parameters you will be sending to the gateway. For example:
        $strForm = "<html>\n";
        $strForm .=     "<head>\n";
        $strForm .=     "</head>\n";
        $strForm .=     "<body>\n";
        $strForm .=     "<iframe src='".$iframe_src."' width='100%' height='700px'  scrolling='no' frameBorder='0'><p>Browser unable to load iFrame</p></iframe>\n";
        $strForm .=     "</body>\n";
        $strForm .= "</html>";
        
        echo $strForm;
        exit;
        
    }
}
