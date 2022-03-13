<?php

namespace Dizatech\WpQueue;

use Exception;

class SyncProductJob implements JobInterface{
    function handle($payload)
    {
        //A: get token
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL             => SEPIDAR_API_URL . '/token',
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => json_encode([
                'username'          => SEPIDAR_API_USERNAME,
                'password'          => SEPIDAR_API_PASSWORD
            ]),
            CURLOPT_HTTPHEADER      => ['Content-Type:application/json'],
            CURLOPT_RETURNTRANSFER  => true
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response);
        $token = $response->token;

        //B: send request
        $product = wc_get_product( $payload->product_id );
        if( $product && in_array( $product->get_type(), ['simple', 'variation'] ) ){
            $product_id = $product->get_id();
            if( $product->get_type() == 'simple' ){
                $title = $product->get_title();
            }
            else{
                $parent_id = $product->get_parent_id();
                $parent = wc_get_product($parent_id);
                $title = $parent->get_title() . " | " . implode(" | ", $product->get_variation_attributes() );
                $title = rtrim($title, " | ");
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL             => SEPIDAR_API_URL . '/RegisterItem',
                CURLOPT_HEADER          => true,
                CURLOPT_POST            => true,
                CURLOPT_POSTFIELDS      => json_encode([
                    'code'              => "1/{$product_id}",
                    'title'             => $title,
                    'unit'              => "عدد",
                    'type'              => 1,
                    'stockcode'         => 1,
                    'canHaveTracing'    => false
                ]),
                CURLOPT_HTTPHEADER      => [
                    'Authorization:Bearer ' . $token,
                    'Content-Type:application/json'
                ],
                CURLOPT_RETURNTRANSFER  => true
            ]);
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if( $status != 200 ){
                throw new Exception("Server reponded: " . PHP_EOL . $response);
            }
        }
    }
}