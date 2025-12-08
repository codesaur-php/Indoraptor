<?php

namespace Raptor\Content;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class SettingsMiddleware
 *
 * Indoraptor framework-ийн глобал тохиргоо (Settings)-г
 * HTTP хүсэлт бүрийн үед уншиж, Request attributes руу inject хийх middleware.
 *
 * Энэхүү middleware нь дараах үүрэгтэй:
 * ----------------------------------------------------
 * ✔ LocalizationMiddleware-ээс ирсэн хэлний кодоор тохирох контентыг авах  
 * ✔ SettingsModel-ийг ашиглан p (primary) + c (content) хүснэгтийг JOIN хийх  
 * ✔ is_active=1 тохиргооноос зөвхөн НЭГ мөрийг (хамгийн идэвхтэйг) унших  
 * ✔ config талбар JSON бол автоматаар decode хийх  
 * ✔ Request объектод 'settings' аттрибутаар дамжуулж өгөх  
 *
 * Ашиглагдах газар:
 * ----------------------------------------------------
 * - Twig template engine (layout.twig, header.twig, footer.twig)
 * - Controller-ууд (UI settings авах)
 * - SEO meta тохиргоо
 * - Logo, favicon, Apple Touch Icon, сайт description
 *
 * Анхаарах зүйл:
 * ----------------------------------------------------
 * - Энэ middleware-ээс өмнө *LocalizationMiddleware* заавал ажилласан байх ёстой  
 *   Учир нь хэлний код (c.code) нь localization attribute-аас авдаг.
 *
 * @package Indoraptor\Content
 */
class SettingsMiddleware implements MiddlewareInterface
{
    /**
     * @inheritdoc
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // Request attribute-оос PDO-г авах (Application-с дамждаг)
            $pdo = $request->getAttribute('pdo');

            // SettingsModel instance
            $model = new SettingsModel($pdo);

            // LocalizationMiddleware-ээс ирсэн хэлний кодыг шалгах
            $code = $request->getAttribute('localization')['code']
                ?? throw new \Exception('SettingsMiddleware-н өмнө LocalizationMiddleware ажиллах ёстой!');

            // SQL statement - идэвхтэй + тухайн хэлний контентыг авах
            $stmt = $pdo->prepare(
                'SELECT p.email, p.phone, p.favico, p.apple_touch_icon, p.config, ' .
                'c.title, c.logo, c.description, c.urgent, c.contact, c.address, c.copyright ' .
                "FROM {$model->getName()} AS p " .
                "INNER JOIN {$model->getContentName()} AS c ON p.id = c.parent_id " .
                'WHERE p.is_active = 1 AND c.code = :code LIMIT 1'
            );
            // Хэлний кодыг bind хийх
            $stmt->bindParam(':code', $code, \PDO::PARAM_STR);
            // Гүйцэтгээд settings мөрийг унших
            if ($stmt->execute()) {
                $settings = $stmt->fetchAll()[0] ?? [];

                // config талбарыг JSON болгон parse хийж array болгоно
                if (!empty($settings['config'])) {
                    $settings['config'] = \json_decode($settings['config'], true);
                }
            }
        } catch (\Throwable $err) {
            // Хөгжүүлэлтийн орчинд алдааны лог бичих
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
        }

        // Request attributes → 'settings' дараагийн middleware руу дамжуулах
        return $handler->handle(
            $request->withAttribute('settings', $settings ?? [])
        );
    }
}
