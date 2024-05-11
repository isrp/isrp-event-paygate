<?php

class PayGateDatabase {
	var $db_version = '24';
	var $reg_table_name;
	var $events_table_name;
	var $periods_table_name;
	var $prices_table_name;
	var $rooms_table_name;
	var $db;
	var $site_prefix;
	
	public function __construct($mainfile) {
		global $wpdb;
		$this->db = $wpdb;
		$this->site_prefix = $this->db->prefix;
		$this->reg_table_name = $this->db->prefix . "paygate_registrations";
		$this->events_table_name = $this->db->prefix . "paygate_events";
		$this->periods_table_name = $this->db->prefix . "paygate_periods";
		$this->prices_table_name = $this->db->prefix . "paygate_prices";
		$this->roomlists_table_name = $this->db->prefix . "paygate_roomlists";
		$this->rooms_table_name = $this->db->prefix . "paygate_rooms";
		$this->ticket_roomlist_name = $this->db->prefix . "paygate_ticketroomlist";
		
		register_activation_hook( $mainfile, [ $this, 'install' ]);
		add_action( 'plugins_loaded', [ $this, 'updateDB']);
		
		if($this->db->get_var("SHOW TABLES LIKE '$this->reg_table_name'") != $this->reg_table_name)
			$this->createTable();
	}
	
	public function install() {
		error_log("PayGate: Creating database tables in $this->site_prefix for version $this->db_version");
		$this->createTable();
		add_option( 'paygate_db_version', $this->db_version );
	}
	
	public function updateDB() {
		$version = get_option( 'paygate_db_version', 0);
		if (version_compare($version, $this->db_version) < 0) {
			error_log("PayGate: Updating database tables in $this->site_prefix to version $this->db_version");
			$this->createTable($version);
			update_option( 'paygate_db_version', $this->db_version );
		}
	}
	
