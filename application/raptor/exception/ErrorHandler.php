<?php

namespace Raptor\Exception;

use codesaur\Template\FileTemplate;
use codesaur\Http\Message\ReasonPrhase;
use codesaur\Http\Application\ExceptionHandler as Base;
use codesaur\Http\Application\ExceptionHandlerInterface;

class ErrorHandler implements ExceptionHandlerInterface
{
    public function exception(\Throwable $throwable)
    {
        $errorTemplate = __DIR__ . '/error.html';
        if (!\class_exists(FileTemplate::class)
            || !\file_exists($errorTemplate)
        ) {
            return (new Base())->exception($throwable);
        }
        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = $throwable instanceof \Exception ? 'Exception' : 'Error';
        
        if ($code != 0) {
            $title .= " $code";
            if (\class_exists(ReasonPrhase::class)) {
                $status = "STATUS_$code";
                $reasonPhrase = ReasonPrhase::class;
                if (\defined("$reasonPhrase::$status")
                        && !\headers_sent()
                ) {
                    \http_response_code($code);
                }
            }
        }
        \error_log("$title: $message");
        
        $host = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        $host .= $_SERVER['HTTP_HOST'] ?? 'localhost';

        $vars = [
            'title' => $title,
            'return' => 'Return to host',
            'message' => "<h3 style=\"text-align:center;color:white\">$message</h3>"
        ];
        
        if (CODESAUR_DEVELOPMENT) {
            $vars['message'] .=
                '<br/><pre style="color:white;height:300px;overflow-y:auto;overflow-x:hidden;">'
                . \json_encode($throwable->getTrace(), \JSON_PRETTY_PRINT) . '</pre>';
        }
        
        (new FileTemplate($errorTemplate, $vars))->render();
    }
}
