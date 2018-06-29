<?php

class PayGateDragonMembersURLSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('dragon-members-url', 'כתובת רשימת חברי מועדון דרקון', $section);
		$this->width = 40;
	}
	
}
