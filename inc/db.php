<?php

class PayGateDatabase {
	var $db_version = '7';
	var $reg_table_name;
	var $events_table_name;
	var $periods_table_name;
	var $prices_table_name;
	var $db;
	
	public function __construct($mainfile) {
		global $wpdb;
		$this->db = $wpdb;
		$this->reg_table_name = $this->db->prefix . "paygate_registrations";
		$this->events_table_name = $this->db->prefix . "paygate_events";
		$this->periods_table_name = $this->db->prefix . "paygate_periods";
		$this->prices_table_name = $this->db->prefix . "paygate_prices";
		
		register_activation_hook( $mainfile, [ $this, 'install' ]);
		add_action( 'plugins_loaded', [ $this, 'updateDB']);
		
		if($this->db->get_var("SHOW TABLES LIKE '$this->reg_table_name'") != $this->reg_table_name)
			$this->createTable();
	}
	
	public function install() {
		error_log("PayGate: Creating database tables");
		$this->createTable();
		add_option( 'paygate_db_version', $this->db_version );
	}
	
	public function updateDB() {
		$version = get_option( 'paygate_db_version', $this->db_version);
		if (version_compare($version, $this->db_version) < 0) {
			$this->createTable();
			update_option( 'paygate_db_version', $this->db_version );
		}
	}
	
	private function createTable() {
		$charset_collate = $this->db->get_charset_collate();
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		dbDelta("CREATE TABLE $this->events_table_name (
			id int NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			success_page varchar(255) NOT NULL DEFAULT 'paygate-success',
			PRIMARY KEY (id)
		) $charset_collate;");
		
		dbDelta("CREATE TABLE $this->periods_table_name (
			id INT NOT NULL AUTO_INCREMENT,
			event_id INT NOT NULL,
			name varchar(255) NOT NULL,
			period_end INT NOT NULL,
			PRIMARY KEY (id)
		) $charset_collate;");
		
		dbDelta("CREATE TABLE $this->prices_table_name (
			id INT NOT NULL AUTO_INCREMENT,
			period_id INT NOT NULL,
			ticket_type VARCHAR(255) NOT NULL,
			full_price DECIMAL(5,2) NOT NULL,
			dragon_price DECIMAL(5,2) DEFAULT NULL,
			PRIMARY KEY (id)
		) $charset_collate;");
		
		dbDelta("CREATE TABLE $this->reg_table_name (
			id int NOT NULL AUTO_INCREMENT,
			event_id INT NOT NULL,
			period_id INT NOT NULL,
			price_id INT NOT NULL,
			name varchar(255) NOT NULL,
			price decimal(5,2) NOT NULL DEFAULT 0,
			order_time int DEFAULT NULL,
			order_id varchar(255) DEFAULT NULL,
			dragon_id varchar(10) DEFAULT NULL,
			details TEXT DEFAULT '',
			PRIMARY KEY (id)
		) $charset_collate;");
	}
	
	public function listEvents() {
		return $this->db->get_results("SELECT * FROM $this->events_table_name");
	}
	
	public function createEvent($name, $success_page) {
		if (empty($name) or empty($success_page))
			return null;
		return $this->db->insert($this->events_table_name, [
			'name' => $name,
			'success_page' => $success_page,
		]);
	}
	
	public function deleteEvent($id) {
		if (!$this->verifyCanDeleteEvent($id))
			return null;
		foreach ($this->listPeriods($id) as $period)
			$this->deletePeriod($id, $period->id);
		return $this->db->delete($this->events_table_name, [ "id" => $id]);
	}
	
	private function verifyCanDeleteEvent($eventId) {
		foreach ($this->listPeriods($eventId) as $period)
			if (!$this->verifyCanDeletePeriod($period->id))
				return false;
		return true;
	}
	
	public function getEvent($id) {
		return $this->db->get_row("SELECT * FROM $this->events_table_name where id = " . ((int)$id));
	}
	
	public function updateEvent($id, $name, $success_page) {
		if (empty($name) or !is_numeric($id) or $id <= 0 or empty($success_page))
			return null;
		return $this->db->update($this->events_table_name, [
			'name' => $name,
			'success_page' => $success_page,
		], [
			'id' => (int)$id,
		]);
	}
	
