<?php

namespace Raptor\Exception;

use codesaur\Http\Message\ReasonPrhase;
use codesaur\Http\Application\ExceptionHandlerInterface;

/**
 * Class JsonExceptionHandler
 * ---------------------------------------------------------
 * ⚡ Indoraptor Framework - JSON хэлбэрээр алдаа буцаах тусгай
 * Exception Handler.
 *
 * Энэ класс нь REST API, AJAX request, mobile client зэрэг
 * JSON-response шаардсан орчинд тохиромжтой. Систем дотор
 * үүссэн бүх төрлийн Exception/Error-ийг JSON бүтэцтэйгээр
 * клиент рүү буцаана.
 *
 * ➤ Үндсэн боломжууд:
 * -----------------------------------------
 * ✔ Алдааны HTTP статус кодыг автоматаар таньж тохируулах  
 * ✔ JSON Content-Type header илгээх  
 * ✔ JSON бүтэцтэй стандарт error формат гаргах  
 * ✔ Хөгжүүлэлтийн горимд (CODESAUR_DEVELOPMENT) →  
 *     Stack trace бүрэн харуулна  
 *
 * ➤ JSON бүтэц:
 * -----------------------------------------
 * {
 *    "error": {
 *        "code": <int>,
 *        "title": "<Throwable class name + code>",
 *        "message": "<error message>",
 *        "trace": [...]   // зөвхөн DEVELOPMENT горимд
 *    }
 * }
 *
 * ➤ Хөгжүүлэгч өөрийн Exception Handler-г ашиглах боломжтой
 * ---------------------------------------------------------
 * Энэ JSON handler-г Application-д дараах байдлаар идэвхжүүлнэ:
 *
 *      $app->use(new JsonExceptionHandler());
 *
 * Хэрэв хөгжүүлэгч өөрийн custom ExceptionHandler бичвэл  
 * мөн адил `Application->use()` ашиглан override хийж болно.
 *
 * ➤ HTTP статус кодын тохиргоо:
 * -----------------------------------------
 * Throwable код нь 0 биш бол ReasonPrhase классыг ашиглан
 * тохирох HTTP статус кодыг автоматаар тохируулна:
 *
 *   - 400 Bad Request
 *   - 401 Unauthorized
 *   - 404 Not Found
 *   - 500 Internal Server Error
 *   гэх мэт
 *
 * @package Raptor\Exception
 */
class JsonExceptionHandler implements ExceptionHandlerInterface
{
    public function exception(\Throwable $throwable): void
    {
        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = \get_class($throwable);
        
        if ($code != 0) {
            $status = "STATUS_$code";
            $reasonPhrase = ReasonPrhase::class;
            if (\defined("$reasonPhrase::$status") && !\headers_sent()) {
                \http_response_code($code);
            }
            $title .= " $code";
        }
        
        \error_log($title . ': ' . $message);
        
        if (!\headers_sent()) {
            \header('Content-Type: application/json');
        }
        
        $error = ['code' => $code, 'title' => $title, 'message' => $message];
        
        if (CODESAUR_DEVELOPMENT) {
            $error['trace'] = $throwable->getTrace();
        }
        
        echo \json_encode(['error' => $error])
            ?: '{"error":{"code":' . $code . ',"title":"' . \addslashes($title) . '","message":"' . \addslashes($message) . '"}}';
    }
}
