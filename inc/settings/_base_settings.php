<?php

class PayGateSettingsBase {
	
	const SETTING_TYPE_STRING = 1;
	const SETTING_TYPE_BOOLEAN = 2;
	const SETTING_TYPE_SELECT = 3;
	
	protected $value = null;
	protected $name;
	protected $title;
	protected $setting_name;
	protected $section;
	protected $type;
	protected $description;
	protected $width = 20;
	protected $values = [];
	
	protected function __construct($name, $title, $section, $type = null,
			$description = null) {
		$this->name = $name;
		$this->title = $title;
		$this->setting_name = 'paygate-' . $name;
		$this->section = $section;
		
		$this->type = is_null($type) ? static::SETTING_TYPE_STRING : $type;
		$this->description = $description;
	}
	
	public function register() {
		register_setting('paygate_setting_group', 'paygate-' . $this->name);
		add_settings_field($this->name, __($this->title, 'isrp-event-paygate'),
			[ $this, 'display' ], 'paygate-settings', $this->section);
	}
	
	public function display() {
		$this->value = get_option($this->setting_name);
		switch ($this->type) {
			case static::SETTING_TYPE_STRING: return $this->display_string();
			case static::SETTING_TYPE_BOOLEAN: return $this->display_boolean();
			case static::SETTING_TYPE_SELECT: return $this->display_select();
			default: return;
		}
	}
	
	public function getValue() {
		if (is_null($this->value))
			$this->value = get_option($this->setting_name);
		return $this->value;
	}
	
	protected function display_string() {
		$this->print_tag('input', [
			'type' => 'text',
			'id' => $this->name . "test",
			'name' => $this->setting_name,
			'value' => isset($this->value) ? $this->value : '',
			'style' => "width: {$this->width}em; direction: ltr;",
		]);
	}
	
	protected function display_boolean() {
		$this->print_tag('input', [
			'type' => 'checkbox',
			'id' => $this->name,
			'name' => $this->setting_name,
			'value' => '1',
			'checked' => (bool)$this->value,
			'title' => $this->description,
		]);
		if ($this->description)
			echo " <label for='{$this->name}'>".__($this->description, 'isrp-event-paygate')."</label>";
	}
	
	protected function display_select() {
		$this->print_tag('select', [
			'id' => $this->name,
			'name' => $this->setting_name,
			'title' => $this->description,
		], function() {
			print '<option>' . __('Select option') . '</option>';
			foreach ($this->values as $value => $description) {
				if (is_numeric($value))
					$value = $description;
				$is_selected = ($value == $this->value);
				print '<option value="' . $value . '" ' . ($is_selected ? 'selected' : '') . '>' . $description . '</option>';
			}
		});
		if ($this->description)
			echo " <label for='{$this->name}'>".__($this->description, 'isrp-event-paygate')."</label>";
	}
	
	protected function print_tag($name, $attr, $contentClosure = null) {
		echo '<';
		echo $name;
		echo ' ';
		foreach ($attr as $name => $content) {
			if (is_null($content) or $content===false) continue;
			echo " $name";
			if (is_bool($content)) continue;
			echo "=\"" . esc_attr($content) . '"';
		}
		if ($contentClosure == null) {
			echo '/>';
		} else {
			echo '>';
			$contentClosure();
			echo '</'.$name.'>';
		}
	}
}
