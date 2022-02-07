<?php

use Dizatech\WpQueue\Job;

if( PHP_SAPI !== 'cli' || isset( $_SERVER['HTTP_USER_AGENT'] ) ){
    die('This script should only be used in command line');
}
set_time_limit(-1);

$base_dir = __DIR__;
while( !file_exists( $base_dir . '/wp-load.php' ) ){
    $new_base_dir = dirname( $base_dir );
    if( $base_dir == $new_base_dir ){
        die('Unable to load wordpress!');
    }

    $base_dir = $new_base_dir;
}
require_once $base_dir . '/wp-load.php';
date_default_timezone_set('Asia/Tehran');

$args = getopt('', ['count:', 'sleep:', 'retries:', 'retry_interval:']);
$defaults = [
    'count'             => 1,
    'sleep'             => 60,
    'retries'           => 3,
    'retry_interval'    => 120
];
$args = array_merge($defaults, $args);
while(1){
    $sql = "SELECT * FROM
        {$wpdb->prefix}queue_jobs
        WHERE retry_at <= NOW()
        ORDER BY created_at DESC LIMIT 0, %d";
    $sql = $wpdb->prepare( $sql, $args['count'] );
    $job_records = $wpdb->get_results( $sql );

    if( empty( $job_records ) ){
        sleep($args['sleep']);
    }
    else{
        foreach( $job_records as $job_record ){
            $job = new Job($job_record, $args['retries'], $args['retry_interval']);
            $job->do();
        }
    }
}