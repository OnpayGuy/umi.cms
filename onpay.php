/*
#####################################
#  OnPay payment module for UMICMS.
#  Copyright (c) 2011 by norgen
#  http://www.onpay.ru
#  http://www.umicms.ru
#  Ver. 1.0.0
#####################################
*/
<?php

class onpayPayment extends payment {

    public function validate() {
        return true;
    }

    public function process($template = null) {
        $currency = strtoupper(mainConfiguration::getInstance()->get('system', 'default-currency'));
        $amount = number_format($this->order->getActualPrice(), 1, '.', '');
        $orderId = $this->order->getId();
        $onpay_id = $this->object->mnt_onpay_id;
        $secret_key = $this->object->mnt_secret_key;
        $successUrl = $this->object->mnt_success_url;
        $systemUrl = $this->object->mnt_system_url;
        $signature = md5("fix;$amount;$currency;$orderId;yes;$secret_key");
        $param = array();
        $param['formAction'] = "http://secure.onpay.ru/pay/$onpay_id";
        $param['mntId'] = $orderId;
        $param['mntCurrencyCode'] = $currency;
        $param['mntAmount'] = $amount;
        $param['mntSignature'] = $signature;
        $param['mntSuccessUrl'] = $successUrl;
        $this->order->setPaymentStatus('initialized');
        list($templateString) = def_module::loadTemplates("tpls/emarket/payment/onpay/" . $template . ".tpl", "form_block");
        return def_module::parseTemplate($templateString, $param);
    }

    public function poll() {
        $key = $this->object->mnt_secret_key;
        $buffer = outputBuffer::current();
        $buffer->clear();
        $buffer->contentType("text/plain");
        $rezult = "FAIL";

        function answer($type, $code, $pay_for, $order_amount, $order_currency, $text, $key) {

            print ("$type;$pay_for;$order_amount;$order_currency;$code;$key");
            $md5 = strtoupper(md5("$type;$pay_for;$order_amount;$order_currency;$code;$key"));
            return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<result>\n<code>$code</code>\n<pay_for>$pay_for</pay_for>\n<comment>$text</comment>\n<md5>$md5</md5>\n</result>";
        }

        function answerpay($type, $code, $pay_for, $order_amount, $order_currency, $text, $onpay_id, $key) {
            $md5 = strtoupper(md5("$type;$pay_for;$onpay_id;$pay_for;$order_amount;$order_currency;$code;$key"));
            return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<result>\n<code>$code</code>\n <comment>$text</comment>\n<onpay_id>$onpay_id</onpay_id>\n <pay_for>$pay_for</pay_for>\n<order_id>$pay_for</order_id>\n<md5>$md5</md5>\n</result>";
        }

        if ($_REQUEST['type'] == 'check') { //Ответ на запрос check от OnPay
            $error = 0;
            $order_amount = $_REQUEST['order_amount'];
            $order_currency = $_REQUEST['order_currency'];
            $pay_for = $_REQUEST['pay_for'];
            $md5 = $_REQUEST['md5'];
            $rezult = answer($_REQUEST['type'], 0, $pay_for, $order_amount, $order_currency, 'OK', $key);
        }

        if ($_REQUEST['type'] == "pay") { //Ответ на запрос pay от OnPay
            $onpay_id = $_REQUEST['onpay_id'];
            $code = $pay_for = $_REQUEST['pay_for'];
            $order_amount = $_REQUEST['order_amount'];
            $order_currency = $_REQUEST['order_currency'];
            $balance_amount = $_REQUEST['balance_amount'];
            $balance_currency = $_REQUEST['balance_currency'];
            $exchange_rate = $_REQUEST['exchange_rate'];
            $paymentDateTime = $_REQUEST['paymentDateTime'];
            $md5 = $_REQUEST['md5'];
            $md5fb = strtoupper(md5($_REQUEST['type'] . ";" . $pay_for . ";" . $onpay_id . ";" . $order_amount . ";" . $order_currency . ";" . $this->object->mnt_secret_key . ""));
            if ($md5fb != $md5) {
                $rezult = answerpay($_REQUEST['type'], 7, $pay_for, $order_amount, $order_currency, 'Md5 signature is wrong', $onpay_id, $key);
            }
            else {
                $rezult = answerpay($_REQUEST['type'], 0, $pay_for, $order_amount, $order_currency, 'OK', $onpay_id, $key);
                $this->order->setPaymentStatus('accepted');
            }
        }
        $buffer->push($rezult);
        $buffer->end();
    }

}

;
?>
