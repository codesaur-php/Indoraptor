<?php

namespace Raptor;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use Codesaur\Container\Container;

/**
 * Class ContainerMiddleware
 *
 * Dependency Injection Container-г request attributes-д inject хийх middleware.
 *
 * Энэ middleware нь codesaur/container package-г ашиглан Container үүсгэж,
 * request attributes-д inject хийнэ. Хөгжүүлэгчид өөрсдийн service-уудыг
 * registerServices() method-д бүртгэж ашиглах боломжтой.
 *
 * ════════════════════════════════════════════════════════════════
 * 📚 Хөгжүүлэгчдэд зориулсан заавар
 * ════════════════════════════════════════════════════════════════
 *
 * Service бүртгэх:
 * - ContainerMiddleware-г өргөтгөж registerServices() method-д service-уудыг нэмнэ
 * - registerServices() method нь container болон request параметр авна
 * - Request-ээс PDO, User зэрэг dependency-уудыг авах боломжтой
 * - Controller-д $this->getService('service_id') ашиглан авах
 *
 * Жишээ:
 * ```php
 * namespace App\Middleware;
 *
 * use Raptor\ContainerMiddleware;
 * use Psr\Container\ContainerInterface;
 * use Psr\Http\Message\ServerRequestInterface;
 *
 * class MyContainerMiddleware extends ContainerMiddleware
 * {
 *     protected function registerServices(
 *         ContainerInterface $container,
 *         ServerRequestInterface $request
 *     ): void {
 *         parent::registerServices($container, $request);
 *
 *         // PDO шаардлагатай service (Lazy loading - request-ээс PDO авч)
 *         $container->set('mailer', function(ContainerInterface $c) use ($request) {
 *             $pdo = $request->getAttribute('pdo');
 *             return new \Raptor\Mail\Mailer($pdo);
 *         });
 *
 *         // PDO шаардлагагүй service
 *         $container->set('cache', function(ContainerInterface $c) {
 *             return new \App\Services\CacheService();
 *         });
 *     }
 * }
 * ```
 *
 * Application-д өөрийн middleware-г бүртгэнэ:
 * ```php
 * $this->use(new \App\Middleware\MyContainerMiddleware());
 * ```
 *
 * Controller-д ашиглах:
 * ```php
 * $mailer = $this->getService('mailer');
 * $cache = $this->getService('cache');
 * ```
 *
 * @package Raptor
 */
class ContainerMiddleware implements MiddlewareInterface
{
    /**
     * Middleware process.
     *
     * Container үүсгэж, request attributes-д inject хийнэ.
     * Хөгжүүлэгчид өөрсдийн service-уудыг registerServices() method-д
     * бүртгэж ашиглах боломжтой.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Container үүсгэх (эсвэл одоо байгаа container-г ашиглах)
        $container = $request->getAttribute('container');
        if (!$container instanceof ContainerInterface) {
            $container = new Container();
            $this->registerServices($container, $request);
        }

        // Container-г request attribute болгон дамжуулах
        return $handler->handle(
            $request->withAttribute('container', $container)
        );
    }

    /**
     * Service-уудыг container-д бүртгэх.
     *
     * Хөгжүүлэгчид энэ method-г өргөтгөж өөрсдийн service-уудыг бүртгэж болно.
     *
     * ════════════════════════════════════════════════════════════════
     * 📝 Жишээ: Service бүртгэх
     * ════════════════════════════════════════════════════════════════
     *
     * ```php
     * protected function registerServices(
     *     ContainerInterface &$container,
     *     ServerRequestInterface $request
     * ): void {
     *     // PDO шаардлагатай service (Lazy loading - request-ээс PDO авч)
     *     $container->set('mailer', function(ContainerInterface $c) use ($request) {
     *         $pdo = $request->getAttribute('pdo');
     *         return new \Raptor\Mail\Mailer($pdo);
     *     });
     *
     *     // PDO шаардлагагүй service (Lazy loading)
     *     $container->set('cache', function(ContainerInterface $c) {
     *         return new \App\Services\CacheService();
     *     });
     *
     *     // User шаардлагатай service (Lazy loading - request-ээс User авч)
     *     $container->set('user_service', function(ContainerInterface $c) use ($request) {
     *         $pdo = $request->getAttribute('pdo');
     *         $user = $request->getAttribute('user');
     *         return new \App\Services\UserService($pdo, $user);
     *     });
     * }
     * ```
     *
     * ════════════════════════════════════════════════════════════════
     * 💡 Зөвлөмж
     * ════════════════════════════════════════════════════════════════
     *
     * 1. Request-ээс dependency авах
     *    → $pdo = $request->getAttribute('pdo');
     *    → $user = $request->getAttribute('user');
     *
     * 2. Lazy loading ашиглах (Зөвлөмж)
     *    → Service-г factory function ашиглан бүртгэнэ
     *    → Service-г шаардлагатай үед л үүсгэдэг (performance сайжирна)
     *    → $container->set('mailer', function(ContainerInterface $c) use ($request) {
     *          $pdo = $request->getAttribute('pdo');
     *          return new \Raptor\Mail\Mailer($pdo);
     *      });
     *
     * 3. Factory function-д use ($request) ашиглах
     *    → Closure дотор request-г ашиглахын тулд use keyword заавал ашиглана
     *
     * 4. Service ID-г тодорхой, уншигдахуйц нэр өгөх
     *    ✅ 'mailer', 'cache', 'email_notification'
     *    ❌ 'm', 'c', 'e'
     *
     * @param ContainerInterface $container Container instance
     * @param ServerRequestInterface $request Server request (PDO, User зэрэг dependency-ууд агуулна)
     * @return void
     */
    protected function registerServices(
        ContainerInterface &$container,
        ServerRequestInterface $request
    ): void {
        // ============================================================
        // Энд өөрийн service-уудыг бүртгэнэ
        // ============================================================
        //
        // Жишээ: PDO шаардлагатай service (Lazy loading)
        // $container->set('mailer', function(ContainerInterface $c) use ($request) {
        //     $pdo = $request->getAttribute('pdo');
        //     return new \Raptor\Mail\Mailer($pdo);
        // });
        //
        // Жишээ: PDO шаардлагагүй service (Lazy loading)
        // $container->set('cache', function(ContainerInterface $c) {
        //     return new \MyNamespace\CacheService();
        // });
        //
        // Жишээ: User шаардлагатай service (Lazy loading)
        // $container->set('user_service', function(ContainerInterface $c) use ($request) {
        //     $pdo = $request->getAttribute('pdo');
        //     $user = $request->getAttribute('user');
        //     return new \MyNamespace\UserService($pdo, $user);
        // });
        //
    }
}

