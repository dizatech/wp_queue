<?php

namespace Dizatech\WpQueue;

use WP_CLI;

/**
 * Runs wp_queue queued jobs
 */ 
class QueueWorker{
	/**
	 * Runs wp_queue queued jobs
	 *
	 * ## OPTIONS
	 *
	 * [--count=<integer>]
	 * : number of queued job to fetch from database in each iteration.
	 * default: 5
	 * 
	 * [--sleep=<integer>]
	 * : interval between each two consecutive iterations
	 * default: 60
	 * 
	 * [--retries=<integer>]
	 * : number of retries before considering a job as failed and moving it to failed jobs table
	 * default: 3
	 * 
	 * [--retry_interval=<integer>]
	 * : interval between two consecutive tries of a certain job
	 * default: 120
	 * 
	 *
	 * ## EXAMPLES
	 *
	 *	wp worker work
	 * 	wp worker work --count=5 --sleep=30 --retries=2 --retry_interval=300
	 *
	 * @when after_wp_load
	 */
	public function work($args, $assoc_args)
	{
		set_time_limit(-1);
		date_default_timezone_set('UTC');
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'count'				=> 5,
				'sleep'				=> 60,
				'retries'			=> 3,
				'retry_interval'	=> 120
			)
		);
		
		global $wpdb;
		while(1){
			$sql = "SELECT * FROM
				{$wpdb->prefix}queue_jobs
				WHERE retry_at <= NOW()
				ORDER BY retry_at ASC LIMIT 0, %d";
			$sql = $wpdb->prepare( $sql, $assoc_args['count'] );
			$job_records = $wpdb->get_results( $sql );

			if( !empty( $job_records ) ){
				foreach( $job_records as $job_record ){
					WP_CLI::line( date('Y-m-d H:i:s') . "    Doing job {$job_record->id} ...");
					$job = new Job($job_record, $assoc_args['retries'], $assoc_args['retry_interval']);
					$job->do();
				}
			}

			sleep($assoc_args['sleep']);
		}
	}

	public function retry($args)
	{
		date_default_timezone_set('UTC');
		$id = $args[0] ?? null;
		
		global $wpdb;
		$sql = "SELECT * FROM {$wpdb->prefix}queue_failed_jobs";
		if( $id > 0 ){
			$sql .= $wpdb->prepare( " WHERE id=%d", $id );
		}
		$jobs = $wpdb->get_results($sql);
		foreach( $jobs as $job ){
			$data = [
				'job'               => $job->job,
				'payload'           => $job->payload,
			];
			$format = [
				'job'               => '%s',
				'payload'           => '%s'
			];
			$wpdb->insert(
				"{$wpdb->prefix}queue_jobs",
				$data,
				$format
			);

			$wpdb->delete(
				"{$wpdb->prefix}queue_failed_jobs",
				['id' => $job->id],
				['id' => '%s']
			);
		}
	}
}