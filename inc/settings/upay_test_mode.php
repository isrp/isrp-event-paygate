<?php

class PayGateUPayTestModeSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('upay-test-mode', __("Use UPay's test system", 'isrp-event-paygate'), $section,
			static::SETTING_TYPE_BOOLEAN, __("To perform acceptance testing, check this box to send all payments to UPay test system, ".
			"That does not require real credit cards.", 'isrp-event-paygate'));
	}
	
}

return ['PayGateUPayTestModeSetting', 'paygate_section_processor'];
