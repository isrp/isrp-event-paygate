<?php

class PayGateAcceptTestSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('accept-test', 'אפשר רכישה מחשבון בדיקה', $section,
			static::SETTING_TYPE_BOOLEAN, 'סמן כדי לאפשר קניות במצב בדיקות של פלאפיי');
	}
	
}
