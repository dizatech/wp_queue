<?php

namespace Dizatech\WpQueue;

use WP_Query;

/**
 * Syncs woocommerce products to Sepidar API via Job/Queue
 */ 
class SyncProductCommand{
    /**
	 * Syncs woocommerce products to Sepidar API via Job/Queue 
	 *
	 * ## EXAMPLES
	 *
	 *	wp sync products
	 *
	 * @when after_wp_load
	 */
    public function products()
    {
        set_time_limit(-1);
        $args = [
            'post_type'         => ['product','product_variation'],
            'posts_per_page'    => -1
        ];
        $q = new WP_Query($args);
        while( $q->have_posts() ){
            $q->the_post();
            Job::dispatch(SyncProductJob::class, ['product_id' => get_the_ID()]);
        }
    }
}