	public function listPeriods($eventId) {
		return $this->db->get_results("SELECT * FROM $this->periods_table_name ".
			"WHERE event_id = " . ((int)$eventId) . " ".
			"ORDER BY period_end ASC");
	}
	
	public function addPeriod($eventId, $name, $endPeriod) {
		if (empty($name) or !is_numeric($eventId) or $eventId <= 0 or empty($endPeriod))
			return null;
		$this->db->insert($this->periods_table_name, [
			'event_id' => (int)$eventId,
			'name' => $name,
			'period_end' => $endPeriod,
		]);
		$periodId = $this->db->insert_id;
		$this->addPrice($periodId, 'כרטיס רגיל');
	}
	
	private function verifyCanDeletePeriod($periodId) {
		if ($this->db->get_var("SELECT COUNT(*) FROM $this->reg_table_name ".
			"WHERE period_id = " . ((int)$periodId)) > 0) {
			add_settings_error('paygate', 'registrations', 'Cannot delete period as there are ticket sold!');
			return false;
		}
		return true;
	}
	
	public function deletePeriod($eventId, $periodId) {
		if (!$this->verifyCanDeletePeriod($periodId))
			return null;
		
		$this->db->delete($this->prices_table_name, [
			'period_id' => $periodId
		]);
		return $this->db->delete($this->periods_table_name, [
			"event_id" => $eventId,
			"id" => $periodId,
		]);
	}
	
	public function getPeriod($periodId) {
		return $this->db->get_row("SELECT * FROM $this->periods_table_name where id = " . ((int)$periodId));
	}
	
	public function listPrices($periodId) {
		return $this->db->get_results("SELECT * FROM $this->prices_table_name ".
			"WHERE period_id = " . ((int)$periodId));
	}
	
	public function addPriceForAllPeriods($eventId, $type) {
		foreach ($this->listPeriods($eventId) as $period)
			$this->addPrice($period->id, $type);
	}
	
	public function addPrice($periodId, $type) {
		if (empty($type) or !is_numeric($periodId) or $periodId <= 0)
			return null;
		if ($this->db->get_var("SELECT COUNT(*) FROM $this->prices_table_name ".
				"WHERE period_id = " . ((int)$periodId) .
				"AND ticket_type = '".esc_sql($type)."'") > 0)
			return;
		$this->db->insert($this->prices_table_name, [
			'period_id' => (int)$periodId,
			'ticket_type' => $type,
			'full_price' => 0,
			'dragon_price' => 0,
		]);
	}
	
	public function updatePrice($periodId, $type, $fullCost, $dragonCost) {
		if (empty($type) or !is_numeric($periodId) or $periodId <= 0)
			return null;
		$this->db->update($this->prices_table_name, [
			'full_price' => $fullCost,
			'dragon_price' => $dragonCost,
		], [
			'period_id' => $periodId,
			'ticket_type' => $type,
		]);
	}
	
	private function verifyCanDeletePrice($periodId, $type) {
		$sql = $this->db->prepare("SELECT COUNT(*) FROM $this->reg_table_name AS regs ".
			"INNER JOIN $this->prices_table_name AS prices ON regs.price_id = prices.id ".
			"WHERE prices.period_id = %d AND prices.ticket_type = %s", $periodId, $type);
		if ($this->db->get_var($sql) > 0) {
			add_settings_error('paygate', 'registrations', 'Cannot delete period as there are ticket sold!');
			return false;
		}
		return true;
	}
	
	public function deletePriceForAllPeriods($eventId, $type) {
		$type = stripslashes($type);
		foreach ($this->listPeriods($eventId) as $period)
			if (!$this->verifyCanDeletePrice($period->id, $type))
				return false;
		
		foreach ($this->listPeriods($eventId) as $period)
			$this->deletePrice($period->id, $type);
	}
	
	public function deletePrice($periodId, $type) {
		if (!$this->verifyCanDeletePrice($periodId, $type))
			return false;
		
		$this->db->delete($this->prices_table_name, [
			'period_id' => $periodId,
			'ticket_type' => $type,
		]);
	}
	
