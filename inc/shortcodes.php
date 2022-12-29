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
	private $currentClubId = null;
	private $currentClubIdWasUsed = false;
	private $availableTickets = -1;
	
	public function __construct(PayGate $paygate) {
		$this->pg = $paygate;
		$this->pelepay_account = get_option('paygate-pelepay-account');
		$this->processor = new PayGatePelepayProcessor($this->pelepay_account);
		$this->currentEvent = $this->pg->database()->getActiveEventId();
		$evData = $this->pg->database()->getEvent($this->currentEvent);
		$this->availableTickets = $evData->max_tickets > 0 ? max(0, $evData->max_tickets - $evData->sold) : -1;
		foreach ($this->pg->database()->listEventCurrentPrices($this->currentEvent) as $ticket) {
			$this->prices[$ticket->ticket_type] = [ $ticket->full_price, $ticket->club_price ];
		}
		
		add_shortcode('paygate-checkout', [ $this, 'payCheckout' ]);
		add_shortcode('paygate-name', [ $this, 'nameField' ]);
		add_shortcode('paygate-button', [ $this, 'payButton' ]);
		add_shortcode('paygate-submit', [ $this, 'payButton' ]);
		add_shortcode('paygate-price', [ $this, 'showPrice' ]);
		add_shortcode('paygate-club-form', [ $this, 'clubForm']);
		add_shortcode('paygate-dragon-form', [ $this, 'clubForm']);
		add_shortcode('paygate-input', [ $this, 'customField' ]);
		add_shortcode('paygate-select', [ $this, 'customField' ]);
	}
		
	public function payCheckout($atts, $content = null) {
		wp_enqueue_script('paygate-shortcodes', ISRP_EVENT_PAYGATE_URL . '/scripts/shortcode-scripts.js', [ 'jquery' ], null, true);
		$atts = shortcode_atts([
		], $atts, 'paygate-checkout');
		
		$this->verifyClubCode();
		$jsAllowCart = $this->pg->settings()->allowMultipleTickets() ? 'true' : 'false';
		
		ob_start();
		if ($this->availableTickets == 0) {
			?>
			<h2><?php _e('No More Tickets Available', 'isrp-event-paygate')?></h2>
			<p>
			<?php _e('Unfortunately, the event is now full and no more tickets are available for purchase.', 'isrp-event-paygate')?>
			</p>
			<?php
			return ob_get_clean();
		}
		
		?>
		<script>
		jQuery(document).ready(function() {
			window.PayGateCheckout = new EventPayGate(<?php echo $jsAllowCart?>, <?php echo $this->availableTickets?>,
											 '<?php _e('Sold out', 'isrp-event-paygate')?>');
		});
		</script>
		<div class="paygate-tickets">
		<form method="post" action="/?paygate-handler" id="paygate-form">
		<input type="hidden" name="action" value="pay">
		<input type="hidden" name="paygate-club-id" value="<?php echo $this->currentClubId?>">
		<table id="paygate-cart" style="display: <?php
				if ($this->pg->settings()->allowMultipleTickets()):?>auto<?php else:?>none<?php endif;?>">
		<thead>
			<tr>
			<th><?php _e('Ticket Type', 'isrp-event-paygate')?></th>
			<th><?php _e('Price', 'isrp-event-paygate')?></th>
			<th><?php _e('Name', 'isrp-event-paygate')?></th>
			</tr>
		</thead>
		<tbody>
		</tbody>
		<tbody class="total">
			<tr>
			<th><?php _e('Total:', 'isrp-event-paygate')?></th>
			<th><?php _e('¤', 'isrp-event-paygate')?><span id="paygate-total">0</span></th>
			<th><button id="paygate-checkout" type="button" onclick="this.form.submit()"><?php _e('Pay','isrp-event-paygate')?></button></th>
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
		
		$this->verifyClubCode();
		if (empty($atts['value']) and $this->currentClubId) {
			$member = $this->pg->getClubCard($this->currentClubId);
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

	public function customField($atts = [], $content = null, $tag = '') {
		$atts = shortcode_atts([
			'type' => $tag === 'paygate-select' ? 'select' : 'text',
			'name' => 'data',
			'width' => '',
			'value' => '',
			'style' => 'vertical-align: top;',
			'cols' => 40,
			'rows' => 10,
		], $atts, 'paygate-input');
		ob_start();

		switch ($atts['type']) {
			case 'select':
				$items = explode(';', trim($content));
				?>
				<select name="paygate-field-<?php echo $atts['name']?>"
					<?php if ($atts['width']):?> width="<?php echo $atts['width']; ?>" <?php endif ?>
					<?php if ($atts['style']):?> style="<?php echo $atts['style']; ?>" <?php endif ?> >
					<?php if (!$value) echo '<option></option>' ?>
					<?php foreach ($items as $item) {
						$item = trim($item);
						$selected = $item == trim($value) ? 'selected="selected"' : '';
						echo '<option value="'.$item.'" '.$selected.'>'.$item.'</option>';
					} ?>
				</select>
				<?php
				break;
			case 'textarea':
			case 'textbox':
				?>
				<textarea name="paygate-field-<?php echo $atts['name']?>" cols="<?php echo $atts['cols']?>" rows="<?php echo $atts['rows']?>"
					<?php if ($atts['width']):?> width="<?php echo $atts['width']; ?>" <?php endif ?>
					<?php if ($atts['style']):?> style="<?php echo $atts['style']; ?>" <?php endif ?>
					><?php echo $value?></textarea>
				<?php
				break;
			default:
			?>
			<input type="<?php echo $atts['type']?>" name="paygate-field-<?php echo $atts['name']?>"
				<?php if ($atts['width']):?> width="<?php echo $atts['width']; ?>" <?php endif ?>
				<?php if ($atts['style']):?> style="<?php echo $atts['style']; ?>" <?php endif ?>
				<?php if ($atts['type'] == 'checkbox'):?> value="1" checked="<?php echo $atts['value'] ? "checked" : ""?>"
				<?php else:?> value="<?php echo $atts['value']; ?>"
				<?php endif?> >
			<?php
			break;
		}

		return ob_get_clean();
	}
	
	public function showPrice($atts) {
		$atts = shortcode_atts([
			'type' => ''
		], $atts, 'paygate-price');
		$this->verifyClubCode();
		$ticketType = $atts['type'] ?: $this->currentTicketType;
		if (empty($ticketType)) {
			if (count($this->prices) == 1)
				$ticketType = key($this->prices);
			else
				return 'TYPE ERROR: available types: ' . join("; ", array_keys($this->prices));
		}
		if (!isset($this->prices[$ticketType]))
			return 'Invalid type: '.$ticketType;
		
		ob_start();
		$fieldid = bin2hex(openssl_random_pseudo_bytes(8));
		if ($this->pg->settings()->allowMultipleTickets()) {
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
		} else {
		?>
		<span id="<?php echo $fieldid ?>"><!-- static price for <?php $ticketType ?> -->
			<?php echo $this->getTicketPrice((boolean)$this->currentClubId, $ticketType);?>
		</span>
		<?php
		}
		return ob_get_clean();
	}
	
	public function payButton($atts, $content = null, $tag = '') {
		$atts = shortcode_atts([
			'type' => '',
			'class' => '',
		], $atts, $tag);
		$this->verifyClubCode();
		
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
		$class = $atts['class'] ? "class=\"{$atts['class']}\"" : '';
		ob_start();
		
		if ($this->availableTickets == 0) {
			?>
			<button <?php echo $class?> type="button" disabled><?php _e('Sold out', 'isrp-event-paygate')?></button>
			<?php
			return ob_get_clean();
		}
		
		?>
		<script>
		window.paygate_ticket_types = window.paygate_ticket_types || {};
		window.paygate_ticket_types['<?php echo $this->currentTicketType?>'] = [
		'<?php echo $this->getTicketPrice(true, $this->currentTicketType);?>',
		'<?php echo $this->getTicketPrice(false, $this->currentTicketType);?>'
		];
		</script>
		<button <?php echo $class?> type="button" onclick="PayGateCheckout.addTicket(this, '<?php echo $this->currentTicketType?>')">
		<?php echo do_shortcode(trim($content)) ?>
		</button>
		<?php
		$this->currentTicketType = null;
		return ob_get_clean();
	}
	
	private function getTicketPrice($forClub, $ticketType) {
		$isClub = !is_null($this->currentClubId) && !($this->currentClubIdWasUsed) && $forClub;
		if ($isClub)
			error_log("PayGate: Calculating price for club ticket");
		return $this->prices[$ticketType][$isClub ? 1 : 0];
	}
	
	private function verifyClubCode() {
		if ($this->currentClubId)
			return;
		$clubid = @$_REQUEST['club-id'];
		if (!empty($clubid)) {
			if (!$this->pg->verifyClubId($clubid)) {
				wp_die(esc_html__('Invalid club ID!', 'isrp-event-paygate'));
			}
			
			$this->currentClubId = $clubid;
			$mid = $this->pg->getClubCard($clubid)->member_number;
			if ($this->pg->database()->checkUsedClubId($mid)) {
				$this->currentClubIdWasUsed = true;
				ob_start()
				?>
				<div class="paygate-no-club">
				<h3><?php _e('Club members are elgible for only 1 discounted ticket', 'isrp-event-paygate')?></h3>
				<form method="get" action="">
				<button type="submit"><?php _e('Click here to go back to registration form', 'isrp-event-paygate')?></button>
				</form>
				</div>
				<?php
				print ob_get_clean();
			}
		}
	}
	
	public function clubForm($atts, $content = null, $tag = '') {
		global $wp;
		$atts = shortcode_atts([
			'success' => '',
			'width' => '',
			'style' => '',
		], $atts, $tag);
		
		// if there is already a club code, don't show
		if (@$_REQUEST['club-id'])
			return '';
		
		if (empty($atts['success']))
			$atts['success'] = add_query_arg($wp->query_vars, home_url($wp->request));
		if (filter_var($atts['success'], FILTER_VALIDATE_URL) === false)
			$atts['success'] = home_url($atts['success']);

		ob_start();
		?>
		<div class="paygate-club">
		<form method="post" action="/?paygate-handler">
		<input type="hidden" name="action" value="club-verify">
		<input type="hidden" name="success-page" value="<?php echo $atts['success']?>">
		<?php echo do_shortcode($content); ?>
		<input type="text" name="club-email"
			width="<?php echo @$atts['width']; ?>"
			value="<?php echo @$atts['value']; ?>"
			style="<?php echo @$atts['style']; ?>">
		<button type="submit"><?php _e('Send', 'isrp-event-paygate')?></button>
		</form>
		</div>
		<?php
		return ob_get_clean();
	}
	
	public function clubVerify() {
		$email = @$_REQUEST['club-email'];
		$success = @$_REQUEST['success-page'];
		error_log("Paygate: checking club membership of $email, will return to $success");
		$id = $this->pg->getClubId($email);
		if (strpos($success, '?') === false)
			$success .= '?';
		else
			$success .= '&';
		if ($id !== false) {
			header('Location: ' . $success . 'club-id=' . $id);
			exit();
		}
		
		wp_die( __('Unrecognized e-mail address, please try again', 'isrp-event-paygate') );
	}
}