	private function createTable($oldVersion = 0) {
		$charset_collate = $this->db->get_charset_collate();
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// special column renaming logic for upgrades through v10, as dbDelta() can't handle that
		if ($oldVersion > 0 && $oldVersion < 10) {
			$this->db->query("ALTER TABLE $this->prices_table_name ".
				"CHANGE dragon_price club_price decimal(5,2) DEFAULT NULL NULL");
			$this->db->query("ALTER TABLE $this->reg_table_name ".
				"CHANGE dragon_id club_id varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL NULL");
		}
		
		dbDelta("CREATE TABLE $this->events_table_name (
			id int NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			created INT NOT NULL DEFAULT 0,
			max_tickets INT NOT NULL DEFAULT 0,
			success_page varchar(255) NOT NULL DEFAULT 'paygate-success',
			PRIMARY KEY  (id)
		) $charset_collate;");
		
		dbDelta("CREATE TABLE $this->periods_table_name (
			id INT NOT NULL AUTO_INCREMENT,
			event_id INT NOT NULL,
			name varchar(255) NOT NULL,
			period_end INT NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;");
		
		dbDelta("CREATE TABLE $this->prices_table_name (
			id INT NOT NULL AUTO_INCREMENT,
			period_id INT NOT NULL,
			ticket_type VARCHAR(255) NOT NULL,
			full_price DECIMAL(5,2) NOT NULL,
			club_price DECIMAL(5,2) DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY period_ticket_prices (period_id, ticket_type)
		) $charset_collate;");

		dbDelta("CREATE TABLE $this->roomlists_table_name (
			id INT NOT NULL AUTO_INCREMENT,
			event_id INT NOT NULL,
			room_list_name VARCHAR(255) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;");

		dbDelta("CREATE TABLE $this->rooms_table_name (
  			id INT NOT NULL AUTO_INCREMENT,
     		room_list_id INT NOT NULL,
			room_name VARCHAR(255) NOT NULL,
   			max_tickets INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id)
		) $charset_collate;");
		
		dbDelta("CREATE TABLE $this->ticket_roomlist_name (
			id int NOT NULL AUTO_INCREMENT,
			event_id INT NOT NULL,
			ticket_type VARCHAR(255) NOT NULL,
			room_list_id INT NOT NULL,
			PRIMARY KEY  (id)
			) $charset_collate;");

		dbDelta("CREATE TABLE $this->reg_table_name (
			id int NOT NULL AUTO_INCREMENT,
			event_id INT NOT NULL,
			period_id INT NOT NULL,
			price_id INT NOT NULL,
			status enum('pending', 'complete', 'cancelled', 'revoked') DEFAULT 'pending',
			name varchar(255) NOT NULL,
			price decimal(5,2) NOT NULL DEFAULT 0,
			order_time int DEFAULT NULL,
			order_id varchar(255) DEFAULT NULL,
			club_id varchar(10) DEFAULT NULL,
			details TEXT DEFAULT '',
			room_id INT DEFAULT NULL,
			reason varchar(80) DEFAULT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;");
	}
	
	public function listEvents() {
		return $this->db->get_results(
			"SELECT wpe.*, count(wpr.id) AS sold FROM $this->events_table_name wpe
			LEFT JOIN $this->reg_table_name wpr ON wpr.event_id = wpe.id
			GROUP BY wpe.id"
		);
	}
	
	public function createEvent($name, $success_page, $max_tickets) {
		if (empty($name) or empty($success_page))
			return null;
		return $this->db->insert($this->events_table_name, [
			'name' => $name,
			'success_page' => $success_page,
			'created' => time(),
			'max_tickets' => (int)$max_tickets,
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
		return $this->db->get_row(
			"SELECT wpe.*, count(wpr.id) AS sold FROM $this->events_table_name wpe
			LEFT JOIN $this->reg_table_name wpr ON wpr.event_id = wpe.id
			WHERE wpe.id = " . ((int)$id) . "
			GROUP BY wpe.id");
	}
	
	public function updateEvent($id, $name, $success_page, $max_tickets) {
		if (empty($name) or !is_numeric($id) or $id <= 0 or empty($success_page))
			return null;
		return $this->db->update($this->events_table_name, [
			'name' => $name,
			'success_page' => $success_page,
			'max_tickets' => (int)$max_tickets,
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
			add_settings_error('paygate', 'registrations', __('Cannot delete period as there are ticket sold!', 'isrp-event-paygate'));
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
	
	public function listEventCurrentPrices($eventId) {
		return $this->db->get_results("SELECT * FROM $this->prices_table_name ".
			"WHERE period_id = (".
				"SELECT id FROM $this->periods_table_name ".
				"WHERE period_end > UNIX_TIMESTAMP(now()) AND event_id = " . ((int)$eventId) . " ".
				"ORDER BY period_end ASC ".
				"LIMIT 1)");
	}
	
	public function addPriceForAllPeriods($eventId, $type) {
		foreach ($this->listPeriods($eventId) as $period)
			if (!$this->addPrice($period->id, $type))
				return false;
		return true;
	}
	
	public function addPrice($periodId, $type) {
		if (empty($type) or !is_numeric($periodId) or $periodId <= 0)
			return false;
		if ($this->db->get_var("SELECT COUNT(*) FROM $this->prices_table_name".
				" WHERE period_id = " . ((int)$periodId) .
				" AND ticket_type = '".esc_sql($type)."'") > 0)
			return false;
		return $this->db->insert($this->prices_table_name, [
			'period_id' => (int)$periodId,
			'ticket_type' => $type,
			'full_price' => 0,
			'club_price' => 0,
		]);
	}
	
	public function updatePrice($periodId, $type, $fullCost, $clubCost) {
		if (empty($type) or !is_numeric($periodId) or $periodId <= 0)
			return false;
		return $this->db->query(
			$this->db->prepare("INSERT INTO $this->prices_table_name ".
			"(period_id, ticket_type, full_price, club_price) ".
			"VALUES ('%d', '%s', '%s', '%s') ".
			"ON DUPLICATE KEY UPDATE full_price = VALUES(full_price), club_price = VALUES(club_price)",
			$periodId, $type, $fullCost, $clubCost)
		);
	}
	
	private function verifyCanDeletePrice($periodId, $type) {
		$sql = $this->db->prepare("SELECT COUNT(*) FROM $this->reg_table_name AS regs ".
			"INNER JOIN $this->prices_table_name AS prices ON regs.price_id = prices.id ".
			"WHERE prices.period_id = %d AND prices.ticket_type = %s", $periodId, $type);
		if ($this->db->get_var($sql) > 0) {
			add_settings_error('paygate', 'registrations', __('Cannot delete period as there are ticket sold!', 'isrp-event-paygate'));
			return false;
		}
		return true;
	}
	
	public function deletePriceForAllPeriods($eventId, $type) {
		$type = stripslashes($type);
		$this->setRoomListForTicket($eventId, $type, null);
		foreach ($this->listPeriods($eventId) as $period)
			if (!$this->verifyCanDeletePrice($period->id, $type))
				return false;
		
		foreach ($this->listPeriods($eventId) as $period)
			$this->deletePrice($period->id, $type);
		return true;
	}
	
	public function deletePrice($periodId, $type) {
		if (!$this->verifyCanDeletePrice($periodId, $type))
			return false;
		
		return $this->db->delete($this->prices_table_name, [
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
		return (int)$this->db->get_var("SELECT event_id ".
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
	 * check if the specified club ID was already used to purchase a ticket
	 * in the event of the currently active period.
	 * @param string $clubId
	 */
	public function checkUsedClubId($clubId) {
		$activeEventId = $this->getActiveEventId();
		return $this->db->get_var("SELECT COUNT(*) FROM $this->reg_table_name".
			" WHERE event_id = ".((int)$activeEventId) .
			" AND club_id = '" . esc_sql($clubId) . "'") > 0;
	}
	
	public function getCurrentTicketPrice($ticketType, $isClub) {
		$column = $isClub ? 'club_price' : 'full_price';
		$activeEventId = $this->getActiveEventId();
		$price = $this->db->get_var($this->db->prepare(		
			"SELECT $column as price FROM $this->prices_table_name as prices ".
			"INNER JOIN $this->periods_table_name AS periods on prices.period_id = periods.id ".
			"WHERE period_end > UNIX_TIMESTAMP(now()) ".
			"AND event_id = %d ".
			"AND ticket_type = %s ".
			"ORDER BY period_end ASC ".
			"LIMIT 1", $activeEventId, $ticketType));
		error_log("PayGate: Calculated price for $ticketType, $isClub in event $activeEventId: $price");
		if ($price == 0 and $isClub) {
			$price = $this->getCurrentTicketPrice($ticketType, false);
			error_log("PayGate: No club price, getting full price: $price");
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
	
	public function storeRegistration($name, $type, $period, $price, $time, $orderid, $club_id, $details, $roomId) {
		return $this->db->insert($this->reg_table_name, [
			'event_id' => $this->getPeriod($period)->event_id,
			'period_id' => $period,
			'price_id' => $this->getPriceByType($period, $type)->id,
			'status' => 'pending',
			'name' => $name,
			'price' => $price,
			'order_time' => $time,
			'order_id' => $orderid,
			'club_id' => $club_id,
			'details' => $details,
			'room_id' => $roomId,
		]);
	}

	public function cancelRegistration($orderid, $reason = 'cancelled') {
		return $this->db->update($this->reg_table_name, [
			'status' => $reason == 'revoked' ? 'revoked' : 'cancelled',
			'reason' => $reason,
		], [
			'order_id' => $orderid,
		]);
	}
	
	public function getRegistrations() {
		return $this->db->get_results("SELECT * FROM $this->reg_table_name");
	}
	
	public function getRegistrationPageCount($eventId, $pageSize) {
		return ceil($this->db->get_var("SELECT COUNT(*) FROM $this->reg_table_name ".
			"WHERE event_id = " . ((int)$eventId)) / $pageSize);
	}
	
	public function getRegistrationsPage($eventId, $page, $pageSize) {
		if ($page < 1) $page = 1;
		return $this->db->get_results("SELECT * FROM $this->reg_table_name ".
			"WHERE event_id = " . ((int)$eventId) . " " .
			"ORDER BY order_time DESC LIMIT $pageSize OFFSET " . (($page-1) * $pageSize));
	}
	
	public function deleteRegistration($regId) {
		return $this->db->delete($this->reg_table_name, [ 'id' => $regId ]);
	}



	public function getRoom($room_id){
		return $this->db->get_row(
			"SELECT * FROM $this->rooms_table_name WHERE id = " . ((int)$room_id)
		);
	}

	public function getRooms($room_list_id){
		return $this->db->get_results(
			"SELECT * FROM $this->rooms_table_name WHERE room_list_id = ". ((int)$room_list_id) . " ORDER BY room_name"
		);
	}

	public function addRoom($room_list_id, $room_name, $max_tickets){
		if($room_list_id <= 0 || $max_tickets <= 0 || !is_numeric($max_tickets) || !is_numeric($room_list_id) ){
			return false;
		}

		return $this->db->insert($this->rooms_table_name, ['room_list_id' => $room_list_id , 'room_name' => $room_name , 'max_tickets' => $max_tickets]);
	}

	public function updateRoom($room_id, $room_name, $max_tickets){
		if(!is_numeric($room_id) || !is_numeric($max_tickets)){
			return false;
		}

		return $this->db->update($this->rooms_table_name, [
				'room_name' =>  $room_name,
				'max_tickets' => $max_tickets
		] ,
		[
			'id' => $room_id
		]);
	}

	public function deleteRoom($roomId){
		if(!$this->verifyCanDeleteRoom($roomId)){
			return false;
		}

		return $this->db->delete($this->rooms_table_name, ['id' => $roomId]);
	}

	public function verifyCanDeleteRoom($roomId){
		if($this->db->get_var(
			"SELECT COUNT(*) FROM $this->reg_table_name WHERE id = " . ((int)$roomId)) > 0){
				return false;
			}
		return true;
	}

	public function getRoomsList($listId){
		return $this->db->get_row(
			"SELECT * FROM $this->roomlists_table_name WHERE id = " . ((int)$listId)
		);
	}

	public function getRoomLists($eventId){
		return $this->db->get_results(
			"SELECT * FROM $this->roomlists_table_name WHERE event_id = ". ((int)$eventId)
		);
	}

	public function addRoomList($roomlist_event_id, $room_list_name) {
		if(!is_numeric($roomlist_event_id)){
			error_log("Paygate: invalid event for add room list ('$roomlist_event_id')");
			return false;
		}

		if (!$room_list_name) {
			error_log("Paygate: invalid empty room list name!");
			return false;
		}

		return $this->db->insert($this->roomlists_table_name, ['room_list_name' => $room_list_name , 'event_id' => $roomlist_event_id]);
	}

	public function deleteRoomList($listId){
		foreach($this->getRooms($listId) as $room){
			if(!$this->verifyCanDeleteRoom($room->id)){
				return false;
			}
		}

		if ($this->db->delete($this->rooms_table_name, ['room_list_id' => $listId]) == false)
			return false;
		return $this->db->delete($this->roomlists_table_name , ['id' => $listId]);
	}

	public function updateRoomsList($listId, $roomlist_name){
		if(empty($roomlist_name)){
			return false;
		}

		return $this->db->update($this->roomlists_table_name, [
				'room_list_name' =>  $roomlist_name,
		] ,
		[
			'id' => $listId
		]);
	}
	
	public function roomListForTicket($eventId, $ticketType) {
		return $this->db->get_row($this->db->prepare("
			SELECT rl.* FROM $this->roomlists_table_name AS rl
			INNER JOIN $this->ticket_roomlist_name AS trl ON trl.room_list_id = rl.id
			WHERE trl.event_id = %d AND trl.ticket_type = %s",
			[
				$eventId, $ticketType
			]));
	}

	public function setRoomListForTicket($eventId, $ticketType, $roomListId) {
		if ($roomListId === null) // delete
			return $this->db->delete($this->ticket_roomlist_name, [
				'event_id' => $eventId,
				'ticket_type' => $ticketType,
			]);
		if (empty($this->roomListForTicket($eventId, $ticketType)))
			return $this->db->insert($this->ticket_roomlist_name, [
				'event_id' => $eventId,
				'ticket_type' => $ticketType,
				'room_list_id' => $roomListId,
			]);
		return $this->db->update($this->ticket_roomlist_name, [
			'room_list_id' => $roomListId,
		], [
			'event_id' => $eventId,
			'ticket_type' => $ticketType,
		]);
	}

	public function listRoomsForTicket($eventId, $ticketType) {
		return $this->db->get_results("
			SELECT r.id, r.room_name, r.max_tickets FROM $this->rooms_table_name AS r
			INNER JOIN $this->roomlists_table_name AS rl ON r.room_list_id = rl.id
			INNER JOIN $this->ticket_roomlist_name AS trl ON trl.room_list_id = rl.id
				AND trl.ticket_type = '" . esc_sql($ticketType) . "'");
	}


	public function getRoomAvailableTickets($roomId){
		$pending_max_time_sec = 3600;

		$soldTickets = $this->db->get_var(
			"SELECT COUNT(*) FROM $this->reg_table_name WHERE id = $roomId AND (status = 'complete' OR (status = 'pending' AND order_time > UNIX_TIMESTAMP(NOW() - $pending_max_time_sec)))"  );

		$maxTickets = $this->db->get_var("SELECT max_tickets FROM $this->rooms_table_name WHERE id = " . $roomId);

		if ($maxTickets > $soldTickets){
			return $maxTickets - $soldTickets;
		} else {
			return 0;
		}
	}

	public function getRoomIdByName($roomName){
		$roomId = $this->db->get_var("SELECT id FROM $this->rooms_table_name WHERE room_name = '" . esc_sql($roomName) . "'");

		if(!$roomId){
			return false;
		}

		return $roomId;
	}
}
