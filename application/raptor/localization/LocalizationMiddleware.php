<?php

namespace Raptor\Localization;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class LocalizationMiddleware
 *
 * Энэ middleware нь Indoraptor framework-ийн нутагшуулалтын (localization)
 * үндсэн агуулгыг бүрдүүлдэг.
 *
 * Гол үүргүүд:
 * ------------
 * 1) Системд идэвхтэй байгаа хэлийг (LanguageModel) ачаалж:
 *      ['mn' => [...], 'en' => [...], ...]
 *    бүтэцтэйгээр request-д attribute хэлбэрээр дамжуулна.
 *
 * 2) Аль хэл сонгогдсоныг (RAPTOR_LANGUAGE_CODE - session key) шалгаж,
 *    боломжгүй бол default хэлийг автоматаар сонгоно.
 *
 * 3) default, dashboard, user зэрэг хүснэгтүүдээс тухайн хэл дээрх
 *    орчуулгуудыг ачаалж нэгтгэнэ.
 *
 * 4) Эцэст нь request-д 'localization' нэртэй attribute болгон:
 *      [
 *          'language' => [...],
 *          'code'     => 'mn',
 *          'text'     => ['keyword' => 'translated text', ...]
 *      ]
 *    байдлаар дамжуулж өгнө.
 *
 * Энэ attribute нь бүх Controller, View (Twig), Template, Middleware-т
 * шууд ашиглах боломжтой.
 */
class LocalizationMiddleware implements MiddlewareInterface
{
    /**
     * Хэлний жагсаалтыг өгөгдлийн сангаас татах.
     *
     * Хэрэв ямар нэгэн алдаа гарвал:
     *   - Development mode үед error_log() руу хэвлэнэ
     *   - Fallback хэл болгон English-г буцаана
     *
     * @param ServerRequestInterface $request
     * @return array|string[][]
     */
    private function retrieveLanguage(ServerRequestInterface $request)
    {
        try {
            // LanguageModel-ийг PDO-оор холбож ачаална
            $model = new LanguageModel($request->getAttribute('pdo'));
            $rows = $model->retrieve();
            if (empty($rows)) {
                throw new \Exception('Languages not found!');
            }
            // Амжилттай хэлний жагсаалт буцааж байна
            return $rows;
        } catch (\Throwable $err) {
            // Алдааг зөвхөн хөгжүүлэлтийн горимд хэвлэнэ
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
            // Хэл жагсаалт олдоогүй тул fallback хэл (англи) буцааж байна
            return ['en' => ['locale' => 'en-US', 'title' => 'English']];
        }
    }

    /**
     * Dashboard апп нь орчуулгаа 3 үндсэн группээс татна:
     *   - default
     *   - dashboard
     *   - user
     *
     * Эдгээрийг нэгтгэн цогц орчуулгын массив болгон буцаана.
     *
     * @param ServerRequestInterface $request
     * @param string $langCode - сонгогдсон хэлний код (mn, en, ru, ...)
     * @return array
     */
    private function retrieveTexts(ServerRequestInterface $request, string $langCode)
    {
        $texts = [];
        try {
            $pdo = $request->getAttribute('pdo');
            
            // Ачаалах текстийн бүлгүүд
            $tables = ['default', 'dashboard', 'user'];
            foreach ($tables as $table) {
                $model = new TextModel($pdo);
                $model->setTable($table);   // Хүснэгтийг автоматаар шалгаж үүсгэнэ (__initial)
                $text = $model->retrieve($langCode);
                // Хүснэгт хоосон биш бол үр дүнг нэгтгэнэ
                if (!empty($text)) {
                    $texts += $text;
                }
            }
        } catch (\Throwable $err) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
        }
        return $texts;
    }

    /**
     * Middleware-н үндсэн process()
     *
     * 1) available languages татна
     * 2) сонгогдсон хэлийг тодорхойлно (session or default)
     * 3) тухайн хэл дээрх орчуулгуудыг нэгтгэнэ
     * 4) request объект дээр 'localization' attribute болгон
     *    бүх мэдээллийг хавсаргаад дараагийн middleware/controller руу дамжуулна.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Хэлний жагсаалтыг татах
        $language = $this->retrieveLanguage($request);

        // Session-д хадгалсан хэл хүчинтэй эсэхийг шалгах
        if (
            isset($_SESSION['RAPTOR_LANGUAGE_CODE']) &&
            isset($language[$_SESSION['RAPTOR_LANGUAGE_CODE']])
        ) {
            $code = $_SESSION['RAPTOR_LANGUAGE_CODE'];
        } else {
            // Session-ээс хэл олдоогүй тул эхний хэл дээр автоматаар fallback хийе
            $code = \key($language);
        }

        // Сонгосон хэл дээр текстүүдээ татах 
        $text = $this->retrieveTexts($request, $code);

        // Localization attribute-г request дээр нааж дараагийн боловсруулагчруу дамжуулна
        return $handler->handle(
            $request->withAttribute('localization', [
                'language' => $language,
                'code'     => $code,
                'text'     => $text
            ])
        );
    }
}
