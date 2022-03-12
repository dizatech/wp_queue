<?php
/**
 * Plugin Name:       Wp Queue
 * Plugin URI:        https://github.com/dizatech/wp_queue
 * Description:       A wordpress plugin for managing queues
 * Version:           0.9
 * Author:            Dizatech
 * Author URI:        https://dizatech.com/
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

require_once 'vendor/autoload.php';

register_activation_hook(__FILE__, ['Dizatech\WpQueue\Database', 'init']);