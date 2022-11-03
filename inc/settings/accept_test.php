<?php

class PayGateAcceptTestSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('accept-test', __('Allow test purchases', 'isrp-event-paygate'), $section,
			static::SETTING_TYPE_BOOLEAN, __('ֻCheck this box to allow test payments on payment processors that support production test accounts.'));
	}
	
}
