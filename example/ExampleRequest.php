<?php

namespace Indoraptor\Example;

use Error;

use Firebase\JWT\JWT;

use codesaur\Http\Message\ServerRequest;

class ExampleRequest extends ServerRequest
{
    function __construct()
    {
        $this->initFromGlobal();
        
        if (!in_array($this->getRemoteAddr(), array('127.0.0.1', '::1'))) {
            throw new Error('This experimental example only works on local development enviroment');
        }

        if (empty($this->getServerParams()['HTTP_JWT'])) {
            // For testing purpose we authorizing into Indoraptor
            $issuedAt = time();
            $expirationTime = $issuedAt + 300;
            $payload = array(
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'account_id' => 1,
                'organization_id' => 1
            );
            $key = 'codesaur-indoraptor-not-so-secret';
            $jwt = JWT::encode($payload, $key, $_ENV['INDO_JWT_ALGORITHM'] ?? 'HS256');
            $this->setHeader('INDO_JWT', $jwt);
        }
    }
    
    function isValidIP(string $ip): bool
    {
        $real = ip2long($ip);
        if (empty($ip) || $real === -1 || $real === false) {
            return false;
        }

        $private_ips = array(
            ['0.0.0.0', '2.255.255.255'],
            ['10.0.0.0', '10.255.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.0.2.0', '192.0.2.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['255.255.255.0', '255.255.255.255']);
        foreach ($private_ips as $r) {
            $min = ip2long($r[0]); $max = ip2long($r[1]);
            if ($real >= $min && $real <= $max) {
                return false;
            }
        }

        return true;
    }

    function getRemoteAddr(): string
    {
        if (!empty($this->getServerParams()['HTTP_X_FORWARDED_FOR'])) {
            if (!empty($this->getServerParams()['HTTP_CLIENT_IP'])
                    && $this->isValidIP($this->getServerParams()['HTTP_CLIENT_IP'])) {
                return $this->getServerParams()['HTTP_CLIENT_IP'];
            }            
            foreach (explode(',', $this->getServerParams()['HTTP_X_FORWARDED_FOR']) as $ip) {
                if ($this->isValidIP(trim($ip))) {
                    return $ip;
                }
            }
        }

        if (!empty($this->getServerParams()['HTTP_X_FORWARDED'])
                && $this->isValidIP($this->getServerParams()['HTTP_X_FORWARDED'])
        ) {
            return $this->getServerParams()['HTTP_X_FORWARDED'];
        } elseif (!empty($this->getServerParams()['HTTP_X_CLUSTER_CLIENT_IP'])
                && $this->isValidIP($this->getServerParams()['HTTP_X_CLUSTER_CLIENT_IP'])
        ) {
            return $this->getServerParams()['HTTP_X_CLUSTER_CLIENT_IP'];
        } elseif (!empty($this->getServerParams()['HTTP_FORWARDED_FOR'])
                && $this->isValidIP($this->getServerParams()['HTTP_FORWARDED_FOR'])
        ) {
            return $this->getServerParams()['HTTP_FORWARDED_FOR'];
        } elseif (!empty($this->getServerParams()['HTTP_FORWARDED'])
                && $this->isValidIP($this->getServerParams()['HTTP_FORWARDED'])
        ) {
            return $this->getServerParams()['HTTP_FORWARDED'];
        }

        return $this->getServerParams()['REMOTE_ADDR'] ?? '';
    }
}
