<?php

namespace Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Raptor\Localization\LanguageModel;
use Raptor\Localization\TextModel;

/**
 * Class LocalizationMiddleware
 * ------------------------------------------------------------------
 * 🌐 Web давхаргын Localization Middleware  
 * (Dashboard-аас ялгаатай - зөвхөн public веб хуудсуудын орчуулгыг ачаална)
 *
 * Энэ middleware нь веб сайтын (frontend) хэрэглэгчийн харах
 * UI текстүүдийг хэл дээр нь нутагшуулж, Request → Controller →
 * Template давхаргуудад дамжуулах зориулалттай.
 *
 * ------------------------------------------------------------------
 * 🆚 Dashboard-ийн LocalizationMiddleware-ээс ялгарах гол онцлог
 * ------------------------------------------------------------------
 * ✔ *Dashboard* хувилбар нь `dashboard`, `default`, `user`,  
 *   гэх мэт орчуулгын хүснэгтүүдийг нэгтгэн ачаадаг  
 *
 * ❗ *Харин энэ Web хувилбар нь* зөвхөн:
 *   - `default` (системийн үндсэн текст)
 *   - `user` (админ хэрэглэгчийн үүсгэсэн орчуулга текст)
 *
 *   гэсэн **хоёрхон орчуулгын хүснэгтийг** ачаална.
 *
 * 👉 Учир нь public веб талд Dashboard-ийн орчуулга шаардлагагүй.  
 *    Веб UI-г хурдан болгох үүднээс орчуулгын хүрээг хязгаарласан.
 *
 * ------------------------------------------------------------------
 * 🧩 Middleware-ийн ажиллагааны дараалал
 * ------------------------------------------------------------------
 * 1) `LanguageModel` ашиглан боломжит хэлүүдийг DB-ээс татна  
 *    - Хэрэв алдаа гарвал → fallback: English (en-US)
 *
 * 2) Session (`WEB_LANGUAGE_CODE`) дээр хэрэглэгч сонгосон хэл байвал
 *    тэрийг хэрэглэнэ, эс бөгөөс эхний хэл кодыг авна.
 *
 * 3) Сонгогдсон хэл дээр:
 *      - default орчуулгын хүснэгт
 *      - user орчуулгын хүснэгт
 *    хоёроос текстүүдийг нийлүүлж авах
 *
 * 4) Request объектод attributes хэлбэрээр дамжуулах:
 *
 *      $request->getAttribute('localization') =
 *      [
 *          'language' => [...],  // боломжит хэлүүд
 *          'code'     => 'mn',   // сонгосон хэл код
 *          'text'     => [...]   // орчуулгын key/value массив
 *      ]
 *
 * 5) Controller болон Twig Template-үүд нь `localization` attribute-ийг ашиглан
 *    UI-г тухайн хэл дээр автоматаар харуулна.
 *
 * ------------------------------------------------------------------
 * 🧑‍💻 Хөгжүүлэгчид зориулсан тэмдэглэл
 * ------------------------------------------------------------------
 * ✔ Хэрвээ веб дээр тусдаа орчуулгын хүснэгт хэрэгтэй бол  
 *   `$tables = ['default', 'user'];` массиваа өргөтгөх боломжтой  
 *
 * ✔ Dashboard талд энэхүү middleware ашиглагдахгүй  
 *
 * ✔ Хэл солих route-г өөрөө Web\Application эсвэл өөр Router дээр
 *   нэмэн хэрэгжүүлэх боломжтой (жишээ: `/language/mn`)
 *
 * ------------------------------------------------------------------
 * @package Web
 */
class LocalizationMiddleware implements MiddlewareInterface
{   
    /**
     * Нийт боломжит хэлүүдийг DB-ээс татах
     * (алдаа гарвал English рүү fallback хийнэ)
     */
    private function retrieveLanguage(ServerRequestInterface $request)
    {
        try {
            $model = new LanguageModel($request->getAttribute('pdo'));
            $rows = $model->retrieve();
            if (empty($rows)) {
                throw new \Exception('Languages not found!');
            }
            return $rows;
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }
            return ['en' => ['locale' => 'en-US', 'title' => 'English']];
        }
    }
    
    /**
     * Сонгогдсон хэл дээр WEB талд шаардлагатай текстүүдийг
     * зөвхөн `default` болон `user` хүснэгтээс ачаална.
     */
    private function retrieveTexts(ServerRequestInterface $request, string $langCode)
    {
        $texts = [];
        try {
            $tables = ['default', 'user'];
            $pdo = $request->getAttribute('pdo');            
            foreach ($tables as $table) {
                $model = new TextModel($pdo);
                $model->setTable($table);
                $text = $model->retrieve($langCode);
                if (!empty($text)) {
                    $texts += $text;
                }
            }
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }
        }
        return $texts;
    }
    
    /**
     * Localization attribute-г Request объектод дамжуулж,
     * дараагийн Middleware/Controller-т өгнө.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = $this->retrieveLanguage($request);

        if (isset($_SESSION['WEB_LANGUAGE_CODE'])
            && isset($language[$_SESSION['WEB_LANGUAGE_CODE']])
        ) {
            $code = $_SESSION['WEB_LANGUAGE_CODE'];
        } else {
            $code = \key($language);
        }
        
        $text = $this->retrieveTexts($request, $code);
        
        return $handler->handle(
            $request->withAttribute('localization', [
                'language' => $language,
                'code' => $code,
                'text' => $text
            ])
        );
    }
}
