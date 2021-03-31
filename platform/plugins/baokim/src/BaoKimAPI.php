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
    private function refreshToken(array $payload)
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
            'form_params' => $payload                // request body (dữ liệu post

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
    private function getToken(array $payload)
    {
        if (!$this->_jwt)
            $this->refreshToken($payload);

        try {
            JWT::decode($this->_jwt, $this->secret, array($this->encode_alg));
        } catch (\Throwable $th) {
            $this->refreshToken($payload);
        }

        return $this->_jwt;
    }

    /**
     * 
     */
    public function sendOrder(array $payload)
    {
        $enpoint = $this->endpoint . '/order/send';

        $options = array(
            "query" => array(
                "jwt" => $this->getToken($payload)
            ),
            "form_params" => $payload
        );

        $response = $this->client->request('POST', $enpoint, $options);

        $body = json_decode($response->getBody()->getContents());

        return $body;
    }
}
