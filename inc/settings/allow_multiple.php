<?php

class PayGateAllowMultipleSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('allow-multiple', 'עגלת קניות', $section,
			static::SETTING_TYPE_BOOLEAN, 'האם להציג ממשק של עגלת קניות המאפשר רכישה של מספר כרטיסים ביחד');
	}
	
}
