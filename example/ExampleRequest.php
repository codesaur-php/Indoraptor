<?php

namespace Indoraptor\Example;

use Firebase\JWT\JWT;

use codesaur\Http\Message\ServerRequest;

class ExampleRequest extends ServerRequest
{
    function __construct()
    {
        $this->initFromGlobal();
        
        if (!\in_array($this->serverParams['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
            throw new \Error('This experimental example only works on local development enviroment');
        }

        if (empty($this->serverParams['HTTP_AUTHORIZATION'])) {
            // For a testing purpose we authorizing into Indoraptor
            $issuedAt = \time();
            $lifeSeconds = 300;
            $expirationTime = $issuedAt + $lifeSeconds;
            $payload = [
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'seconds' => $lifeSeconds,
                'account_id' => 1,
                'organization_id' => 1
            ];
            $key = 'codesaur-indoraptor-not-so-secret';
            $jwt = JWT::encode($payload, $key, 'HS256');
            $this->serverParams['HTTP_AUTHORIZATION'] = "Bearer $jwt";
        }
    }
}
