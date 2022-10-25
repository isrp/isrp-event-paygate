<?php

require_once __DIR__.'/db.php';
require_once __DIR__.'/options.php';
require_once __DIR__.'/shortcodes.php';
require_once __DIR__.'/pelepay.php';

class PayGate {
	private $database;
	private $settings;
	private $shortcodes;
	
	public function __construct($mainfile) {
		$this->database = new PayGateDatabase($mainfile);
		$this->settings = new PayGateSettingsPage($this);
		$this->shortcodes = new PayGateShortcodes($this);
		$this->processor = new PayGatePelepayProcessor($this->settings()->getPelepayAccount());
		
		add_action( 'admin_enqueue_scripts', [ $this, 'custom_wp_admin_style'] );
		add_action( 'parse_request', [ $this, 'handleCallbacks']);
	}
	
	public function handleCallbacks($wpquery) {
		$pagename = $wpquery->query_vars['name'] ?: $wpquery->query_vars['pagename'];
		if (strpos($wpquery->request, 'paygate-handler') !== 0)
			return;
		
		list($path, $query) = explode("?", $_SERVER['REQUEST_URI']);
		@list($nop, $handler, $res, $code) = explode("/",$path);
		
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			switch (@$_REQUEST['action']) {
				case 'pay':
					return $this->createPayment();
				case 'export':
					return $this->settings()->handleExport();
				case 'dragon-verify':
					return $this->shortcodes->dragonVerify();
			}
		}
		
		
		if ($res == 'failure') {
			$rescode = @$_GET['Response'];
			$resmessage = PayGatePelepayConstants::RESPONSE_CODES[$rescode];
			wp_die("חלה שגיאה במהלך התשלום - אנא נסו שנית:\n\n$resmessage [$rescode]");
		}
		
		if ($res == 'success')
			return $this->paymentSuccess($query, $code);
		
