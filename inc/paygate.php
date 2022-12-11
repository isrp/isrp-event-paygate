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
		
		add_action( 'admin_enqueue_scripts', [ $this, 'custom_wp_admin_style' ]);
		add_action( 'plugins_loaded', [ $this, 'initTextDomain' ]);
		add_action( 'parse_request', [ $this, 'handleCallbacks' ]);
		add_action( 'init', [ $this, 'startSession' ]);
	}
	
	public function initTextDomain() {
		$res = load_plugin_textdomain('isrp-event-paygate', false, ISRP_EVENT_PAYGATE_DIR . '/languages');
	}
	
	public function startSession() {
		if (!session_id())
			session_start();
	}
	
	public function handleCallbacks($wpquery) {
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
				case 'club-verify':
					return $this->shortcodes->clubVerify();
			}
		}
		
		
		if ($res == 'failure') {
			$rescode = @$_GET['Response'];
			$resmessage = PayGatePelepayConstants::RESPONSE_CODES[$rescode];
			wp_die(sprintf(esc_html__(
				'An error occured while processing the payment - try again and if it reoccures, let the site manager know that you got this error:\n\n[%2$s] %2$s',
				'isrp-event-paygate'), $rescode, $resmessage));
		}
		
		if ($res == 'success')
			return $this->paymentSuccess($query, $code);
		
		wp_die(sprintf(esc_html__('PayGate invalid operation!', 'isrp-event-paygate'), $var));
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

	private function clubURL($action) {
		if (empty($action))
			$action = "";
		else if ($action[0] != '/')
			$action = '/'.$action;
		$url = $this->settings()->clubMembershipAPI() . $action;
		return preg_replace('//', '/', $url);
	}
	
	/**
	 * Generate a unique ID for each club member
	 * @param string $email
	 * @return string|boolean
	 */
	public function getClubId($email) {
		$res = @file_get_contents($this->clubURL("/email/$email"));
		if (!$res)
			return false;
		return json_decode($res)->token;
	}
	
	/**
	 * Call to verify the unique club ID received from a browser
	 * @param string $id
	 * @return boolean
	 */
	public function verifyClubId($id) {
		$res = @file_get_contents($this->clubURL("/token/$id"));
		if (!$res)
			return false;
		return true;
	}
	
	/**
	 * Retrieve the club card for an authenticated club memeber
	 * @param string $id unique club id code
	 * @return array|boolean
	 */
	public function getClubCard($id) {
		$res = @file_get_contents($this->clubURL("/token/$id"));
		if (!$res)
			return false;
		return json_decode($res);
	}
	
	private function createPayment() {
		error_log("PayGate: Starting to process payment request: " . print_r($_POST, true));
		$club_id = @$_REQUEST['paygate-club-id'];
		$tickets = @$_REQUEST['tickets'];
		if (!$tickets)
			wp_die(esc_html__('Invalid request, please try again', 'isrp-event-paygate'));
		
		// replay the transactions, just to be sure:
		if ($club_id) {
			$member = $this->getClubCard($club_id);
			if (!$member) {
				error_log("PayGate: Invalid club ID submitted, ignoring");
				$club_id = null;
			} else {
				if ($this->database()->checkUsedClubId($member->member_number)) {
					error_log("PayGate: Club ID used before in current event, ignoring");
					$club_id = null;
				}
			}
		} else
			$club_id = null;
		
		$has_club_id = is_null($club_id) ? false : true;
		$ticketdata = [];
		$period = $this->database()->getActivePeriod();
		$event = $this->database()->getEvent($period->event_id);
		$orderid = bin2hex(openssl_random_pseudo_bytes(4));
		$total = 0;
		foreach ($tickets as $ticketType => $ticketList) {
			foreach ($ticketList as $ticket) {
				list($price, $name) = explode(":", $ticket);
				$dbprice = $this->database()->getCurrentTicketPrice($ticketType, $has_club_id);
				if ($price != $dbprice)
					error_log("PayGate: User submitted price $price is different from database: $dbprice, ignoring");
				$ticketdata[] = [ $name, $ticketType, $dbprice, $has_club_id ];
				$has_club_id = false;
				$total += $dbprice;
			}
		}
		
		if ($event->max_tickets > 0 && $event->sold  + count($ticketdata) > $event->max_tickets) {
				wp_die(sprintf(esc_html__('Only %1$s tickets left, but you tried to purchase %2$s tickets. Please try again.', 'isrp-event-paygate'), $event->max_tickets - $event->sold, count($ticketdata)));
		}
		$calldata = json_encode([
			'time' => time(),
			'club_id' => $club_id,
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
		$resmessage = PayGatePelepayConstants::RESPONSE_CODES[$result['Response']] ?: 'Unknown error';
		
		if ($result['Response'] != '000') {
			error_log("PayGate: PayGate transaction failed: ". print_r($result, true));
			wp_die(sprintf(
				esc_html__('Error processing payment - "%1$s". Please contact the site administrator','isrp-event-paygate'),
				$resmessage));
		}
		
		@list($prefix, $transaction_id) = explode(':',$result['orderid']);
		$calldata = $_SESSION['paygate_calldata'];
		if ($transaction_id != md5($calldata . "secret")) {
			error_log("Transaction id verification failed: " . print_r($calldata, true));
			wp_die(esc_html__('Invalid payment confirmation!', 'isrp-event-paygate'));
		}
		
		if (!$this->settings()->allowTestTransaction() and $result['index'][0] == 'T') {
			wp_die(esc_html__('A payment test account is not valid on this site!', 'isrp-event-paygate'));
		}
		
		$tickets = json_decode($calldata, true);
		$clubcard = $this->getClubCard($tickets['club_id']);
		
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
				$ticket[2], $tickets['time'], $orderid, $ticket[3] ? $clubcard->member_number : null,
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
