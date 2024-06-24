<?php
namespace Dizatech\WpQueue;

use Exception;

class SyncOrderJob implements JobInterface{
    function handle($payload){
        $data = $this->get_order_info($payload->order_id);
        //$dir = realpath(dirname(__FILE__)).'/';
        //$file = $dir."debug_syncOrderJob.txt";
        //file_put_contents($file, json_encode($data), FILE_APPEND);
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', PORTAL_URL.'/api/order/sync_order', [
            'headers'   => [
                'Authorization' => 'Bearer '.TAMADKALA_API_TOKEN,
                'Accept'         => 'application/json'
            ],
            'body' => json_encode($data)
        ]);

    }

    function get_order_info($order_id){
        global $wpdb;
        $order = wc_get_order($order_id);
        $fees = $order->get_items('fee');
        $info = [];
        $info['id'] = $order->get_id();
        $order_data = $order->get_data();
        $info['status'] = $order_data['status'];
        $info['currency'] = $order_data['currency'];
        $info['date_created'] = $order_data['date_created'];
        $info['date_modified'] = $order_data['date_modified'];
        $info['discount_total'] = $order_data['discount_total'];
        $info['discount_tax'] = $order_data['discount_tax'];
        $info['shipping_total'] = $order_data['shipping_total'];
        $info['total'] = $order_data['total'];
        $info['customer_id'] = $order_data['customer_id'];
        $info['billing_details'] = $order_data['billing'];
        $info['billing_details']['city_id'] = get_post_meta($order->id,'_billing_city_id',true);
        $info['shipping_details'] = $order_data['shipping'];
        $info['mobile'] = get_user_meta( $order_data['customer_id'], 'mobile', TRUE );
        $info['payment_method'] = $order_data['payment_method'];
        $info['payment_method_title'] = $order_data['payment_method_title'];
        $info['customer_note'] = $order->customer_note;
        $info['customer_nid'] = get_user_meta( $order_data['customer_id'], 'nid', TRUE );
        $info['organization'] = get_user_meta( $order_data['customer_id'], 'organization', TRUE );
        $info['company_nid'] = get_user_meta( $order_data['customer_id'], 'ilenc', TRUE );
        $info['company_financial_code'] = get_user_meta( $order_data['customer_id'], 'financial_code', TRUE );
        $info['request_official_invoice'] = get_post_meta($order->get_id(),'request_official_invoice',true);
        $info['info_confirmed'] = get_post_meta($order->get_id(),'_info_confirmed',true) ?? 0;
        if ($info['request_official_invoice']) {
            if (get_post_meta($order->get_id(),'_invoice_type',true) == 'LegalEntity') {
                $info['invoice_type'] = 'LegalEntity';
                $info['company_name'] = get_post_meta($order->get_id(),'_company_name',true);
                $info['company_address'] = get_post_meta($order->get_id(),'_company_address',true);
                $info['company_postcode'] = get_post_meta($order->get_id(),'_company_postcode',true);
                $info['company_province'] = get_post_meta($order->get_id(),'_company_province',true);
                $info['company_city'] = get_post_meta($order->get_id(),'_company_city',true);
            } else {
                $info['invoice_type'] = 'Individual';
            }
        }

        $info['line_items'] =[];
        foreach( $order_data['line_items'] as $line_item ){
            $line_item_details = [];
            $line_item_details = $this->get_line_item($line_item);
            $info['line_items'][] = $line_item_details;
        }
        $info['fees'] = $this->get_fees($fees);
        $info['address'] = trim( $order_data['billing']['address_1']);
        $info['postal_code'] = $order_data['billing']['postcode'];
        $info['phone'] = $order_data['billing']['phone'];
        $shipping_methods = $order->get_items( 'shipping' );
        $info['shipping_method'] = $this->get_shipping_methods($shipping_methods);
        $info['shipping_delivery_key'] = get_post_meta( $info['id'], 'tamad_shipping_delivery_time_key', TRUE );
        $info['shipping_delivery_time'] = get_post_meta( $info['id'], 'tamad_shipping_delivery_time', TRUE );
        $info['payments'] = $this->get_payments($order_id);
        $info['coupons'] = $this->get_coupons($order);
        return $info;
    }

    function tamadkala_get_product_type( $id )
    {
        if( $id == 1551 ){
            return 'analysis';
        }
        elseif(
            tamad_has_term( 13, 'product_cat', $id ) ||
            has_term( 13, 'product_cat', $id )
        ){
            return 'equipment';
        }
        elseif(
            tamad_has_term( 11, 'product_cat', $id ) ||
            has_term( 11, 'product_cat', $id ) ||
            tamad_has_term( 754, 'product_cat', $id ) ||
            has_term( 754, 'product_cat', $id )
        ){
            return 'chemicals';
        }
        elseif(
            tamad_has_term( 12, 'product_cat', $id ) ||
            has_term( 12, 'product_cat', $id )
        ){
            return 'lab_dishes';
        }
        elseif(
            tamad_has_term( 1654, 'product_cat', $id ) ||
            has_term( 1654, 'product_cat', $id )
        ){
            return 'medlab';
        }
    
        return NULL;
    }

    function get_line_item($line_item)
    {
        $line_item_data = $line_item->get_data();
        if($line_item_data['variation_id']){
            $product = wc_get_product($line_item_data['variation_id']);
        }else{
            $product = wc_get_product($line_item_data['product_id']);
        }

        //set original data
        $line_item_details['original_product_id'] = $line_item_data['product_id'];
        $line_item_details['original_variation_id'] = $line_item_data['variation_id'];
        $line_item_details['original_quantity'] = $line_item_data['quantity'];
        $line_item_details['original_title'] = urldecode($line_item_data['name']);
        $line_item_details['original_package_amount'] = 1;

        $line_item_details['unit'] = get_sepidar_unit($product->get_id());
        if($product->is_type('simple') || $product->is_type('variable')){
            $sepidar_sale_amount = 0;

            if ( $product->is_type('variable') ) {
                foreach ($product->get_children() as $child_id) {
                    $is_original_package = get_post_meta($child_id, 'original_package', true);
                    if ($is_original_package=='yes') {
                        $line_item_details['original_package_amount'] = get_post_meta($child_id,'_sepidar_sale_amount',true);
                        break;
                    }
                }
            }
        }else{
            $line_item_details['sepidar_sale_amount'] = get_post_meta($product->get_id(),'_sepidar_sale_amount',true);
            $sepidar_sale_amount = get_post_meta($product->get_id(),'_sepidar_sale_amount',true)!='' ? get_post_meta($product->get_id(),'_sepidar_sale_amount',true) : 0;

            $parent = wc_get_product($product->get_parent_id());
            foreach ($parent->get_children() as $child_id) {
                $is_original_package = get_post_meta($child_id, 'original_package', true);
                if ($is_original_package=='yes') {
                    $line_item_details['original_package_amount'] = get_post_meta($child_id,'_sepidar_sale_amount',true);
                    break;
                }
            }
        }

        $line_item_details['product_id'] = $line_item_data['product_id'];
        if($line_item_data['variation_id'] > 0 && $sepidar_sale_amount){
            $product = wc_get_product($line_item_data['product_id']);
            $line_item_details['name'] = $product->get_title();
            $line_item_details['variation_id'] = 0;
            $line_item_details['unit'] = get_sepidar_unit($product->get_id());
        }else{
            $line_item_details['name'] = urldecode( $line_item_data['name'] );
            $line_item_details['variation_id'] = $line_item_data['variation_id'];
        }

        $terms = get_the_terms($line_item_data['product_id'], 'product_cat');
        $cats =[];
        $primary_term_id = yoast_get_primary_term_id('product_cat',$line_item_data['product_id']);
        foreach($terms as $term){
            $is_main = $term->term_id == $primary_term_id ? 1: 0;
            $cats[]=['term_id'=>$term->term_id,'title'=>$term->name,'is_main'=>$is_main];
        }
        $line_item_details['categories'] = $cats;
        if( isset( $line_item_data['meta_data'] ) && count( $line_item_data['meta_data'] ) > 0 ){
            $meta_data = array();
            foreach( $line_item_data['meta_data'] as $meta_data_item ){
				$meta_data_item_data = $meta_data_item->get_data();
				if( $meta_data_item_data['key'] != 'مقدار مورد نیاز' )
					$meta_data[ $meta_data_item_data['key'] ] = $meta_data_item_data['value'];
			}
            if( isset( $meta_data['on_special_offer'] ) ){
                $line_item_details['on_special_offer'] = json_decode( $meta_data['on_special_offer'] );
                unset( $meta_data['on_special_offer'] );
            }
            if( $line_item_details['variation_id'] && !empty( $meta_data ) ){
                $line_item_details['name'] .= "(" . implode( "-", $meta_data ) . ")";
            }
        }

        $package_count = get_post_meta( $line_item_details['product_id'], 'package_count', TRUE );
        $variation_package_product_count = get_post_meta( $line_item_details['variation_id'], 'variation_package_product_count', TRUE );
        if( $variation_package_product_count > 0 )
            $package_count = $variation_package_product_count;

        $line_item_details['package_count'] = intval( $package_count );

        if ($product){
            $line_item_details['sku'] = $product->get_sku();
            $line_item_details['catalog_no'] = get_post_meta( $product->get_id(),'catalog_no', TRUE);
        }else{
            $line_item_details['sku'] = "";
            $line_item_details['catalog_no'] = "";
        }
        $line_item_details['product_type'] = $this->tamadkala_get_product_type( $line_item_details['product_id'] );
        if($sepidar_sale_amount){
            $line_item_details['quantity'] = $line_item_data['quantity']*$sepidar_sale_amount;
        }else{
            $line_item_details['quantity'] = $line_item_data['quantity'];
        }
        
        $line_item_details['subtotal'] = $line_item_data['subtotal'];
        $line_item_details['total'] = $line_item_data['total'];

        return $line_item_details;
    }

    function get_fees($fees)
    {
        foreach( $fees as $fee ){
            $fee_data = $fee->get_data();
            $info[] = array(
                'name'		=> $fee_data['name'],
                'fee_total'	=> $fee_data['total']
            );
        }

        return $info;
    }

    function get_shipping_methods($shipping_methods)
    {
        $info = "";
        if( is_array( $shipping_methods ) && !empty( $shipping_methods) ){
            $shipping_method = $shipping_methods[ array_keys( $shipping_methods )[0] ];
            $info = $shipping_method->get_name();
        }
        return $info;
    }

    function get_payments($order_id)
    {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}payment_requests WHERE order_id = %d AND status = 'completed'";
        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, $order_id )
        );
        $info = [];
        if( !empty( $results ) ){
            foreach( $results as $result ){
                $info[] = [
                    'payment_date' => substr( $result->payment_time, 0, 10),
                    'payment_method' => $result->gateway,
                    'amount' => $result->amount / 10, //Rial to Toman
                    'refno' => $result->ref
                ];
            }
        }

        return $info;
    }

    function get_coupons($order)
    {
        $coupons = $order->get_coupons();
        $info = [];
        foreach( $coupons as $coupon ){
            $coupon_data = $coupon->get_meta_data()[0]->get_data();
            $coupon_free_shipping = $coupon_data['value']['free_shipping'];
            $free_shipping = FALSE;
            $shipping_methods = $order->get_shipping_methods();
            if( !empty( $shipping_methods ) ){
                foreach( $shipping_methods as $shipping_method ){
                    if(
                        $shipping_method->get_method_title() == 'حمل و نقل رایگان' &&
                        $shipping_method->get_total() == 0 &&
                        $coupon_free_shipping
                    ){
                        $free_shipping = TRUE;
                        break;
                    }
                }
            }
            
            $info[] = [
                'code'			=> $coupon->get_code(),
                'amount'		=> $coupon->get_discount(),
                'free_shipping'	=> $free_shipping
            ];
        }

        return $info;
    }
}
