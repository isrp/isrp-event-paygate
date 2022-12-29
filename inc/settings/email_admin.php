<?php

class PayGateEmailAdminSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('email-admin', __('E-Mail address to BCC on invoices', 'isrp-event-paygate'), $section);
	}
	
}
