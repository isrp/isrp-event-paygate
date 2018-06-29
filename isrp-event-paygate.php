<?php
/*
 Plugin Name: ISRP Event PayGate
 Plugin URI:  http://github.com/guss77/isrp-event-paygate
 Description: Plugin for the Israeli Society of Roleplayers' day-event payments system
 Version:     1.0.0
 Author:      Oded Arbel
 Author URI:  https://github.com/guss77/isrp-event-paygate
 License:     GPL2
 License URI: https://www.gnu.org/licenses/gpl-2.0.html
 Domain Path: /languages
 Text Domain: paygate
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once __DIR__.'/lib/blockspring.php';

require_once __DIR__.'/inc/paygate.php';

$paygate_ref = new PayGate(__FILE__);
