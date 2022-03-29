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
        date_default_timezone_set('Asia/Tehran');
        $args = [
            'post_type'         => ['product','product_variation'],
            'posts_per_page'    => -1
        ];
        $q = new WP_Query($args);
        while( $q->have_posts() ){
            $q->the_post();
            $product = wc_get_product( get_the_ID() );
            if( in_array( $product->get_type(), ['simple', 'variation']) ){
                if( $product->get_type() == 'variation' ){
                    $parent_id = $product->get_parent_id();
                    if( $parent_id == 0 ){ //not valid
                        continue;
                    }
                }
                Job::dispatch(SyncProductJob::class, ['product_id' => $product->get_id()]);
            }            
        }
    }
}