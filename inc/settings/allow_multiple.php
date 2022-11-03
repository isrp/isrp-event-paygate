<?php

class PayGateAllowMultipleSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('allow-multiple', __('Shopping cart', 'isrp-event-paygate'), $section,
			static::SETTING_TYPE_BOOLEAN, __('Display interface to allow purchases of multiple tickets at once.', 'isrp-event-paygate'));
	}
	
}
