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

use Dizatech\WpQueue\Job;
use Dizatech\WpQueue\SyncProductJob;

require_once __DIR__ . '/vendor/autoload.php';

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
    if( class_exists('Dizatech\WpQueue\QueueWorker') ){
        WP_CLI::add_command( 'worker', 'Dizatech\WpQueue\QueueWorker' );
    }
    if( class_exists('Dizatech\WpQueue\SyncProductCommand') ){
        WP_CLI::add_command( 'sync', 'Dizatech\WpQueue\SyncProductCommand' );
    }
}

add_action('transition_post_status', 'wp_queue_sync_product', 9999, 3);
function wp_queue_sync_product($new_status, $old_status, $post){
    if( $post->post_type == 'product' && $old_status != $new_status && $new_status == 'publish' ){
        Job::dispatch(SyncProductJob::class, ['product_id' => $post->ID]);
    }
    elseif( $post->post_type == 'product_variation' ){
        Job::dispatch(SyncProductJob::class, ['product_id' => $post->ID]);
    }
}