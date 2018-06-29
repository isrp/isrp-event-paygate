<?php
require_once __DIR__.'/pelepay-constants.php';

class PayGatePelepayProcessor {
	
	private $account;
	
	public function __construct($account) {
		$this->account = $account;
	}
	
	public function get_form($buttontext, $amount, $description, $order, $okurl, $failurl) {
		ob_start();
		?>
		<form name="pelepayform" action="https://www.pelepay.co.il/pay/paypage.aspx" method="post">
		<input type="hidden" name="business" value="<?php echo $this->account;?>">
		<INPUT type="hidden" name="amount" value="<?php echo $amount;?>">
		<INPUT type="hidden" name="description" value="<?php echo $description?>">
		<INPUT type="hidden" name="orderid" value="paygate:<?php echo $order?>">
		<input type="hidden" name="success_return" value="<?php echo $okurl?>">
		<input type="hidden" name="notify_url" value="<?php echo $okurl?>">
		<input type="hidden" name="fail_return" value="<?php echo $failurl?>">
		<input type="hidden" name="cancel_return" value="<?php echo $failurl?>">
		<input type="hidden" name="active_notify_url" value="1">
		
		<!--  input type="image" src="http://www.pelepay.co.il/btn_images/pay_button_1.gif" name="submit" alt="Make payments with pelepay" -->
		<button class="paygate" type="submit"><?php echo $buttontext?></button>
		</form>
		<?php
		return ob_get_clean();
		
	}
	
	public function parse($result) {
		// expecting a Pelepay result like this:
		// Response=000&ConfirmationCode=4476850&index=T470001&amount=50.00&firstname=%d7%a2%d7%95%d7%93%d7%93&lastname=%d7%90%d7%a8%d7%91%d7%9c&email=oded@geek.co.il&phone=054-7340014&payfor=test1&custom=&orderid=1
		$resmessage = PayGatePelepayConstants::RESPONSE_CODES[$result['Response']];
		$result['message'] = $resmessage;
		list($orderCode, $cpgid) = explode(':', $result['orderid']);
		if ($result['Response'] == '000' && $orderCode == 'paygate') {
			error_log("PayGate: transaction successful: ". print_r($result, true));
			return [
				'amount' => $result['amount'],
				'campaign' => $cpgid,
			];
		}
		
		error_log("PayGate: transaction failed: ". print_r($result, true));
		return false;
	}
	
}