<?php

namespace Raptor\Exception;

use codesaur\Template\FileTemplate;
use codesaur\Http\Message\ReasonPrhase;
use codesaur\Http\Application\ExceptionHandler as Base;
use codesaur\Http\Application\ExceptionHandlerInterface;

/**
 * Class ErrorHandler
 * -----------------------------------------------
 * ⚠️ Indoraptor Framework-ийн default алдаа баригч (Exception Handler).
 *
 * Энэ класс нь систем дотор гарсан бүх төрлийн Exception болон Error-ийг
 * нэг цэгээс барьж, хэрэглэгчид харагдах энгийн error хуудас руу рендерлэнэ.
 *
 * ✔ Хэрэв `/error.html` template байгаа бол → HTML алдааны хуудас руу рендерлэнэ  
 * ✔ Хэрэв template байхгүй эсвэл Template engine ачаалагдаагүй бол →  
 *   `codesaur\Http\Application\ExceptionHandler` үндсэн fallback ашиглана  
 *
 * ⚙ **Хөгжүүлэгч override хийх боломжтой**
 * ---------------------------------------------  
 * Хэрэв developer өөрийн custom Exception Handler бичихийг хүсвэл  
 * `Application->use(new CustomExceptionHandler())` гэж ашиглан бүртгэж болно.  
 *
 * Алдаа барих үндсэн логик:
 *   1) Throwable объектын код, мессежийг унших
 *   2) HTTP статус код тохируулах (ReasonPhrase ашиглан)
 *   3) error.log руу бичих (`error_log`)
 *   4) error.html template рендерлэх
 *
 * Хөгжүүлэлтийн горимд (CODESAUR_DEVELOPMENT):
 *   → Stack trace дэлгэц дээр JSON форматтай харуулна.
 *
 * Тemplate-д дамжуулах хувьсагчууд:
 *   ● title     - Алдааны гарчиг (Exception 404 гэх мэт)
 *   ● message   - Хэрэглэгчид үзэгдэх аюулгүй алдааны текст
 *   ● return    - Буцах линк тэмдэглэгээ
 *
 * @package Raptor\Exception
 */
class ErrorHandler implements ExceptionHandlerInterface
{
    /**
     * Глобал exception барих гол функц.
     *
     * @param \Throwable $throwable Баригдсан Exception/Error
     *
     * @return mixed HTML render эсвэл fallback Exception handler
     */
    public function exception(\Throwable $throwable): void
    {
        $errorTemplate = __DIR__ . '/error.html';

        // Хэрэв FileTemplate эсвэл template файл байхгүй бол fallback ашиглах
        if (!\class_exists(FileTemplate::class)
            || !\file_exists($errorTemplate)
        ) {
            (new Base())->exception($throwable);
            return;
        }

        // Exception мэдээлэл
        $code = $throwable->getCode();
        $message = $throwable->getMessage();
        $title = $throwable instanceof \Exception ? 'Exception' : 'Error';

        // Алдааны код 0 биш бол тайлбарт нэмж харуулна
        if ($code != 0) {
            $title .= " $code";

            // HTTP статус код тохируулах боломжтой эсэхийг шалгах
            if (\class_exists(ReasonPrhase::class)) {
                $status = "STATUS_$code";
                $reasonPhrase = ReasonPrhase::class;

                // Status constant байвал → HTTP статус илгээх
                if (\defined("$reasonPhrase::$status") && !\headers_sent()) {
                    \http_response_code($code);
                }
            }
        }

        // Log файл руу бичих
        \error_log("$title: $message");

        // Host тодорхойлох
        $host = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || $_SERVER['SERVER_PORT'] == 443)
                ? 'https://' : 'http://';
        $host .= $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Template хувьсагчууд
        $vars = [
            'title' => $title,
            'return' => 'Return to host',
            'message' => "<h3 style=\"text-align:center;color:white\">$message</h3>"
        ];

        // Хөгжүүлэлтийн горимд stack trace харуулах
        if (CODESAUR_DEVELOPMENT) {
            $vars['message'] .=
                '<br/><pre style="color:white;height:300px;overflow-y:auto;overflow-x:hidden;">'
                . \json_encode($throwable->getTrace(), \JSON_PRETTY_PRINT)
                . '</pre>';
        }

        // Error хуудсыг рендерлэх
        (new FileTemplate($errorTemplate, $vars))->render();
    }
}
