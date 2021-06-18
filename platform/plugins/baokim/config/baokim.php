<?php

return [
    "key"                   => "xxxxxxxxxxxxxxxxxxxxx",
    "secret"                => "xxxxxxxxxxxxxxxxxxxxx",
    "merchant_id"           => "merchant_id",
    "endpoint"              => env("BAOKIM_ENPOINT", "https://sandbox-api.baokim.vn/payment"),
    "token_expire"          => env("BAOKIM_TOKEN_EXPIRE", 60),
    "encode_alg"            => 'HS256',
    "account_confirm"       => env("BAOKIM_ACCOUNT_CONFIRM", "no"),
    "domain"                => "xe2banh.vn"            
];
