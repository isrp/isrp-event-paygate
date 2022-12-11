<?php

class PayGatePelePayAccountSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('pelepay-account', __('Pelepay account identifier', 'isrp-event-paygate'), $section);
	}
	
}
