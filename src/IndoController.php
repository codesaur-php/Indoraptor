<?php

namespace Indoraptor;

use PDO;
use Exception;

use Firebase\JWT\JWT;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ServerRequestInterface;

use codesaur\Http\Application\Controller;
use codesaur\Http\Message\ReasonPrhaseInterface;

define('INDO_JWT_LIFETIME', getenv('INDO_JWT_LIFETIME', true) ?: 2592000);
define('INDO_JWT_ALGORITHM', getenv('INDO_JWT_ALGORITHM', true) ?: 'HS256');
define('INDO_JWT_SECRET', getenv('INDO_JWT_SECRET', true) ?: 'codesaur-indoraptor-not-so-secret');

class IndoController extends Controller
{
    use \codesaur\DataObject\PDOTrait;
    
    final function __construct(ServerRequestInterface $request)
    {
        parent::__construct($request);
        
        $driver = getenv('INDO_DB_DRIVER', true) ?: 'mysql';
        $host = getenv('INDO_DB_HOST', true) ?: 'localhost';
        $username =  getenv('INDO_DB_USERNAME', true) ?: 'root';
        $passwd = getenv('INDO_DB_PASSWORD', true) ?: '';
        $charset = getenv('INDO_DB_CHARSET', true) ?: 'utf8';
        $options = array(
            PDO::ATTR_PERSISTENT => getenv('INDO_DB_PERSISTENT', true) == 'true',
            PDO::ATTR_ERRMODE => !$this->isDevelopment() ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_WARNING
        );
        
        $dsn = "$driver:host=$host;charset=$charset";
        $this->pdo = new PDO($dsn, $username, $passwd, $options);

        $database = getenv('INDO_DB_NAME', true) ?: 'indoraptor';
        if ($request->getServerParams()['HTTP_HOST'] === 'localhost'
                && in_array($request->getServerParams()['REMOTE_ADDR'], array('127.0.0.1', '::1'))
        ) {
            $collation = getenv('INDO_DB_COLLATION', true) ?: 'utf8_unicode_ci';
            $this->exec("CREATE DATABASE IF NOT EXISTS $database COLLATE " . $this->quote($collation));
        }
        $this->exec("USE $database");
        
        if (getenv('INDO_TIME_ZONE_UTC', true)) {
            $this->exec('SET time_zone = ' . $this->quote(getenv('INDO_TIME_ZONE_UTC', true)));
        }
    }
    
    final public function generate(array $data)
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + INDO_JWT_LIFETIME;
        $payload = array(
            'iat' => $issuedAt,
            'exp' => $expirationTime
        ) + $data;
        $key = INDO_JWT_SECRET;
        $alg = INDO_JWT_ALGORITHM;
        
        return JWT::encode($payload, $key, $alg);
    }
    
    final public function validate($jwt = null, $secret = null, $algs = null)
    {
        try {
            if (!isset($jwt)) { 
                $jwt = getenv('INDO_JWT', true);
            } elseif (empty($jwt)) {
                throw new Exception('Undefined JWT!');
            }
            
            $result = (array) JWT::decode($jwt,
                    $secret ?? INDO_JWT_SECRET,
                    $algs ?? array(INDO_JWT_ALGORITHM));            
            if ($result['account_id'] ?? false &&
                !getenv('CODESAUR_ACCOUNT_ID', true)) {
                putenv("CODESAUR_ACCOUNT_ID={$result['account_id']}");
            }            
            return $result;            
        } catch (Exception $ex) {
            if ($this->isDevelopment()) {
                error_log($ex->getMessage());
            }
            
            return $ex->getMessage();
        }
    }
    
    final public function isAuthorized(): bool
    {
        return is_array($this->validate());
    }
    
    final public function isInternal(): bool
    {
        return $this->getAttribute('indo_internal') === true;
    }

    final public function respond($response, $status = null)
    {
        if ($this->isInternal()) {
            echo json_encode($response);
            return;
        }
        
        header('Content-Type: application/json');        
        if (is_int($status)) {
            $prhase = "STATUS_$status";
            $reasonPhraseInterface = ReasonPrhaseInterface::class;
            if (defined("$reasonPhraseInterface::$prhase")) {
                http_response_code($status);
            }
        }        
        exit(json_encode($response));
    }
    
    final public function error($message, $status)
    {
        return $this->respond(array('error' => array('code' => $status, 'message' => $message)), $status);
    }
    
    final public function badRequest(string $message = 'Bad Request')
    {
        return $this->error($message, StatusCodeInterface::STATUS_BAD_REQUEST);
    }
    
    final public function unauthorized(string $message = 'Unauthorized')
    {
        return $this->error($message, StatusCodeInterface::STATUS_UNAUTHORIZED);
    }
    
    final public function forbidden(string $message = 'Forbidden')
    {
        return $this->error($message, StatusCodeInterface::STATUS_FORBIDDEN);
    }
    
    final public function notFound(string $message = 'Not found')
    {
        return $this->error($message, StatusCodeInterface::STATUS_NOT_FOUND);
    }
    
    final public function grabModel()
    {
        $cls = $this->getQueryParam('model')
                ?? $this->getPostParam('model', FILTER_SANITIZE_STRING);
        if (!empty($cls)) {
            $class = str_replace(' ', '', $cls);
            if (class_exists($class)) {
                $model = new $class($this->pdo);
                if (method_exists($model, 'setTable')) {
                    $table = $this->getQueryParam('table')
                            ?? $this->getPostParam('table', FILTER_SANITIZE_STRING);
                    if (!empty($table)) {
                        $model->setTable((string)$table, 'utf8_unicode_ci');
                    }
                    return $model;
                }
            }
        }        
        return $this->badRequest();
    }
}
