<?php

// load settings
foreach (scandir(__DIR__.'/settings') as $filename) {
	$path = __DIR__.'/settings/' . $filename;
	if (is_file($path))
		require_once $path;
}

$paygate_default_tz = new DateTimeZone('Asia/Jerusalem');

class PayGateSettingsPage {
	private $pg;
	private $pelepay_account;
	private $dragon_members_url;
	private $accept_test_transaction;
	private $allowMultiple;
	
	const PAY_ICON = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pg0KPCEtLSBHZW5lcmF0b3I6IEFkb2JlIElsbHVzdHJhdG9yIDE4LjAuMCwgU1ZHIEV4cG9ydCBQbHVnLUluIC4gU1ZHIFZlcnNpb246IDYuMDAgQnVpbGQgMCkgIC0tPg0KPCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4NCjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCINCgkgdmlld0JveD0iMCAwIDQ4Mi4wODUgNDgyLjA4NSIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNDgyLjA4NSA0ODIuMDg1OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+DQo8Zz4NCgk8cGF0aCBkPSJNNDYxLjI3MSwzNDMuMzEyYy02LjMzOS01LjY1NC0xNC4yOTctOC4yNi0yMi40MDMtNy4zMjljLTEzLjYxMiwxLjU1Ny0zMS40MzYsNS4yMzMtNTAuMzA3LDkuMTI1DQoJCWMtMjUuNDgyLDUuMjU2LTUxLjgzLDEwLjY5LTcyLjAwNCwxMS45NTFjLTE3LjQyNiwxLjA4Ni00OS4xNjEtMy40NTQtNTguNTYzLTguMzc5Yy02LjM0Mi0zLjMyMi05Ljg5LTcuMzk2LTExLjg3Ny0xMC45OThoMzkuMTENCgkJYzE2LjEzNSwwLDI5LjI2MS0xMy4xMjYsMjkuMjYxLTI5LjI2di0yMi45NzhjMC0xNi4xMzQtMTMuMTI2LTI5LjI1OS0yOS4yNjEtMjkuMjU5SDE3OC4wMmMtNC41NTUtMC4xNC01OS4yNTMtMS4xNy05Ny4xMzEsMzEuMTQNCgkJYy02LjcwNCw1LjcxOC0xMi4wMzYsMTEuMTIxLTE2LjI4OCwxNi4wMzFjLTQuNDk4LTUuNjIxLTExLjQxLTkuMjMyLTE5LjE1My05LjIzMkgxMC41Yy01Ljc5OSwwLTEwLjUsNC43MDEtMTAuNSwxMC41VjQ1Mi45Ng0KCQljMCwyLjc4NSwxLjEwNyw1LjQ1NiwzLjA3NSw3LjQyNWMxLjk3LDEuOTY5LDQuNjQxLDMuMDc1LDcuNDI1LDMuMDc1bDM0Ljk0Ny0wLjAwMWMxMy41MjUsMCwyNC41MjgtMTEuMDAzLDI0LjUyOC0yNC41Mjd2LTguMDcNCgkJYzEwLjQ1MSwwLjY1NiwzNC4xMzYsMy4yNDIsODQuMjYxLDEyLjY1M2M1OS4xOTQsMTEuMTE1LDk2LjEzLDE0Ljg4MiwxMjUuMjMxLDE0Ljg4MWMyMC44NTYsMCwzNy42OTEtMS45MzQsNTUuODAxLTQuNDg1DQoJCWMyMi4zODctMy4xNTEsNTAuNTQ0LTExLjExNiw3NS4zODYtMTguMTQzYzExLjUtMy4yNTMsMjIuMzYyLTYuMzI1LDMxLjk3NC04Ljc0OWMyNS45MDEtNi41MzMsMjkuNzY5LTIyLjg2NSwyOS43NjktMzIuMDJWMzY4LjI1DQoJCUM0NzIuMzk3LDM1OC44NSw0NjguMjM3LDM0OS41MjcsNDYxLjI3MSwzNDMuMzEyeiBNNDguOTc2LDQzOC45MzJjMCwxLjk0NS0xLjU4MywzLjUyNy0zLjUyOCwzLjUyN0wyMSw0NDIuNDZWMzE1LjEyNWgyNC40NDcNCgkJYzEuOTQ1LDAsMy41MjgsMS41ODIsMy41MjgsMy41MjdWNDM4LjkzMnogTTQ1MS4zOTcsMzk1YzAsMi4yMywwLDguMTUtMTMuOTAzLDExLjY1NmMtOS45MDMsMi40OTgtMjAuOTA2LDUuNjEtMzIuNTU1LDguOTA1DQoJCWMtMjQuMTk4LDYuODQ1LTUxLjYyNSwxNC42MDMtNzIuNTk4LDE3LjU1NWMtNDEuMzE3LDUuODE4LTc1Ljc2Niw4LjI0OC0xNzQuMjMtMTAuMjM5Yy01MC42MzktOS41MDgtNzUuNzczLTEyLjM4Mi04OC4xMzYtMTMuMDQxDQoJCXYtNzcuOTYxYzAuMzc1LTAuNDY5LDAuNzIyLTAuOTY4LDEuMDIxLTEuNTEzYzIuNjktNC45MDgsOS4zMjMtMTQuOTQ5LDIzLjUyMS0yNy4wNTljMzIuMzczLTI3LjYxMyw4Mi40Mi0yNi4xNDIsODIuOTEzLTI2LjEyNQ0KCQljMC4xMzYsMC4wMDUsMC4yNzIsMC4wMDgsMC40MDgsMC4wMDhoMTA3LjM5YzQuNTU1LDAsOC4yNjEsMy43MDUsOC4yNjEsOC4yNTl2MjIuOTc4YzAsNC41NTUtMy43MDYsOC4yNi04LjI2MSw4LjI2aC0xMDcuMzkNCgkJYy01Ljc5OSwwLTEwLjUsNC43MDEtMTAuNSwxMC41czQuNzAxLDEwLjUsMTAuNSwxMC41aDQ1Ljk3N2MyLjE1OSw4LjYxLDguMTE2LDIxLjA1MiwyNC40MzUsMjkuNg0KCQljMTQuOTI1LDcuODE5LDUxLjk0NCwxMS44NDYsNjkuNjE2LDEwLjczNmMyMS42NTQtMS4zNTIsNDguNzQyLTYuOTM5LDc0LjkzOC0xMi4zNDNjMTguMzk2LTMuNzk1LDM1Ljc3Mi03LjM3OSw0OC40NTEtOC44MjkNCgkJYzIuNjcyLTAuMzAyLDQuNzU2LDAuOTkzLDYuMDM3LDIuMTM3YzIuNTMyLDIuMjU5LDQuMTA2LDUuODExLDQuMTA2LDkuMjY4VjM5NXoiLz4NCgk8cGF0aCBkPSJNNDU1LjE1NywxOC42MjVIODYuMzYyYy0xNC44NDgsMC0yNi45MjgsMTIuMDgtMjYuOTI4LDI2LjkyOFYyMTguNDVjMCwxNC44NDksMTIuMDgsMjYuOTMsMjYuOTI4LDI2LjkzaDM2OC43OTUNCgkJYzE0Ljg0OCwwLDI2LjkyOC0xMi4wODEsMjYuOTI4LTI2LjkzVjQ1LjU1MkM0ODIuMDg1LDMwLjcwNSw0NzAuMDA1LDE4LjYyNSw0NTUuMTU3LDE4LjYyNXogTTQwMy4wNjcsMjI0LjM3OUgxMzguNDc0DQoJCWMtMi43ODItMzEuMzA2LTI3LjEyMi01Ni40OS01OC4wMzktNjAuNTgzVjk3LjM0M2MyOS45NjctMy45NjgsNTMuNzUxLTI3Ljc1Miw1Ny43MTgtNTcuNzE5aDI2NS4yMzUNCgkJYzMuOTY3LDI5Ljk2MSwyNy43NCw1My43NDEsNTcuNjk3LDU3LjcxN3Y2Ni40NTdDNDMwLjE3OSwxNjcuOSw0MDUuODQ5LDE5My4wODEsNDAzLjA2NywyMjQuMzc5eiBNNDYxLjA4NSw0NS41NTJ2MzAuNTI4DQoJCWMtMTguMzU5LTMuNTkxLTMyLjg1NC0xOC4wOTMtMzYuNDM4LTM2LjQ1NmgzMC41MUM0NTguNDI2LDM5LjYyNSw0NjEuMDg1LDQyLjI4NCw0NjEuMDg1LDQ1LjU1MnogTTg2LjM2MiwzOS42MjVoMzAuNTMxDQoJCWMtMy41ODQsMTguMzctMTguMDg5LDMyLjg3NS0zNi40NTksMzYuNDZWNDUuNTUyQzgwLjQzNSw0Mi4yODQsODMuMDk0LDM5LjYyNSw4Ni4zNjIsMzkuNjI1eiBNODAuNDM1LDIxOC40NXYtMzMuMzk1DQoJCWMxOS4zMTQsMy43NjksMzQuMzUyLDE5LjYxMSwzNi45MTksMzkuMzI0SDg2LjM2MkM4My4wOTQsMjI0LjM3OSw4MC40MzUsMjIxLjcxOSw4MC40MzUsMjE4LjQ1eiBNNDU1LjE1NywyMjQuMzc5aC0zMC45Nw0KCQljMi41NjYtMTkuNzA2LDE3LjU5My0zNS41NDUsMzYuODk3LTM5LjMydjMzLjM5MUM0NjEuMDg1LDIyMS43MTksNDU4LjQyNiwyMjQuMzc5LDQ1NS4xNTcsMjI0LjM3OXoiLz4NCgk8cGF0aCBkPSJNMjcxLjMyNSw1Ni44ODRjLTQxLjQyMSwwLTc1LjExOSwzMy42OTctNzUuMTE5LDc1LjExN2MwLDQxLjQyMSwzMy42OTgsNzUuMTE5LDc1LjExOSw3NS4xMTkNCgkJYzQxLjQyLDAsNzUuMTE4LTMzLjY5OCw3NS4xMTgtNzUuMTE5QzM0Ni40NDMsOTAuNTgyLDMxMi43NDUsNTYuODg0LDI3MS4zMjUsNTYuODg0eiBNMjcxLjMyNSwxODYuMTIxDQoJCWMtMjkuODQyLDAtNTQuMTE5LTI0LjI3Ny01NC4xMTktNTQuMTE5YzAtMjkuODQsMjQuMjc3LTU0LjExNyw1NC4xMTktNTQuMTE3YzI5Ljg0MSwwLDU0LjExOCwyNC4yNzcsNTQuMTE4LDU0LjExNw0KCQlDMzI1LjQ0MywxNjEuODQzLDMwMS4xNjYsMTg2LjEyMSwyNzEuMzI1LDE4Ni4xMjF6Ii8+DQoJPHBhdGggZD0iTTE1MC40MzksOTkuNzA1Yy0xNy44MSwwLTMyLjI5OCwxNC40ODgtMzIuMjk4LDMyLjI5OGMwLDE3LjgwOSwxNC40ODgsMzIuMjk3LDMyLjI5OCwzMi4yOTcNCgkJYzE3LjgwOSwwLDMyLjI5Ny0xNC40ODgsMzIuMjk3LTMyLjI5N0MxODIuNzM2LDExNC4xOTMsMTY4LjI0OCw5OS43MDUsMTUwLjQzOSw5OS43MDV6IE0xNTAuNDM5LDE0My4yOTkNCgkJYy02LjIyOSwwLTExLjI5OC01LjA2Ny0xMS4yOTgtMTEuMjk3YzAtNi4yMyw1LjA2OC0xMS4yOTgsMTEuMjk4LTExLjI5OGM2LjIzLDAsMTEuMjk3LDUuMDY4LDExLjI5NywxMS4yOTgNCgkJQzE2MS43MzYsMTM4LjIzMiwxNTYuNjY5LDE0My4yOTksMTUwLjQzOSwxNDMuMjk5eiIvPg0KCTxwYXRoIGQ9Ik0zOTIuMjA5LDk5LjcwNWMtMTcuODA5LDAtMzIuMjk3LDE0LjQ4OC0zMi4yOTcsMzIuMjk4YzAsMTcuODA5LDE0LjQ4OCwzMi4yOTcsMzIuMjk3LDMyLjI5Nw0KCQljMTcuODEsMCwzMi4yOTktMTQuNDg4LDMyLjI5OS0zMi4yOTdDNDI0LjUwOCwxMTQuMTkzLDQxMC4wMTksOTkuNzA1LDM5Mi4yMDksOTkuNzA1eiBNMzkyLjIwOSwxNDMuMjk5DQoJCWMtNi4yMywwLTExLjI5Ny01LjA2Ny0xMS4yOTctMTEuMjk3YzAtNi4yMyw1LjA2Ny0xMS4yOTgsMTEuMjk3LTExLjI5OGM2LjIzLDAsMTEuMjk5LDUuMDY4LDExLjI5OSwxMS4yOTgNCgkJQzQwMy41MDgsMTM4LjIzMiwzOTguNDQsMTQzLjI5OSwzOTIuMjA5LDE0My4yOTl6Ii8+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8L3N2Zz4NCg==';
	
