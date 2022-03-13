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

register_activation_hook(__FILE__, function (){
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `{$wpdb->prefix}queue_jobs` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `job` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
        `attempts` tinyint(4) UNSIGNED NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `retry_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) {$charset_collate};";
    $sql .= "CREATE TABLE `{$wpdb->prefix}queue_failed_jobs` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `job` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
        `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
        `exception` text COLLATE utf8mb4_unicode_ci NOT NULL,
        PRIMARY KEY (`id`)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});
if( class_exists('WP_CLI') ){
    WP_CLI::add_command( 'worker', 'Dizatech\WpQueue\QueueWorker' );
    WP_CLI::add_command( 'sync', 'Dizatech\WpQueue\SyncProductCommand' );
}