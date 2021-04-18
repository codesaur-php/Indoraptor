<?php

namespace Indoraptor;

use Throwable;
use Exception;

use codesaur\Http\Message\ReasonPrhaseInterface;
use codesaur\Http\Application\ExceptionHandlerInterface;

class IndoExceptionHandler implements ExceptionHandlerInterface
{
    public function exception(Throwable $throwable)
    {
        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = $throwable instanceof Exception ? 'Exception' : 'Error';
        
        if ($code !== 0) {
            $status = "STATUS_$code";
            $reasonPhraseInterface = ReasonPrhaseInterface::class;
            if (defined("$reasonPhraseInterface::$status")
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