	public function __construct(PayGate $paygate) {
		$this->pg = $paygate;
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		
		$this->pelepay_account = new PayGatePelePayAccountSetting('paygate_section_global');
		$this->dragon_members_url = new PayGateDragonMembersURLSetting('paygate_section_global');
		$this->accept_test_transaction = new PayGateAcceptTestSetting('paygate_section_global');
		$this->allowMultiple = new PayGateAllowMultipleSetting('paygate_section_global');
	}
	
	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_options_page(
			'הגדרות שער תשלום',
			'שער תשלום',
			'manage_options',
			'paygate-admin',
			[ $this, 'create_admin_page' ]
			);
		
		add_menu_page( 'כנסים', 'שער תשלום', 'manage_options',
			'paygate', [$this, 'managementPage' ], static::PAY_ICON);
		
		add_submenu_page( 'paygate', 'מחירים', 'מחירים', 'manage_options', 'paygate-prices',
			[ $this, 'pricesPage' ]);
		
		add_submenu_page( 'paygate', 'דוחות', 'דוחות', 'manage_options', 'paygate-reports',
			[ $this, 'reportsPage' ]);
	}
	
	/**
	 * Register and add settings
	 */
	public function page_init() {
		add_settings_section('paygate_section_global', 'הגדרות כלליות',
			[ $this, 'print_section_info_api'], 'paygate-settings');
		
		$this->pelepay_account->register();
		$this->dragon_members_url->register();
		$this->accept_test_transaction->register();
		$this->allowMultiple->register();
	}
	
	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		?>
		<div class="wrap">
			<form method="post" action="options.php">
			<h2>הגדרות שער תשלום</h2>
			<?php
				// This prints out all hidden setting fields
				settings_fields('paygate_setting_group');
				do_settings_sections('paygate-settings');
				submit_button();
			?>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Print the Section text
	 */
	public function print_section_info_api() {
		//print 'Setup global options';
	}
	
	public function getDragonListURL() {
		return $this->dragon_members_url->getValue();
	}
	
	public function getPelepayAccount() {
		return $this->pelepay_account->getValue();
	}
	
	public function allowTestTransaction() {
		return $this->accept_test_transaction->getValue();
	}
	
	public function allowMultipleTickets() {
		return $this->allowMultiple->getValue();
	}
	
	public function managementPage() {
		global $paygate_default_tz;
		switch (@$_REQUEST['events-action']) {
			case 'create':
				if (@$_REQUEST['event-id'])
					$this->pg->database()->updateEvent($_REQUEST['event-id'],
						@$_REQUEST['event-name'], @$_REQUEST['event-success-page']);
				else
					$this->pg->database()->createEvent(
						@$_REQUEST['event-name'], @$_REQUEST['event-success-page']);
				break;
			case 'edit':
				return $this->showEvents($this->pg->database()->getEvent(@$_REQUEST['event-id']));
			case 'delete':
				if (!$this->pg->database()->deleteEvent(@$_REQUEST['event-id']))
					add_settings_error('paygate', 'events', 'Error deleting event');
				break;
			case 'add-period':
				$evid = (int)@$_REQUEST['event-id'];
				$dt = new DateTime(@$_REQUEST['end-period'], $paygate_default_tz);
				$this->pg->database()->addPeriod($evid, @$_REQUEST['name'],
					$dt->getTimestamp()+86399); // getTimestamp gets time for the beginning of the day
				break;
			case 'delete-period':
				$evid = (int)@$_REQUEST['event-id'];
				$periodId = (int)@$_REQUEST['period-id'];
				$this->pg->database()->deletePeriod($evid, $periodId);
				break;
		}
		$this->showEvents();
	}
	
	private function showEvents($editEvent = null) {
		settings_errors();
		$all_pages = get_pages();
		?>
		<div class="paygate">
		<h1>רשימת כנסים</h1>
		<table>
		<thead>
			<tr>
				<th>#</th>
				<th>שם</th>
				<th>עמוד סיום תשלום</th>
				<th>תקופות</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($this->pg->database()->listEvents() as $ev): ?>
		<?php
		$curpages = @array_filter($all_pages, function($pg) use($ev) {
			return $pg->post_name == $ev->success_page;
		});
		$curpage = array_shift($curpages);
		$periods = $this->pg->database()->listPeriods($ev->id);
		?>
			<tr>
				<td><?php echo $ev->id?></td>
				<td><?php echo $ev->name?></td>
				<td><a target="blank" href="<?php echo admin_url("post.php?post={$curpage->ID}&action=edit")?>">
					<?php echo $curpage->post_title;?> <i class="fas fa-external-link-alt"></i>
				</a></td>
				<td>
				<table class="internal">
				<tbody>
				<?php $periodStart = ""; ?>
				<?php foreach ($periods as $period):?>
					<tr>
					<td>
					<form method="post" action="">
					<input type="hidden" name="event-id" value="<?php echo $ev->id?>">
					<input type="hidden" name="period-id" value="<?php echo $period->id?>">
					<button type="submit" name="events-action" value="delete-period"><i class="far fa-calendar-minus"></i></button>
					</form>
					</td>
					<td><?php echo $period->name?></td>
					<td><?php echo $periodStart?></td>
					<td> - </td>
					<td><?php echo date("j.n.Y",$period->period_end)?></td>
					<?php $periodStart = date("j.n.Y",$period->period_end + 86400)?>
					</tr>
				<?php endforeach;?>
				</tbody>
				</table>
				<form method="post" action="">
				<input type="hidden" name="event-id" value="<?php echo $ev->id?>">
				<p>
					<input type="text" name="name">
					<input type="date" name="end-period" value="<?php echo date("Y-m-d")?>">
					<button type="submit" name="events-action" value="add-period"><i class="far fa-calendar-plus"></i></button>
				</p>
				</form>
				</td>
				<td style="font-size: 180%;">
					<form method="post" action="">
						<input type="hidden" name="event-id" value="<?php echo $ev->id?>">
						<button type="submit" name="events-action" title="עריכת ארוע" value="edit"><i class="far fa-edit"></i></button>
						<a style="color: inherit;" href="<?php echo admin_url("admin.php?page=paygate-prices&event-id=$ev->id")?>" title="מחירים"><i class="fas fa-hand-holding-usd"></i></a>
						<button type="submit" name="events-action" title="מחיקת ארוע" value="delete"><i class="far fa-trash-alt"></i></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
		</table>
		
		<p></p>
		
		<form method="post" action="">
		<?php if ($editEvent):?>
		<h2>עריכת כנס:</h2>
		<input type="hidden" name="event-id" value="<?php echo $editEvent->id?>">
		<?php else:?>
		<h2>יצירת כנס חדש:</h2>
		<?php endif;?>
		
		<p><label><span>שם כנס: </span><input type="text" name="event-name" value="<?php
			if ($editEvent) echo $editEvent->name
			?>"></label></p>
		<p><label><span>עמוד סיום תשלום: </span>
			<select name="event-success-page">
			<?php foreach ($all_pages as $page): ?>
			<option value="<?php echo $page->post_name?>" <?php
				if ($editEvent and $page->post_name == $editEvent->success_page) echo "selected"
				?>><?php echo $page->post_title?></option>
			<?php endforeach; ?>
			</select>
		</label></p>
		
		<button type="submit" name="events-action" value="create">
		<?php if ($editEvent):?>
		עדכון
		<?php else:?>
		יצירת כנס חדש
		<?php endif;?>
		</button>
		
		</form>
		</div>
		<?php
	}
	
	public function pricesPage() {
		$eventId = @$_REQUEST['event-id'];
		switch ($_REQUEST['prices-action']) {
			case 'add-ticket-type':
				$this->pg->database()->addPriceForAllPeriods($eventId, @$_REQUEST['ticket-type']);
				break;
			case 'delete-type':
				$this->pg->database()->deletePriceForAllPeriods($eventId, @$_REQUEST['ticket-type']);
				break;
			case 'update-prices':
				$priceMatrix = @$_REQUEST['paygate-price-matrix'];
				if (!$priceMatrix) return;
				foreach ($priceMatrix as $periodId => $prices) {
					foreach ($prices as $ticketType => $ticketPrice) {
						$fullCost = $ticketPrice['full'];
						$dragonCost = $ticketPrice['dragon'];
						$this->pg->database()->updatePrice($periodId, $ticketType, $fullCost, $dragonCost);
					}
				}
				break;
		}
		$this->showPriceEditor($eventId);
	}
	
	public function showPriceEditor($eventId) {
		settings_errors();
		$action_url = admin_url("admin.php?page=paygate-prices&event-id=$eventId");
		?>
		<div class="paygate">
		<h1>עריכת מחירי כרטיסים</h1>
		<form method="get" action="<?php echo admin_url("admin.php");?>">
		<input type="hidden" name="page" value="paygate-prices">
		<label>
		כנס:
		<select name="event-id" onchange="this.form.submit();">
		<option>בחר כנס</option>
		<?php foreach ($this->pg->database()->listEvents() as $ev):?>
		<option value="<?php echo $ev->id?>" <?php
			if ($ev->id == $eventId) echo "selected";
		?>><?php echo $ev->name ?></option>
		<?php endforeach;?>
		</select>
		</label>
		</form>
		
		<?php
		
		if (!is_numeric($eventId))
			return;
		
		$event = $this->pg->database()->getEvent($eventId);
		if (!$event)
			return;
		$periods = $this->pg->database()->listPeriods($event->id);
		$periodStart = '';
		$priceMatrix = [];
		?>
		
		<form method="post" action="<?php echo $action_url?>">
		<table>
		<thead>
			<tr>
			<th>סוג כרטיס</th>
			<?php foreach ($periods as $period):?>
			<?php
			foreach ($this->pg->database()->listPrices($period->id) as $ticket) {
				$priceMatrix[$ticket->ticket_type][$period->id] = $ticket;
			}
			?>
			<th>
				<?php echo $period->name ?>
				( <?php echo $periodStart ?> - <?php echo date("j.n.Y",$period->period_end)?> )
			</th>
			<?php $periodStart = date("j.n.Y",$period->period_end + 86400)?>
			<?php endforeach;?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($priceMatrix as $ticketType => $ticketPeriods):?>
		<?php $ticketid = bin2hex(openssl_random_pseudo_bytes(4))?>
		<tr>
			<th>
			<a href="<?php echo $action_url?>&prices-action=delete-type&ticket-type=<?php echo urlencode($ticketType)?>"
				title="הסרת כרטיס מסוג <?php echo $ticketType?>" style="color: inherit;"
				><i class="fas fa-trash-alt"></i></a>
				<a onclick="document.getElementById('<?php echo $ticketid?>').select();document.execCommand('copy');return false;"
					href="#" style="color: inherit;"><i class="far fa-clipboard"></i></a>
			<?php echo $ticketType?>
			<input type="text" style="display: none;" id="<?php echo $ticketid?>" value="<?php echo $ticketType?>">
			</th>
			<?php foreach ($periods as $period):?>
			<?php
			$periodId = $period->id;
			$ticket = @$ticketPeriods[$periodId];
			if ($ticket) {
				$regularCost = $ticket->full_price > 0 ? $ticket->full_price : '';
				$dragonCost = $ticket->dragon_price >0 ? $ticket->dragon_price : '';
			} else {
				$regularCost = '';
				$dragonCost = '';
			}
			$regularInputId = "$periodId-$ticketType-full";
			$dragonInputId = "$periodId-$ticketType-dragon";
			?>
			<td>
				<p>
				<label>
				<span>מחיר רגיל:</span>
				<input id="<?php echo $regularInputId?>" name="paygate-price-matrix[<?php echo $periodId?>][<?php echo $ticketType?>][full]" type="number" value="<?php echo $regularCost?>" min="0">₪
				</label>
				<button type="button" onclick="document.getElementById('<?php echo $regularInputId?>').value = '';"><i class="far fa-times-circle"></i></button>
				</p>
				<p>
				<label>
				<span>מחיר מועדון:</span>
				<input id="<?php echo $dragonInputId?>" name="paygate-price-matrix[<?php echo $periodId?>][<?php echo $ticketType?>][dragon]" type="number" value="<?php echo $dragonCost?>" min="0">₪
				</label>
				<button type="button" onclick="document.getElementById('<?php echo $dragonInputId?>').value = '';"><i class="far fa-times-circle"></i></button>
				</p>
			</td>
			<?php endforeach;?>
		</tr>
		<?php endforeach;?>
		</tbody>
		</table>
		<p>
		<button type="submit" name="prices-action" value="update-prices">עדכן מחירים</button>
		</p>
		</form>
		
		<form method="post" action="<? echo $action_url ?>">
		<h2>הוספת סוג כרטיס</h2>
		<label>
		<span>שם סוג כרטיס:</span>
		<input type="text" name="ticket-type">
		</label>
		<p></p>
		<button type="submit" name="prices-action" value="add-ticket-type">הוסף סוג כרטיס</button>
		</form>
		</div>
		<?php
	}
	
	public function reportsPage() {
		//must check that the user has the required capability
		if (!current_user_can('manage_options'))
			wp_die( __('You do not have sufficient permissions to access this page.') );
		
		switch ($_POST['paygate-action']) {
			case 'delete':
				$this->pg->database()->deleteRegistration($_POST['id']);
				break;
		}
		$this->showReport();
	}
	
	private function showReport() {
		global $paygate_default_tz;
		settings_errors();
		$dt = new DateTime("now", $paygate_default_tz);
		
		$page_size = (int)(@$_REQUEST['page-size'] ? $_REQUEST['page-size'] : 50);
		$page_count = $this->pg->database()->getRegistrationPageCount($page_size);
		$page = (int)@$_REQUEST['page'];
		if (!$page) $page = 1;
		if ($page > $page_count) $page = $page_count;
		$at_first_page = ($page <= 1) ? 'disabled' : '';
		$at_last_page = ($page >= $page_count) ? 'disabled' : '';
		?>
		<div class="paygate-registrations">
		<h1>נרשמים</h1>
		<table class="widefat">
		<thead>
			<tr><th>#</th><th>שם</th><th>סוג</th><th>עלות</th><th>זמן הזמנה</th>
			<th>כרטיס דרקון</th><th>אישור פלאפיי</th><th>קוד</th><th></th></tr>
		</thead>
		<tbody>
		<?php foreach ($this->pg->database()->getRegistrationsPage($page, $page_size) as $row): ?>
		<?php
		$details = json_decode($row->details);
		$dt->setTimestamp($row->order_time);
		$ticketType = $this->pg->database()->getPrice($row->price_id);
		?>
		<tr onmouseover="document.getElementById('reg-<?php echo $row->id?>').style.display = 'block';"
				onmouseout="document.getElementById('reg-<?php echo $row->id?>').style.display = 'none';">
			<td><?php echo $row->id?></td>
			<td><?php echo stripslashes($row->name)?></td>
			<td><?php echo $ticketType->ticket_type?></td>
			<td>₪<?php echo $row->price?></td>
			<td style="direction: ltr; text-align: right;"><?php echo $dt->format('d.m.Y, H:i')?></td>
			<td><?php echo $row->dragon_id?></td>
			<td><?php echo $details->ConfirmationCode?></td>
			<td><?php echo $row->order_id?></td>
			<td>
				<?php if ($details->index[0] == 'T'):?>
				<form method="post" action="">
				<input type="hidden" name="id" value="<?php echo $row->id?>">
				<?php echo 'בדיקה!'?>
				<button title="Delete <?php echo $row->name?>" type="submit" onclick="return confirm('להסיר את רישום הבדיקה של <?php echo $row->name?>?')"
					name="paygate-action" value="delete"><i class="fas fa-minus-circle"></i></button>
				</form>
			<?php endif;?></td>
		</tr>
		<tr style="height:0"><td colspan="9"><div class="payer-details" id="reg-<?php echo $row->id?>">
			<strong>פרטי משלם</strong>:
			<p><?php echo urldecode($details->firstname)?> <?php echo urldecode($details->lastname)?></p>
			<p>טלפון: <?php echo $details->phone?></p>
			<p>שולם: ₪<?php echo $details->amount?></p>
		</div></td></tr>
		<?php endforeach; ?>
		</tbody>
		</table>
		
		<?php if ($page_count > 1):?>
		<form method="post" action="">
		<p>
		<input type="hidden" name="page-size" value="<?php echo $page_size?>">
		<button type="submit" class="pager-first" name="page" value="0" <?php echo $at_first_page;?>></button>
		<button type="submit" class="pager-prev" name="page" value="<?php echo ($page - 1);?>" <?php echo $at_first_page;?>></button>
		<strong>Page: <?php echo $page;?></strong>
		<button type="submit" class="pager-next" name="page" value="<?php echo ($page + 1);?>" <?php echo $at_last_page;?>></button>
		<button type="submit" class="pager-last" name="page" value="<?php echo ($page_count - 1);?>" <?php echo $at_last_page;?>></button>
		</p>
		</form>
		<?php endif; ?>
		
		<form method="post" action="/paygate-handler">
		<p>
		<button type="submit" name="action" value="export">
		<i class="fas fa-archive"></i> Export
		</button>
		</p>
		</form>
		</div>
		<?php
	}
	
	public function handleExport() {
		$f = fopen('php://memory', 'w');
		fputcsv($f, [
			'מספר כרטיס',
			'שם',
			'סוג',
			'עלות',
			'זמן הזמנה',
			'כרטיס דרקון',
			'אישור פלאפיי',
			'קוד',
			'עלות הזמנה',
			'שם הרוכש',
			'מספר טלפון',
			'דואר אלקטרוני',
		]);
		$dt = new DateTime("now", $paygate_default_tz);
		foreach ($this->pg->database()->getRegistrations() as $row) {
			$details = json_decode($row->details);
			if ($details->index[0] == 'T') // don't export test purchases
				continue;
			$ticketType = $this->pg->database()->getPrice($row->price_id);
			$dt->setTimestamp($row->order_time);
			fputcsv($f, [
				$row->id,
				stripslashes($row->name),
				$ticketType->ticket_type,
				$row->price,
				$dt->format('d.m.Y, H:i'),
				$row->dragon_id,
				$details->ConfirmationCode,
				$row->order_id,
				$details->amount,
				urldecode($details->firstname) . ' ' . urldecode($details->lastname),
				"'".$details->phone,
			]);
		}
		fseek($f, 0);
		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="paygate.csv";');
		fpassthru($f);
		exit();
	}

}
