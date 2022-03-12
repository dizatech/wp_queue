<?php

namespace Dizatech\WpQueue;

class Database{
    public static function init()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE `{$wpdb->prefix}queue_jobs` (
            `id` bigint(20) NOT NULL,
            `job` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `attempts` tinyint(4) UNSIGNED NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `retry_at` timestamp NOT NULL DEFAULT current_timestamp()
        ) {$charset_collate}";
        $sql .= "CREATE TABLE `{$wpdb->prefix}queue_failed_jobs` (
            `id` bigint(20) NOT NULL,
            `job` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `payload` text COLLATE utf8mb4_unicode_ci NOT NULL,
            `exception` text COLLATE utf8mb4_unicode_ci NOT NULL
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}