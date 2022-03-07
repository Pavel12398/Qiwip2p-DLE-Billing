<?php if( !defined( 'BILLING_MODULE' ) ) die( "Hacking attempt!" );

require_once MODULE_PATH . '/payments/qiwip2p/vendor/autoload.php';

/**
 * QIWI P2P for DLE Billing
 * @author        Pavel12398 <dev.necropan@gmail.com>
 */

Class Payment
{
	var $doc = "https://developer.qiwi.com/ru/p2p-payments";
	var $server = 0;
	
	function Settings($config) 
	{
		$Form = array();

		$Form[] = array("Публичный ключ", "Публичный ключ из аутентификационных данных API P2P QIWI", "<input name=\"save_con[public_key]\" class=\"edit bk\" type=\"password\" value=\"" . $config['public_key'] ."\">" );
        $Form[] = array("Секретный ключ", "Секретный ключ из аутентификационных данных API P2P QIWI", "<input name=\"save_con[secret_key]\" class=\"edit bk\" type=\"password\" value=\"" . $config['secret_key'] ."\">" );
		$Form[] = array("IP фильтр", "Список доверенных ip адресов, можно указать маску", "<input name=\"save_con[ip_filter]\" class=\"edit bk\" type=\"text\" value=\"" . $config['ip_filter'] ."\">" );
        
        $Form[] = [
            "Валюта платежа:",
            "Выберите валюту совершения платежа.",
            "<select name=\"save_con[currency]\" class=\"uniform\">
				<option value=\"RUB\" " . ( $config['currency'] == 'RUB' ? "selected" : "" ) . ">RUB</option>
				<option value=\"KZT\" " . ( $config['currency'] == 'KZT' ? "selected" : "" ) . ">KZT</option>
			</select>"
        ];

		return $Form;
	}
	
	function form( $id, $config_payment, $invoice, $currency, $desc)
	{
		global $config, $member_id;
		$billing_config = require MODULE_DATA . '/config.php';
		
        require_once MODULE_PATH . '/payments/qiwip2p/vendor/autoload.php';
        $billPayments = new Qiwi\Api\BillPayments($config_payment['secret_key']);
		
		$success_url = $config['http_home_url'] . 'billing.html/pay/ok';
		$fail_url = $config['http_home_url'] . 'billing.html/pay/bad';

        $billId = $id;
        $fields= array(
                'amount'                => number_format($invoice['invoice_pay'], 2, '.', ''),
                'currency'              => 'RUB',
                'comment'               => $desc,
                'expirationDateTime'    => date('c',time() + 60*10),
                'email'                 => $member_id['email'],
                'account'               => $member_id['user_id'],
                'successUrl'            => $success_url
            );

            /** @var \Qiwi\Api\BillPayments $billPayments */
            $response = $billPayments->createBill($billId, $fields);


            $parse_url = parse_url(urldecode($response['payUrl']));
            $url = $parse_url['scheme'].'://'.$parse_url['host'].$parse_url['path'];
            parse_str($parse_url['query'], $frm);

            $form = '<form method="GET" id="paysys_form" action="'.$url.'">';
            foreach($frm as $key => $value){
                $form .= '<input type="hidden" name="'.$key.'" value="'.$value.'">';
            }
            $form .= '<input type="submit" name="process" class="bs_button" value="Оплатить" /></form>';
            return $form;
	}
	
    function check_out($data, $config_payment, $invoice){
        $code = null;

        $sign = null;
        $body = null;

        $billPayments = new Qiwi\Api\BillPayments($config_payment['secret_key']);
         try {
            $sign = array_key_exists('HTTP_X_API_SIGNATURE_SHA256', $_SERVER) ? stripslashes($_SERVER['HTTP_X_API_SIGNATURE_SHA256']) : '';
            $body = file_get_contents('php://input');
            $notice = json_decode($body, true);
            if ($billPayments->checkNotificationSignature($sign, $notice, $config_payment['secret_key'])) {

                // Process status.
                switch ( $notice['bill']['status']['value'] ) {
                    case 'WAITING'://Счет ожидает оплаты
                        $code = "Error WAITING\n";
                        break;
                    case 'PAID'://Счет оплачен
                        $code = 200;
                        break;
                    case 'REJECTED'://Счет отменен
                        $code = "Error REJECTED\n";
                        break;
                    case 'EXPIRED'://Счет истек       
                        $code = "Error EXPIRED\n";
                        break;
                    case 'PARTIAL'://Счет частично возвращен
                        $code = "Error PARTIAL\n";
                        break;
                    case 'FULL'://Счет полностью возвращен        
                        $code = "Error FULL\n";
                        break;
                    default:
                        $this->logs($notice['bill']['status']['value']);                
                }

            }
        } catch (Exception $exception) {
            $this->logs($exception->getMessage());
        }


        return $code;
	}

    function check_id($result){		

        $sign = array_key_exists('HTTP_X_API_SIGNATURE_SHA256', $_SERVER) ? stripslashes($_SERVER['HTTP_X_API_SIGNATURE_SHA256']) : '';
        $body = file_get_contents('php://input');
        $notice = json_decode($body, true);
        return $notice['bill']['billId'];
	}
	
	function check_ok($result){
        header('Content-Type: application/json');
        return '{"error":"0"}';		
	}

    function check_ip($remote_addr,$sIP){
        $arrIP = explode('.', $remote_addr);
        if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
            '(' . $arrIP[1] . '|\*{1})(\.)' .
            '(' . $arrIP[2] . '|\*{1})(\.)' .
            '(' . $arrIP[3] . '|\*{1})($|,)/', $sIP)){
                return false;
        }else{
                return true;
        }
    }

	function logs($msg)
    {
        // log send
        $log = '[' . date('D M d H:i:s Y', time()) . '] ';
        $log .= is_array($msg) ? json_encode($msg) : $msg ;
        $log .= "\n";
        file_put_contents(dirname(__FILE__) . "/qiwip2p.log", $log, FILE_APPEND);
    }

}
$Paysys = new Payment();