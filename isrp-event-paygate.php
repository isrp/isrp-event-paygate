<?php
/*
 Plugin Name: ISRP Event PayGate
 Plugin URI:  http://github.com/isrp/isrp-event-paygate
 Description: Plugin for the Israeli Society of Roleplayers' day-event payments system
 Version:     1.5.3
 Author:      Oded Arbel
 Author URI:  https://github.com/isrp/isrp-event-paygate
 License:     GPLv2
 License URI: https://www.gnu.org/licenses/gpl-2.0.html
 Domain Path: /languages
 Text Domain: isrp-event-paygate
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define('ISRP_EVENT_PAYGATE_DIR', basename(dirname(__FILE__)));
define('ISRP_EVENT_PAYGATE_URL', plugins_url(ISRP_EVENT_PAYGATE_DIR));

require_once __DIR__.'/inc/paygate.php';

$paygate_ref = new PayGate(__FILE__);
