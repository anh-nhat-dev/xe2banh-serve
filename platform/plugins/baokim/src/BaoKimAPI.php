<?php

namespace Botble\BaoKim;

use \Firebase\JWT\JWT;
use GuzzleHttp\Client;

class BaoKimAPI
{


    private $key;
    private $secret;
    private $endpoint;
    private $token_expire;
    private $encode_alg;
    private $client;

    private $_jwt = null;



    /**
     * 
     */
    public function __construct()
    {
        $this->key              = config('plugins.baokim.baokim.key');
        $this->secret           = config('plugins.baokim.baokim.secret');
        $this->endpoint         = config('plugins.baokim.baokim.endpoint');
        $this->token_expire     = config('plugins.baokim.baokim.token_expire');
        $this->encode_alg       = config('plugins.baokim.baokim.encode_alg');
        $this->client           = new Client(['timeout' => 20.0]);
    }


    /**
     * 
     */
    private function refreshToken()
    {
        $tokenId    = base64_encode(random_bytes(32));
        $issuedAt   = time();
        $notBefore  = $issuedAt;
        $expire     = $notBefore + $this->token_expire;


        /*
         * Payload data of the token
         */
        $data = [
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'iss'  => $this->key,     // Issuer
            'nbf'  => $notBefore,        // Not before
            'exp'  => $expire,           // Expire
            'form_params' => []                // request body (dữ liệu post

        ];

        $this->_jwt = JWT::encode(
            $data,
            $this->secret,
            $this->encode_alg
        );

        return $this->_jwt;
    }


    /**
     * 
     */
    private function getToken()
    {
        if (!$this->_jwt)
            $this->refreshToken();

        try {
            JWT::decode($this->_jwt, $this->secret, array($this->encode_alg));
        } catch (\Throwable $th) {
            $this->refreshToken();
        }

        return $this->_jwt;
    }

    /**
     * 
     */
    public function verifyQuery($query,  $checksum)
    {
        $myCheckSum =  hash_hmac('sha256', http_build_query($query), $this->secret);
        return $checksum == $myCheckSum;
    }


    /**
     * 
     */
    public function getOrderDetail($id, $mrc_id)
    {
        $enpoint = $this->endpoint . '/api/v4/order/detail';
        $options = [
            'query'     => [
                'jwt'           =>  $this->getToken(),
                'id'            => $id,
                'mrc_order_id'  => $mrc_id
            ]
        ];

        return $this->sendRequest($enpoint, $options);
    }



    /**
     * 
     */
    public function sendOrder(array $payload)
    {
        $enpoint = $this->endpoint . '/api/v4/order/send';
        $options = array(
            "query" => array(
                "jwt" => $this->getToken()
            ),
            "form_params" => $payload
        );
        return  $this->sendRequest($enpoint, $options, "POST");
    }

    /**
     * 
     */
    public function getBankPaymentMethodList()
    {

        $enpoint = $this->endpoint . '/api/v4/bpm/list';
        $options = array(
            "query" => array(
                "jwt" => $this->getToken()
            )
        );
        $body = $this->sendRequest($enpoint, $options);
        return $body->data ??  [];
    }

    /**
     * 
     */
    public function calculateFee(array $payload) {
        $enpoint = $this->endpoint . '/api/v4/bpm/calculate-fee';

        $options = array(
            "query" => array_merge(
                    $payload, 
                    array(
                    "jwt" => $this->getToken()
                )
            )
           
        );
        return  $this->sendRequest($enpoint, $options, "GET");
    }


    /**
     * 
     */
    public function loanPackage($body = []){
        $endpoint = "https://pg.baokim.vn/installment/loan-package";

        $options = array(
            "form_params" => array_merge([
                "domain" => "xedienvietthanh.com",
                
            ], $body)
        );
        return  $this->sendRequest($endpoint, $options, "POST");
    }

    /**
     * 
     */
    public function checkBankCard($body = []){
        $endpoint = "https://pg.baokim.vn/installment/post-check-bank-card";

        $options = array(
            "form_params" => $body
        );

        return  $this->sendRequest($endpoint, $options, "POST");
    }

    /**
     * 
     */
    public function createOrderTemporary($body = []){
        $endpoint = "https://ws.baokim.vn/payment-servicesapi/v1/order-temporary/store";

        $options = array(
            "form_params" => array_merge($body, array(
                "domain"    =>  config('plugins.baokim.baokim.domain')
            ))
        );

        return  $this->sendRequest($endpoint, $options, "POST");
    }



    /**
     * 
     */
    private function sendRequest($enpoint, $options, $method = "GET")
    {
        try {
            $response = $this->client->request($method, $enpoint, $options);
            $body = json_decode($response->getBody()->getContents());
            return $body;
        } catch (\Throwable $th) {
            $response = new \stdClass();
            $response->error    = true;
            $response->message  = $th->getMessage();
            $response->data     = null;
            return $response;
        }
    }

}
