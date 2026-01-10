<?php

class PayGatePaymentProviderSetting extends PayGateSettingsBase {
	
	public function __construct($section) {
		parent::__construct('payment-provider', __('Payment provider', 'isrp-event-paygate'), $section, static::SETTING_TYPE_SELECT);
		$this->values = [
			'upay' => __("UPay"),
			'pelepay' => __("PelePay"),
		];
	}
	
}

return ['PayGatePaymentProviderSetting','paygate_section_processor'];