	private $price_cache = [];
	public function getPrice($priceId) {
		return $this->price_cache[$priceId] ?: ($this->price_cache[$priceId] = $this->db->get_row("SELECT * FROM $this->prices_table_name ".
			"WHERE id = " . ((int)$priceId)));
	}
	
	public function getPriceByType($periodId, $type) {
		return $this->db->get_row("SELECT * FROM $this->prices_table_name ".
			"WHERE period_id = " . ((int)$periodId) . " ".
			"AND ticket_type = '" . esc_sql($type) . "'");
	}
	
	public function getActiveEventId() {
		return $this->db->get_var("SELECT event_id ".
			"FROM $this->periods_table_name ".
			"WHERE period_end > UNIX_TIMESTAMP(now()) ".
			"ORDER BY period_end ASC ".
			"LIMIT 1");
	}
	
	public function getActivePeriod() {
		return $this->db->get_row("SELECT * FROM $this->periods_table_name ".
			"WHERE period_end > UNIX_TIMESTAMP(now()) ".
			"ORDER BY period_end ASC ".
			"LIMIT 1");
	}
	
	/**
	 * check if the specified dragon ID was already used to purchase a ticket
	 * in the event of the currently active period.
	 * @param string $dragonId
	 */
	public function checkUsedDragonId($dragonId) {
		$activeEventId = $this->getActiveEventId();
		return $this->db->get_var("SELECT COUNT(*) FROM $this->reg_table_name ".
			"WHERE event_id = ".((int)$activeEventId) . " " .
			"AND dragon_id = '" . esc_sql($dragonId) . "'") > 0;
	}
	
	public function getCurrentTicketPrice($ticketType, $isDragon) {
		$column = $isDragon ? 'dragon_price' : 'full_price';
		$activeEventId = $this->getActiveEventId();
		$price = $this->db->get_var(
			"SELECT $column as price FROM $this->prices_table_name ".
			"WHERE period_id in (".
				"SELECT id FROM $this->periods_table_name ".
				"WHERE period_end > UNIX_TIMESTAMP(now()) AND event_id = ".((int)$activeEventId)." ".
				"ORDER BY period_end ASC".
			") ".
			"HAVING price > 0");
		error_log("PayGate: Calculated price for $ticketType, $isDragon in event $activeEventId: $price");
		if ($price == 0 and $isDragon) {
			$price = $this->getCurrentTicketPrice($ticketType, false);
			error_log("PayGate: No dragon price, getting full price: $price");
		}
		if ($price == 0)
			return null;
		if (substr($price,-3) == ".00")
			$price = substr($price, 0, -3);
		return $price;
	}
	
	public function getSuccessLandingPage($periodId) {
		return $this->getEvent($this->getPeriod($periodId)->event_id)->success_page;
	}
	
	public function storeRegistration($name, $type, $period, $price, $time, $orderid, $dragon_id, $details) {
		$this->db->insert($this->reg_table_name, [
			'event_id' => $this->getPeriod($period)->event_id,
			'period_id' => $period,
			'price_id' => $this->getPriceByType($period, $type)->id,
			'name' => $name,
			'price' => $price,
			'order_time' => $time,
			'order_id' => $orderid,
			'dragon_id' => $dragon_id,
			'details' => $details,
		]);
	}
	
	public function getRegistrations() {
		return $this->db->get_results("SELECT * FROM $this->reg_table_name");
	}
	
	public function getRegistrationPageCount($pageSize) {
		return ceil($this->db->get_var("SELECT COUNT(*) FROM $this->reg_table_name" ) / $pageSize);
	}
	
	public function getRegistrationsPage($page, $pageSize) {
		return $this->db->get_results("SELECT * FROM $this->reg_table_name ".
			"ORDER BY order_time DESC LIMIT $pageSize OFFSET " . (($page-1) * $pageSize));
	}
	
	public function deleteRegistration($regId) {
		return $this->db->delete($this->reg_table_name, [ 'id' => $regId ]);
	}
	
}
