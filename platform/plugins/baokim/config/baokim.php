<?php

return [
    "key"               => "xxxxxxxxxxxxxxxxxxxxx",
    "secret"            => "xxxxxxxxxxxxxxxxxxxxx",
    "endpoint"          => env("BAOKIM_ENPOINT", "https://sandbox-api.baokim.vn"),
    "token_expire"      => env("BAOKIM_TOKEN_EXPIRE", 60),
    "encode_alg"        => 'HS256'
];
