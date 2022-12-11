<?php

class PayGateClubMembersURLSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('club-members-url', __('ֹURL for club membership API', 'isrp-event-paygate'), $section);
		$this->width = 40;
	}
	
}
