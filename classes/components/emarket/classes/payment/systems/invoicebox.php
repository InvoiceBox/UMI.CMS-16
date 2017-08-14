<?php

class invoiceboxPayment extends payment {

    public function validate() {
        return true;
    }

    public static function getOrderId() {
        return (int) getRequest('participantOrderId');
    }

    public function process($template = null) {
        $this->order->order();
        $items = $this->order->getItems();
        $currency = strtoupper(mainConfiguration::getInstance()->get('system', 'default-currency'));
        $out_amount = number_format($this->order->getActualPrice(), 2, '.', '');
        $order_id = $this->order->getId();
        $signatureValue = md5(
                $this->object->invoicebox_participant_id .
                $order_id .
                $out_amount .
                $currency .
                $this->object->invoicebox_api_key
        );
        $customerId = $this->order->getCustomerId();
        $customerSource = selector::get('object')
                ->id($customerId);

        if (!$customerSource instanceof iUmiObject) {
            throw new publicException(getLabel('error-payment-wrong-customer-id'));
        }

        $customer = new customer($customerSource);
        $email = $customer->getEmail();
        $fio = $customer->getFullName();
        $tel = $customer->getPhone();
        if (!is_string($email) || empty($email) || !umiMail::checkEmail($email)) {
            throw new publicException(getLabel('error-payment-wrong-customer-email'));
        }
        $itemNo = 0;
        $basketItemhtml = '';
        $order_quantity = 0;
        foreach ($items as $basketItem) {
            $itemNo++;
            $order_quantity += $basketItem->getAmount();
            $measure = "шт.";
            $basketItemhtml .= '<input type="hidden" name="itransfer_item' . $itemNo . '_name" value="' . $basketItem->getName() . '" />' . "\n";
            $basketItemhtml .= '<input type="hidden" name="itransfer_item' . $itemNo . '_quantity" value="' . $basketItem->getAmount() . '" />' . "\n";
            $basketItemhtml .= '<input type="hidden" name="itransfer_item' . $itemNo . '_measure" value="' . $measure . '" />' . "\n";
            $basketItemhtml .= '<input type="hidden" name="itransfer_item' . $itemNo . '_price" value="' . number_format($basketItem->getTotalActualPrice(), 2, '.', '') . '" />' . "\n";
        }

        $this->order->setPaymentStatus('initialized');

        $html = '<form action="https://go.invoicebox.ru/module_inbox_auto.u" method="post" target="_blank" name="invoicebox_form">
            <input type="hidden" name="itransfer_participant_id" value="' . htmlspecialchars($this->object->invoicebox_participant_id) . '" />
            <input type="hidden" name="itransfer_participant_ident" value="' . htmlspecialchars($this->object->invoicebox_participant_ident) . '" />
			<input type="hidden" name="itransfer_testmode" value="' . htmlspecialchars($this->object->invoicebox_testmode) . '" />
            <input type="hidden" name="itransfer_participant_sign" value="' . $signatureValue . '" />
            <input type="hidden" name="itransfer_cms_name" value="UMI" />
            <input type="hidden" name="itransfer_order_id" value="' . htmlspecialchars($order_id) . '" />
            <input type="hidden" name="itransfer_order_amount" value="' . htmlspecialchars($out_amount) . '" />
            <input type="hidden" name="itransfer_order_quantity" value="' . htmlspecialchars($order_quantity) . '" />
            <input type="hidden" name="itransfer_order_currency_ident" value="' . htmlspecialchars($currency) . '" />
            <input type="hidden" name="itransfer_order_description" value="<' . htmlspecialchars('Оплата заказа ' . $this->order->getNumber() . ' Сумма к оплате ' . $out_amount . ' ' . $currency) . '" />
            <input type="hidden" name="itransfer_body_type" value="PRIVATE" />
            <input type="hidden" name="itransfer_person_name" value="' . htmlspecialchars($fio) . '" />
            <input type="hidden" name="itransfer_person_email" value="' . htmlspecialchars($email) . '" />
            <input type="hidden" name="itransfer_person_phone" value="' . htmlspecialchars($tel) . '" />
            <input type="hidden" name="itransfer_url_return" value="' . htmlspecialchars('http://' . $_SERVER['SERVER_NAME'] . '/emarket/purchase/result/successful/') . '" />
            <input type="hidden" name="itransfer_url_cancel" value="' . htmlspecialchars('http://' . $_SERVER['SERVER_NAME'] . '/emarket/purchase/result/failed/') . '" />
            <input type="hidden" name="itransfer_url_notify" value="' . htmlspecialchars('http://' . $_SERVER['SERVER_NAME'] . '/emarket/gateway/') . '" />'
                . $basketItemhtml .
                '<font class="tablebodytext">
            Вы хотите оплатить через систему <b>ИнвойсБокс</b><br>
            Сумма к оплате: <b>' . $out_amount . '</b>
            <p>
                <input type="hidden" name="FinalStep" value="1">
                <input type="submit" name="Submit" value="Оплатить">
            </p>
            </font>
        </form>';
        $param = array();
        $param['formAction'] = $html;
        list($templateString) = def_module::loadTemplates("emarket/payment/invoicebox/default.tpl", "form_block");
        return def_module::parseTemplate($templateString, $param);
    }

    public function poll() {
	
	    $participantId = IntVal($_REQUEST["participantId"]);
        $participantOrderId = trim($_REQUEST["participantOrderId"]);
        $participant_apikey = $this->object->invoicebox_api_key;
        $ucode = trim($_REQUEST["ucode"]);
        $timetype = trim($_REQUEST["timetype"]);
        $time = str_replace(' ', '+', trim($_REQUEST["time"]));
        $amount = trim($_REQUEST["amount"]);
        $currency = trim($_REQUEST["currency"]);
        $agentName = trim($_REQUEST["agentName"]);
        $agentPointName = trim($_REQUEST["agentPointName"]);
        $testMode = trim($_REQUEST["testMode"]);
        $sign = trim($_REQUEST["sign"]);
        $currency = strtoupper(mainConfiguration::getInstance()->get('system', 'default-currency'));
        $out_amount = number_format($this->order->getActualPrice(), 2, '.', '');

        $sign_strC =
                $participantId .
                $participantOrderId .
                $ucode .
                $timetype .
                $time .
                $amount .
                $currency.
                $agentName .
                $agentPointName .
                $testMode.
                $participant_apikey;
				
        $buffer = outputBuffer::current();
        $buffer->clear();
        $buffer->contentType("text/plain");
		$sign_strC =md5($sign_strC);

        if ($sign != $sign_strC) {
            $buffer->push("failed");
            $buffer->end();
        }

        $checkAmount = (float) $this->order->getActualPrice();

        if (($amount - $checkAmount) == 0) {
            $this->order->setPaymentStatus('accepted');
            $buffer->push("OK");
        } else {
            
            $buffer->push("failed");
        }
		$this->order->commit();
        $buffer->end();
    }

}

?>