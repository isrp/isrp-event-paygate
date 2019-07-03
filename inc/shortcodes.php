<?php

class PayGateShortcodes {
	private $pg;
	private $table_name;
	private $pelepay_account;
	private $processor;
	private $settings;
	private $currentTicketType = null;
	private $currentDragonId = null;
	private $currentDragonIdWasUsed = false;
	
	public function __construct(PayGate $paygate) {
		$this->pg = $paygate;
		$this->pelepay_account = get_option('paygate-pelepay-account');
		$this->processor = new PayGatePelepayProcessor($this->pelepay_account);
		
		add_shortcode('paygate-checkout', [ $this, 'payCheckout' ]);
		add_shortcode('paygate-name', [ $this, 'nameField' ]);
		add_shortcode('paygate-button', [ $this, 'payButton' ]);
		add_shortcode('paygate-price', [ $this, 'showPrice' ]);
		add_shortcode('paygate-dragon-form', [ $this, 'dragonForm']);
	}
	
	public function payCheckout($atts, $content = null) {
		$atts = shortcode_atts([
		], $atts, 'paygate-checkout');
		
		$this->verifyDragonCode();
		
		ob_start();
		?>
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
		<script>
		jQuery(document).ready(function(){
			window.PayGateCheckout = new (function() {
				this.cart = <?php if ($this->pg->settings()->allowMultipleTickets()): ?>true<?php else:?>false<?php endif;?>;
				this.table = document.getElementById("paygate-cart");
				this.form = document.getElementById("paygate-form");
				this.nameField = document.getElementById("paygate-ticket-name");
				this.totalField = document.getElementById("paygate-total");
				this.checkoutButton = document.getElementById('paygate-checkout');
				this.allowMultiple = <?php echo $this->pg->settings()->allowMultipleTickets() ? 'true' : 'false'?>;
				this.total = 0;
				this.checkoutButton.setAttribute('disabled','disabled');

				this.updateTicketPrices = function() {
					if (!window.paygate_ticket_types) return;
					if (!window.paygate_price_handlers) return;
					for (var tt in window.paygate_ticket_types) {
						var price = window.paygate_ticket_types[tt][this.total == 0 ? 0 : 1];
						if (!window.paygate_price_handlers[tt]) continue;
						window.paygate_price_handlers[tt].forEach(function(h){
							h(price);
						});
					}
				};
				
				this.addTicket = function(type) {
					if (!type) return false;
					if (this.nameField && !this.nameField.value)
						return alert("יש למלא שם של מחזיק הכרטיס");
						
					var price = parseFloat(window.paygate_ticket_types[type][this.total == 0 ? 0 : 1]);
					if (this.cart) {
						var ticket = document.createElement('tr');
						ticket.appendChild(this.makeCell(type));
						ticket.appendChild(this.makeCell(price));
						ticket.appendChild(this.makeCell(this.nameField.value));
						this.table.tBodies[0].appendChild(ticket);
					}
					this.addTicketField(type, price, this.nameField ? this.nameField.value: '');
					this.total += price;
					this.totalField.innerHTML = this.total;
					if (!this.allowMultiple)
						return this.form.submit();
					this.updateTicketPrices();
					//this.nameField.value = '';
				};

				this.addTicketField = function(type, price, name) {
					var input = document.createElement('input');
					input.setAttribute('type','hidden');
					input.setAttribute('name','tickets[' + type + '][]');
					input.setAttribute('value', price + ':' + name)
					this.form.appendChild(input);
					this.checkoutButton.removeAttribute('disabled');
				};
				
				this.makeCell = function(text) {
					var td = document.createElement('td');
					td.appendChild(document.createTextNode(text));
					return td;
				};

				this.updateTicketPrices();
				
				return this;
			})();
		});
		</script>
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
		if (empty($ticketType))
			return 'PRICE ERROR';
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
		
		if (empty($atts['type']))
			return 'TYPE ERROR';
		
		$this->currentTicketType = $atts['type'];
		ob_start();
		?>
		<button type="button" onclick="PayGateCheckout.addTicket('<?php echo $atts['type']?>')">
		<?php echo do_shortcode(trim($content)) ?>
		</button>
		<script>
		window.paygate_ticket_types = window.paygate_ticket_types || {};
		window.paygate_ticket_types['<?php echo $atts['type']?>'] = [
			'<?php echo $this->getTicketPrice($atts['type']);?>',
			'<?php echo $this->pg->database()->getCurrentTicketPrice($atts['type'], false)?>'
		];
		</script>
		<?php
		$this->currentTicketType = null;
		return ob_get_clean();
	}
	
	private function getTicketPrice($ticketType) {
		$isDragon = !is_null($this->currentDragonId) && !($this->currentDragonIdWasUsed);
		if ($isDragon)
			error_log("PayGate: Calculating price for dragon ticket");
		return $this->pg->database()->getCurrentTicketPrice($ticketType, $isDragon);
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
