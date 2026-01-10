<?php

class PayGateUPayAccountSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('upay-account', __('UPay account identifier', 'isrp-event-paygate'), $section);
	}
	
}

return ['PayGateUPayAccountSetting','paygate_section_processor'];
