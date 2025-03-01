<?php

namespace Dizatech\WpQueue;

use Exception;

class SendGiftCode implements JobInterface{
    function handle($payload)
    {
        $client = new \GuzzleHttp\Client();
        $newsletter_setting = newsletter_setting();
        $params = [
            'code'            => $payload->code,
            'messageid'       => uniqid()
        ];
        $params = json_encode($params);
        $data = [
            'template_id'       => 10,
            'sender_email_id'   => $newsletter_setting['tamad_sender_id'],
            'rcpt_email'        => $payload->mail,
            'parameters'        => $params
        ];
        
        $response = $client->request('POST', 'https://email.dizatech.com/api/transactional_email', [
            'headers'   => [
                'Authorization' => 'Bearer '.dizatech_token
            ],
            'form_params' => $data
        ]);
    
        $results = $response->getBody()->getContents();
        $results = json_decode($results,true);
        if( $results['status']!= 'success' ){
            throw new Exception("Server reponded: " . PHP_EOL . $response->getBody()->getContents());
        }
    }
}