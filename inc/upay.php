<?php

class PayGateUPayProcessor {
	
	private $account;
	private $testmode;
	
	public function __construct($account, $testmode) {
		$this->account = $account;
		$this->testmode = $testmode;
	}
	
	public function get_form($buttontext, $amount, $description, $order, $okurl, $failurl, $cancelurl) {
		$paymentURL = 'https://app.upay.co.il/API6/clientsecure/redirectpage.php';
		ob_start();
		?>
		
		<form name='paymentform' action='<?php echo $paymentURL?>' method='post'>
		<input type='hidden' value='<?php echo $this->account;?>' name='email'>
		<INPUT TYPE='hidden' value='<?php echo $amount;?>' NAME='amount'>
		<INPUT TYPE='hidden' value='<?php echo $okurl?>' NAME='returnurl'>
		<INPUT TYPE='hidden' value='<?php echo $okurl?>' NAME='ipnurl'>
		<INPUT TYPE='hidden' value='<?php echo $description?>' NAME='paymentdetails'>
		<INPUT TYPE='hidden' value='1' NAME='maxpayments'>
		<INPUT TYPE='hidden' value='<?php echo $this->testmode ? '0' : '1'?>' NAME='livesystem'>
		<INPUT TYPE='hidden' value='' NAME='commissionreduction'>
		<INPUT TYPE='hidden' value='1' NAME='createinvoiceandreceipt'>
		<INPUT TYPE='hidden' value='0' NAME='createinvoice'>
		<INPUT TYPE='hidden' value='0' NAME='createreceipt'>
		<INPUT TYPE='hidden' value='UPAY' NAME='refername'>
		<INPUT TYPE='hidden' value='HE' NAME='lang'>
		<INPUT TYPE='hidden' value='NIS' NAME='currency'>
		<!-- input type='image' src='https://app.upay.co.il/BANKRESOURCES/UPAY/images/buttons/payment-button1HE.png' name='submit' alt='Make payments with upay' -->
		<button class="paygate" type="submit"><?php echo $buttontext?></button>
		</form>
		
		<?php
		return ob_get_clean();
	}
	
	public function parse($query) {
		$result = wp_parse_args($query);
		// error: user does not exist
		// ?errormessage=USER_NOT_EXISTS&errordescription=חשבון לא קיים
		if ($result['errormessage']) {
			wp_die(sprintf(
				esc_html__('Error processing payment - "%1$s". Please contact the site administrator' /*translators: error text*/,'isrp-event-paygate'),
						$result['errordescription'] . ' (' . $result['errormessage'] . ')'));
		}
		
		return false;
		
		// expecting a Pelepay result like this:
		// Response=000&ConfirmationCode=4476850&index=T470001&amount=50.00&firstname=%d7%a2%d7%95%d7%93%d7%93&lastname=%d7%90%d7%a8%d7%91%d7%9c&email=oded@geek.co.il&phone=054-7340014&payfor=test1&custom=&orderid=1
		$resmessage = PayGatePelepayConstants::RESPONSE_CODES[$result['Response']] ?: 'Unknown error';
		$result['message'] = $resmessage;
		
		if ($result['Response'] != '000') {
			error_log("PayGate: Pelepay transaction failed: ". print_r($result, true));
			wp_die(sprintf(
				esc_html__('Error processing payment - "%1$s". Please contact the site administrator' /*translators: error text*/,'isrp-event-paygate'),
						   $resmessage));
		}
		
		@list($prefix, $transaction_id) = explode(':',$result['orderid']);
		
		if ($result['Response'] == '000' && $orderCode == 'paygate') {
			error_log("PayGate: transaction successful: ". print_r($result, true));
			return [
				'amount' => $result['amount'],
				'transaction' => $transaction_id,
				'testmode' => $result['index'][0] == 'T',
				'email' => $result['email'],
				'name' => urldecode($result['firstname'] . ' ' . $result['lastname']),
				'phone' => $result['phone'],
				'confirmation' => $result['ConfirmationCode'],
			];
		}
		
		error_log("PayGate: Pelepay transaction failed: ". print_r($result, true));
		return false;
		
	}
	
}
