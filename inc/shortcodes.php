<?php

class PayGateShortcodes {
	private $pg;
	private $table_name;
	private $pelepay_account;
	private $processor;
	private $settings;
	private $currentEvent;
	private $prices = [];
	private $currentTicketType = null;
	private $currentDragonId = null;
	private $currentDragonIdWasUsed = false;
	
	public function __construct(PayGate $paygate) {
		$this->pg = $paygate;
		$this->pelepay_account = get_option('paygate-pelepay-account');
		$this->processor = new PayGatePelepayProcessor($this->pelepay_account);
		$this->currentEvent = $this->pg->database()->getActiveEventId();
		foreach ($this->pg->database()->listEventCurrentPrices($this->currentEvent) as $ticket) {
			$this->prices[$ticket->ticket_type] = [ $ticket->full_price, $ticket->dragon_price ];
		}
		
		add_shortcode('paygate-checkout', [ $this, 'payCheckout' ]);
		add_shortcode('paygate-name', [ $this, 'nameField' ]);
		add_shortcode('paygate-button', [ $this, 'payButton' ]);
		add_shortcode('paygate-price', [ $this, 'showPrice' ]);
		add_shortcode('paygate-dragon-form', [ $this, 'dragonForm']);
	}
		
	public function payCheckout($atts, $content = null) {
		wp_enqueue_script('paygate-shortcodes', ISRP_EVENT_PAYGATE_URL . '/scripts/shortcode-scripts.js', [ 'jquery' ], null, true);
		$atts = shortcode_atts([
		], $atts, 'paygate-checkout');
		
		$this->verifyDragonCode();
		$jsAllowCart = $this->pg->settings()->allowMultipleTickets() ? 'true' : 'false';
		
		ob_start();
		?>
		<script>
		jQuery(document).ready(function() {
			window.PayGateCheckout = new EventPayGate(<?php echo $jsAllowCart?>);
		});
		</script>
		<div class="paygate-tickets">
		<form method="post" action="/paygate-handler" id="paygate-form">
		<input type="hidden" name="action" value="pay">
		<input type="hidden" name="paygate-dragon-id" value="<?php echo $this->currentDragonId?>">
		<table id="paygate-cart" style="display: <?php
				if ($this->pg->settings()->allowMultipleTickets()):?>auto<?php else:?>none<?php endif;?>">
		<thead>
			<tr>
			<th>סוג כרטיס</th><th>מחיר</th><th>שם</th>
			</tr>
		</thead>
		<tbody>
		</tbody>
		<tbody class="total">
			<tr>
			<th>סה"כ:</th>
			<th>₪<span id="paygate-total">0</span></th>
			<th><button id="paygate-checkout" type="button" onclick="this.form.submit()">לתשלום</button></th>
			</tr>
		</tbody>
		
		</table>
		</form>
		</div>
		<?php
		return ob_get_clean();
	}
	
	public function nameField($atts) {
		$atts = shortcode_atts([
			'width' => '',
			'value' => '',
			'style' => '',
		], $atts, 'paygate-name');
		ob_start();
		
		$this->verifyDragonCode();
		if (empty($atts['value']) and $this->currentDragonId) {
			$member = $this->pg->getDragonCard($this->currentDragonId);
			$atts['value'] = $member->firstname . ' ' . $member->lastname;
		}
			
		?>
		<input type="text" name="paygate-ticket-name" id="paygate-ticket-name"
			width="<?php echo $atts['width']; ?>"
			value="<?php echo $atts['value']; ?>"
			style="<?php echo $atts['style']; ?>" >
		<?php
		return ob_get_clean();
	}
	
	public function showPrice($atts) {
		$atts = shortcode_atts([
			'type' => ''
		], $atts, 'paygate-price');
		$ticketType = $atts['type'] ?: $this->currentTicketType;
		if (empty($ticketType)) {
			if (count($this->prices) == 1)
				$ticketType = key($this->prices);
			else
				return 'TYPE ERROR';
		}
		if (!isset($this->prices[$ticketType]))
			return 'Invalid type: '.$ticketType;
		
		ob_start();
		$fieldid = bin2hex(openssl_random_pseudo_bytes(8));
		?>
		<span id="<?php echo $fieldid ?>"></span>
		<script>
		window.paygate_price_handlers  = window.paygate_price_handlers || {};
		window.paygate_price_handlers['<?php echo $ticketType?>'] = window.paygate_price_handlers['<?php echo $ticketType?>'] || [];
		window.paygate_price_handlers['<?php echo $ticketType?>'].push(function(price) {
			document.getElementById('<?php echo $fieldid?>').innerHTML = price;
		});
		</script>
		<?php
		return ob_get_clean();
	}
	
