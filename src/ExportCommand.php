<?php

namespace Dizatech\WpQueue;

use WP_Query;

class ExportCommand{
    /**
	 * generates a list of all products and stores in an excel file
	 *
	 * ## EXAMPLES
	 *
	 *	wp export products
	 *
	 * @when after_wp_load
	 */
    public function products($args, $assoc_args)
    {
        set_time_limit(-1);
        date_default_timezone_set('UTC');
        $mode = isset($args[0]) ? $args[0] : 'stock';    
        $args = [
            'post_type'         => ['product','product_variation'],
            'posts_per_page'    => -1,
            'post_status'       => ['publish', 'inherit'],
            'orderby'           => 'title',
            'order'             => 'ASC'
        ];
        $q = new WP_Query($args);

        if ($mode == 'stock') {
            $this->ExportforStock($q);
        }
        else if ($mode == 'codes') {
            $this->ExportAllCodes($q);
        }
        else if ($mode == 'portal') {
            $this->ExportforPortal($q);
        }
    }

    protected function ExportAllCodes($q)
    {
        $fp = fopen(ABSPATH.'/all_codes.csv', 'w');
        while( $q->have_posts() ){
            $q->the_post();
            $product = wc_get_product( get_the_ID() );
            if (!$product) {
                continue; //not valid
            }

            if (in_array( $product->get_type(), ['simple', 'variable', 'variation'])) {
                if (in_array( $product->get_type(), ['simple', 'variable'] )) {
                    $title = trim( $product->get_title() );
                } else {
                    $parent_id = $product->get_parent_id();
                    if ($parent_id == 0) {
                        continue; //not valid
                    }
                    $parent = wc_get_product($parent_id);
                    if (!$parent) {
                        continue; //not valid
                    }
                    $title = trim( $parent->get_title() ) . " | " . implode(" | ", $product->get_variation_attributes() );
                    $title = rtrim($title, " | ");
                }

                fputcsv(
                    $fp,
                    [
                        "1/{$product->get_id()}",
                        urldecode($title)
                    ]
                );
            }            
        }
        fclose($fp);

        \WP_CLI::success("Export completed successfuly!");
    }

    protected function ExportforStock($q)
    {
        $fp = fopen(ABSPATH.'/products.csv', 'w');
        while( $q->have_posts() ){
            $q->the_post();
            $product = wc_get_product( get_the_ID() );
            if (!$product) {
                continue; //not valid
            }

            if (in_array( $product->get_type(), ['simple', 'variable', 'variation'])) {
                if ($product->is_type('variation')) {
                    $sepidar_sale_amount = intval( get_post_meta($product->get_id(),'_sepidar_sale_amount',true) );
                    if ($sepidar_sale_amount > 0) {
                        continue; //sold from parent
                    }
                }
                $unit = get_sepidar_unit($product->get_id());
                if ($product->is_type('variable') && $unit == 'عدد') {
                    continue; //unavailable parent
                }

                if (in_array( $product->get_type(), ['simple', 'variable'] )) {
                    $title = trim( $product->get_title() );
                } else {
                    $parent_id = $product->get_parent_id();
                    if ($parent_id == 0) {
                        continue; //not valid
                    }
                    $parent = wc_get_product($parent_id);
                    if (!$parent) {
                        continue; //not valid
                    }
                    $title = trim( $parent->get_title() ) . " | " . implode(" | ", $product->get_variation_attributes() );
                    $title = rtrim($title, " | ");
                }

                fputcsv(
                    $fp,
                    [
                        "1/{$product->get_id()}",
                        urldecode($title),
                        $unit
                    ]
                );
            }            
        }
        fclose($fp);

        \WP_CLI::success("Export completed successfuly!");
    }

    protected function ExportforPortal($q)
    {
        $fp = fopen(ABSPATH.'/sync.csv', 'w');
        while( $q->have_posts() ){
            $q->the_post();
            $product = wc_get_product( get_the_ID() );
            if (!$product) {
                continue; //not valid
            }

            if (in_array( $product->get_type(), ['simple', 'variable', 'variation'])) {
                if ($product->is_type('variation')) {
                    $sepidar_sale_amount = intval( get_post_meta($product->get_id(),'_sepidar_sale_amount',true) );
                    if ($sepidar_sale_amount > 0) {
                        continue; //sold from parent
                    }
                }
                $unit = get_sepidar_unit($product->get_id());

                if (in_array( $product->get_type(), ['simple', 'variable'] )) {
                    $title = trim( $product->get_title() );
                    $product_id = $product->get_id();
                    $variation_id = 0;
                } else {
                    $parent_id = $product->get_parent_id();
                    if ($parent_id == 0) {
                        continue; //not valid
                    }
                    $parent = wc_get_product($parent_id);
                    if (!$parent) {
                        continue; //not valid
                    }
                    $title = trim( $parent->get_title() ) . " | " . implode(" | ", $product->get_variation_attributes() );
                    $title = rtrim($title, " | ");

                    $product_id = $parent_id;
                    $variation_id = $product->get_id();
                }

                fputcsv(
                    $fp,
                    [
                        $product_id,
                        $variation_id,
                        urldecode($title),
                    ]
                );
            }            
        }
        fclose($fp);

        \WP_CLI::success("Export completed successfuly!");
    }
}