		wp_die("PayGate invalid operation!");
	}
	
	public function custom_wp_admin_style($hook) {
		// Load only on my settings page
		if (preg_match('/(?:page_paygate-\w+|toplevel_page_paygate)$/', $hook)) {
			wp_enqueue_style( 'paygate_wp_admin_css', plugins_url('admin-style.css', __FILE__), [], 6 );
			wp_enqueue_style( 'paygate_wp_admin_fa', 'https://use.fontawesome.com/releases/v5.1.0/css/all.css' );
		}
	}
	
	public function database() : PayGateDatabase {
		return $this->database;
	}
	
	public function settings() : PayGateSettingsPage {
		return $this->settings;
	}
	
	/**
	 * Generate a unique ID for each dragon member
	 * @param string $email
	 * @return string|boolean
	 */
	public function getDragonId($email) {
		$res = @file_get_contents("http://api.roleplay.org.il/club/email/$email");
		if (!$res)
			return false;
		return json_decode($res)->token;
	}
	
	/**
	 * Call to verify the unique dragon ID received from a browser
	 * @param string $id
	 * @return boolean
	 */
	public function verifyDragonId($id) {
		$res = @file_get_contents("http://api.roleplay.org.il/club/token/$id");
		if (!$res)
			return false;
		return true;
	}
	
	/**
	 * Retrieve the dragon card for an authenticated dragon memeber
	 * @param string $id unique dragon id code
	 * @return array|boolean
	 */
	public function getDragonCard($id) {
		$res = @file_get_contents("http://api.roleplay.org.il/club/token/$id");
		if (!$res)
			return false;
		return json_decode($res);
	}
	
	private function createPayment() {
		error_log("PayGate: Starting to process payment request: " . print_r($_POST, true));
		$dragon_id = @$_REQUEST['paygate-dragon-id'];
		$tickets = @$_REQUEST['tickets'];
		if (!$tickets)
			wp_die('בקשה לא חוקית - אנא נסה שנית');
		
		// replay the transactions, just to be sure:
		if ($dragon_id) {
			$member = $this->getDragonCard($dragon_id);
			if (!$member) {
				error_log("PayGate: Invalid dragon ID submitted, ignoring");
				$dragon_id = null;
			} else {
				if ($this->database()->checkUsedDragonId($member->member_number)) {
					error_log("PayGate: Dragon ID used before in current event, ignoring");
					$dragon_id = null;
				}
			}
		} else
			$dragon_id = null;
		
		$has_dragon_id = is_null($dragon_id) ? false : true;
		$ticketdata = [];
		$period = $this->database()->getActivePeriod();
		$orderid = bin2hex(openssl_random_pseudo_bytes(4));
		$total = 0;
		foreach ($tickets as $ticketType => $ticketList) {
			foreach ($ticketList as $ticket) {
				list($price, $name) = explode(":", $ticket);
				$dbprice = $this->database()->getCurrentTicketPrice($ticketType, $has_dragon_id);
				if ($price != $dbprice)
					error_log("PayGate: User submitted price $price is different from database: $dbprice, ignoring");
				$ticketdata[] = [ $name, $ticketType, $dbprice, $has_dragon_id ];
				$has_dragon_id = false;
				$total += $dbprice;
			}
		}
		
		$calldata = json_encode([
			'time' => time(),
			'dragon_id' => $dragon_id,
			'order_id' => $orderid,
			'period' => $period->id,
			'tickets' => $ticketdata,
		], JSON_UNESCAPED_UNICODE);
		$_SESSION['paygate_calldata'] = $calldata;
		$transaction_id = md5($calldata . "secret");
		$event = $this->database()->getEvent($this->database()->getActiveEventId());
		print $this->processor->get_form('לאתר התשלומים', $total,
			count($ticketdata) . " כרטיסים ל-" . $event->name, $transaction_id,
			home_url('/paygate-handler/success/'. base64_encode($transaction_id)), home_url('/paygate-handler/failure'));
		?>
		<script>
		document.forms[0].getElementsByTagName('button')[0].disabled = true;
		document.forms['pelepayform'].submit();
		</script>
		<?php
		exit();
	}
	
	private function paymentSuccess($query, $code) {
		// http://172.17.0.4/paygate-handler/success/OjoxOjE6MjUwOjE1Mjg0NjQwNjE6NDQ1NmNiNWY?
		//   Response=000&ConfirmationCode=0656742&index=T478514&amount=250.00&firstname=עודד&lastname=ארבל&
		//   email=oded@geek.co.il&phone=054-7340014&payfor=כרטיס לליברה 5: יחיד - רישום מוקדם&custom=&orderid=paygate:dae321616c1af325fae085fb4b68ab03
		$result = wp_parse_args($query);
		$resmessage = PayGatePelepayConstants::RESPONSE_CODES[$result['Response']];
		
		if ($result['Response'] != '000') {
			error_log("PayGate: PayGate transaction failed: ". print_r($result, true));
			wp_die("חלה שגיאה בעיבוד התשלום - $resmessage. אנא פנו למנהל האתר");
		}
		
		@list($prefix, $transaction_id) = explode(':',$result['orderid']);
		$calldata = $_SESSION['paygate_calldata'];
		if ($transaction_id != md5($calldata . "secret")) {
			wp_die("אישור תשלום לא חוקי!");
		}
		
		if (!$this->settings()->allowTestTransaction() and $result['index'][0] == 'T') {
			wp_die("אין אפשרות לרכוש כרטיסים עם חשבון בדיקה!");
		}
		
		$tickets = json_decode($calldata, true);
		$dragoncard = $this->getDragonCard($tickets['dragon_id']);
		
		$payer = $result['email'];
		$payerName = urldecode($result['firstname'] . ' ' . $result['lastname']);
		$event = $this->database()->getEvent($this->database()->getActiveEventId());
		ob_start();
		?>
		<div dir="rtl">
		<h1>אישור תשלום עבור כרטיסים ל<?php echo $event->name?></h1>
		<h2>פרטים:</h2>
		<table>
		<tr><td>שם המשלם:</td><td><?php echo $payerName?></td></tr>
		<tr><td>דואר אלקטרוני:<td><td><?php echo $result['email']?></td></tr>
		<tr><td>מס טלפון לאישור:<td><td><?php echo $result['phone']?></td></tr>
		<tr><td>קוד אישור הזמנה:</td><td><?php echo $result['ConfirmationCode']?></td></tr>
		</table>
		
		<h2>כרטיסים:</h2>
		<table style="width: 80%; border-collapse: collapse; border: solid gray 1px;">
		<thead>
		<tr>
			<th style="border: solid gray 1px;">שם</th>
			<th style="border: solid gray 1px;">סוג הכרטיס</th>
			<th style="border: solid gray 1px;">מחיר</th>
			<th style="border: solid gray 1px;">קוד</th></tr>
		</thead>
		<tbody>
		<?php
		
		foreach ($tickets['tickets'] as $ticket) {
			$name = urldecode($ticket[0]);
			if (empty($name))
				$name = $payerName;
			$orderid = $tickets['order_id'] . ':' . bin2hex(openssl_random_pseudo_bytes(2));
			$this->database()->storeRegistration($name, $ticket[1], $tickets['period'],
				$ticket[2], $tickets['time'], $orderid, $ticket[3] ? $dragoncard->member_number : null,
				json_encode($result, JSON_UNESCAPED_UNICODE));
			?>
			<tr>
			<td><?php echo $name?></td>
			<td><?php echo $ticket[1]?> <?php if ($ticket[3]):?> (הנחת מועדון דרקון) <?php endif;?></td>
			<td style="text-align:center;">₪<?php echo $ticket[2]?></td>
			<td style="text-align:center;"><?php echo $orderid?></td>
			</tr>
			<?php
		}
		?>
		</tbody>
		<tbody>
		<tr>
		<th style="border: solid gray 1px;">סה"כ:</th>
		<td style="border: solid gray 1px;" colspan="3">₪<?php echo $result['amount']?></td>
		</tbody>
		</table>
		</div>
		<?php
		
		// send confirmation email
		add_filter( 'wp_mail_content_type', [ $this, 'wpdocs_set_html_mail_content_type' ]);
		wp_mail($payer, 'אישור הזמנה של כרטיסים ל'.$event->name, ob_get_clean());
		remove_filter( 'wp_mail_content_type', [ $this, 'wpdocs_set_html_mail_content_type' ]);
		
		$successpage = '/' . $this->database()->getSuccessLandingPage($tickets['period']);
		header('Location: ' . $successpage);
		error_log("PayGate: Send redirect - Location: $successpage");
		exit();
	}
	
	public function wpdocs_set_html_mail_content_type() {
		return 'text/html';
	}
	
}