	public function payButton($atts, $content = null) {
		$atts = shortcode_atts([
			'type' => ''
		], $atts, 'paygate-button');
		$this->verifyDragonCode();
		
		$ticketType = $atts['type'];
		if (empty($ticketType)) {
			if (count($this->prices) == 1)
				$ticketType = key($this->prices);
			else
				return 'TYPE ERROR';
		}
		if (!isset($this->prices[$ticketType]))
			return 'Invalid type: '.$ticketType;

		$this->currentTicketType = $ticketType;
		ob_start();
		?>
		<button type="button" onclick="PayGateCheckout.addTicket('<?php echo $this->currentTicketType?>')">
		<?php echo do_shortcode(trim($content)) ?>
		</button>
		<script>
		window.paygate_ticket_types = window.paygate_ticket_types || {};
		window.paygate_ticket_types['<?php echo $this->currentTicketType?>'] = [
			'<?php echo $this->getTicketPrice(true, $this->currentTicketType);?>',
			'<?php echo $this->getTicketPrice(false, $this->currentTicketType);?>'
		];
		</script>
		<?php
		$this->currentTicketType = null;
		return ob_get_clean();
	}
	
	private function getTicketPrice($forDragon, $ticketType) {
		$isDragon = !is_null($this->currentDragonId) && !($this->currentDragonIdWasUsed);
		if ($isDragon)
			error_log("PayGate: Calculating price for dragon ticket");
		return $this->prices[$ticketType][$isDragon ? 1 : 0];
	}
	
	private function verifyDragonCode() {
		if ($this->currentDragonId)
			return;
		$dragonid = $_REQUEST['dragon-id'];
		if (!empty($dragonid)) {
			if (!$this->pg->verifyDragonId($dragonid)) {
				print 'כרטיס דרקון לא חוקי!';
				return;
			}
			
			$this->currentDragonId = $dragonid;
			$mid = $this->pg->getDragonCard($dragonid)->member_number;
			if ($this->pg->database()->checkUsedDragonId($mid)) {
				$this->currentDragonIdWasUsed = true;
				ob_start()
				?>
				<div class="paygate-no-dragon">
				<h3>כרטיס דרקון זכאי להנחה בקנית כרטיס אחד בלבד</h3>
				<form method="get" action="">
				<button type="submit">לחץ כאן לחזור לטופס הרכישה</button>
				</form>
				</div>
				<?php
				print ob_get_clean();
			}
		}
	}
	
	public function dragonForm($atts, $content = null) {
		$atts = shortcode_atts([
			'success' => '',
			'width' => '',
			'style' => '',
		], $atts, 'paygate-dragon-form');
		
		// if there is already a dragon code, don't show
		if (@$_REQUEST['dragon-id'])
			return '';
		
		if (empty($atts['success']))
			$atts['success'] = $_SERVER['HTTP_REFERER'];
		
		ob_start();
		?>
		<div class="paygate-dragon-club">
		<form method="post" action="/paygate-handler">
		<input type="hidden" name="action" value="dragon-verify">
		<input type="hidden" name="success-page" value="<?php echo home_url($atts['success'])?>">
		<?php echo do_shortcode($content); ?>
		<input type="text" name="dragon-email"
			width="<?php echo $atts['width']; ?>"
			value="<?php echo $atts['value']; ?>"
			style="<?php echo $atts['style']; ?>">
		<button type="submit">שליחה</button>
		</form>
		</div>
		<?php
		return ob_get_clean();
	}
	
	public function dragonVerify() {
		$email = @$_REQUEST['dragon-email'];
		$success = @$_REQUEST['success-page'];
		$id = $this->pg->getDragonId($email);
		if ($id !== false) {
			header('Location: ' . $success . '?dragon-id=' . $id);
			exit();
		}
		print "כתובת דואר לא מוכרת, אנא נסה שנית";
		exit();
	}
}
