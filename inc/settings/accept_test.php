<?php

class PayGateAcceptTestSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('accept-test', __('For testing: accept all purchase attempts', 'isrp-event-paygate'), $section,
			static::SETTING_TYPE_BOOLEAN, __("To perform acceptance testing, check this box to treat ".
				"payment processor failures as success. Don't forget to disable this before start production service.", 'isrp-event-paygate'));
	}
	
}
