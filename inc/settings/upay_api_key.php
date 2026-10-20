<?php

class PayGateUPayAPIKeySetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('upay-api-key', __('UPay API key', 'isrp-event-paygate'), $section);
	}
	
}

return ['PayGateUPayAPIKeySetting','paygate_section_processor'];
