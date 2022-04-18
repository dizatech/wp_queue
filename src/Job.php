<?php

namespace Dizatech\WpQueue;

use Exception;
use ReflectionClass;

class Job{
    protected $job;
    protected $handler;
    protected $retries;
    protected $retry_interval;

    public function __construct($job, $retries, $retry_interval)
    {
        date_default_timezone_set('UTC');
        $this->job = $job;
        $this->retries = $retries;
        $this->retry_interval = $retry_interval;
    }

    public function do()
    {
        try{
            $this->createHandler();
            $this->callHandler();
        }catch(Exception $e){
            if( intval( $this->job->attempts ) < $this->retries ){
                $this->retry();
            }
            else{
                $this->fail($e);
            }
            return;
        }

        $this->success();
    }

    private function createHandler()
    {
        if( class_exists($this->job->job) ){
            $this->handler = new $this->job->job();
        }
        else{
            throw new Exception('Class Not Found');
        }
    }

    private function callHandler()
    {
        $this->handler->handle( json_decode( $this->job->payload ) );
    }

    private function retry()
    {
        global $wpdb;
        $data = [
            'attempts'      => $this->job->attempts + 1,
            'retry_at'      => date('Y-m-d H:i:s', time() + $this->retry_interval)
        ];
        $format = [
            'attempts'      => '%d',
            'retry_at'      => '%s'
        ];
        $wpdb->update(
            "{$wpdb->prefix}queue_jobs",
            $data,
            ['id'           => $this->job->id],
            $format,
            ['id'           => '%d']
        );
    }

    private function fail(Exception $e)
    {
        global $wpdb;        
        $data = [
            'job'       => $this->job->job,
            'payload'   => $this->job->payload,
            'exception' => $e->getMessage(),
        ];
        $format = [
            'job'       => '%s',
            'payload'   => '%s',
            'exception' => '%s'
        ];
        $wpdb->insert(
            "{$wpdb->prefix}queue_failed_jobs",
            $data,
            $format
        );

        $wpdb->delete(
            "{$wpdb->prefix}queue_jobs",
            ['id' => $this->job->id],
            ['id' => '%s']
        );
    }

    private function success()
    {
        global $wpdb;
        $wpdb->delete(
            "{$wpdb->prefix}queue_jobs",
            ['id' => $this->job->id],
            ['id' => '%s']
        );
    }

    public static function dispatch($job, array $payload=[])
    {
        global $wpdb;
        date_default_timezone_set('UTC');
        $ref = new ReflectionClass($job);
        
        $data = [
            'job'               => $ref->getName(),
            'payload'           => json_encode($payload),
            'created_at'        => date('Y-m-d H:i:s'),
            'retry_at'          => date('Y-m-d H:i:s')
        ];
        $format = [
            'job'               => '%s',
            'payload'           => '%s',
            'created_at'        => '%s',
            'retry_at'          => '%s'
        ];
        $wpdb->insert(
            "{$wpdb->prefix}queue_jobs",
            $data,
            $format
        );
    }
}