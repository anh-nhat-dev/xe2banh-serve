<?php

return [
    "key"           => "xxxxxxxxxxxxxxxxxxxxx",
    "secret"        => "xxxxxxxxxxxxxxxxxxxxx",
    "endpoint"       => env("BAOKIM_ENPOINT", "https://sandbox-api.baokim.vn/payment/api/v4"),
    "token_expire"  => env("BAOKIM_TOKEN_EXPIRE", 60),
    "encode_alg"    => 'HS256'
];
