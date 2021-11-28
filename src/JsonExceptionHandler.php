<?php

namespace Indoraptor;

use Throwable;
use Exception;

use codesaur\Http\Message\ReasonPrhase;
use codesaur\Http\Application\ExceptionHandlerInterface;

class JsonExceptionHandler implements ExceptionHandlerInterface
{
    public function exception(Throwable $throwable)
    {
        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = $throwable instanceof Exception ? 'Exception' : 'Error';
        
        if ($code !== 0) {
            $status = "STATUS_$code";
            $reasonPhrase = ReasonPrhase::class;
            if (defined("$reasonPhrase::$status")
                    && !headers_sent()
            ) {
                http_response_code($code);
            }            
            $title .= " $code";
        }
        
        error_log("$title: $message");
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        $error = array('code' => $code, 'title' => $title, 'message' => $message);
        
        if (defined('CODESAUR_DEVELOPMENT')
                && CODESAUR_DEVELOPMENT
        ) {
            $error['trace'] = $throwable->getTrace();
        }
        
        echo json_encode(array('error' => $error));
    }
}
