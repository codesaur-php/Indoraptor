<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Twig\TwigFilter;

use codesaur\Template\TwigTemplate;
use codesaur\Http\Message\ReasonPrhase;

use Raptor\Authentication\User;
use Raptor\Log\Logger;

/**
 * Class Controller
 *
 * Indoraptor Framework-ийн бүх Controller ангийн үндсэн суурь.
 *
 * Энэ анги нь дараах чухал боломжуудыг нийтлэг байдлаар хангана:
 *
 *  ────────────────────────────────────────────────────────────────
 *  ✔ PDO холболт (DB access)
 *  ✔ Нэвтэрсэн хэрэглэгчийн мэдээлэл (User объект)
 *  ✔ RBAC эрх шалгах (isUser / isUserCan гэх мэт)
 *  ✔ Route линк үүсгэх (generateRouteLink)
 *  ✔ Localization (text(), getLanguageCode())
 *  ✔ Twig template рендерлэх тусгай wrapper (twigTemplate)
 *  ✔ JSON response (respondJSON)
 *  ✔ Redirect хийх (redirectTo)
 *  ✔ Индолог (indolog) - системийн протокол хөтлөх
 *  ✔ Алдаа логлох (errorLog)
 *  ────────────────────────────────────────────────────────────────
 *
 * Raptor-ийн бүх Controller-ууд энэ ангийг өргөтгөснөөр
 * дээрх боломжуудыг нэг мөр ашиглах боломжтой болдог.
 *
 * @package Raptor
 */
abstract class Controller extends \codesaur\Http\Application\Controller
{
    use \codesaur\DataObject\PDOTrait;

    /**
     * Controller constructor.
     *
     * Request объект дотор хадгалсан PDO instance-ийг
     * татаж авч $this->pdo хувьсагчид онооно.
     *
     * @param ServerRequestInterface $request
     */
    public function __construct(ServerRequestInterface $request)
    {
        parent::__construct($request);

        // Database connection
        $this->pdo = $request->getAttribute('pdo');
    }

    /**
     * Нэвтэрсэн хэрэглэгчийн объект (User) авах.
     *
     * Хэрэв хэрэглэгч нэвтрээгүй бол null буцаана.
     *
     * @return User|null
     */
    public final function getUser(): ?User
    {
        return $this->getAttribute('user');
    }

    /**
     * Нэвтэрсэн хэрэглэгчийн ID авах.
     *
     * Хэрэв хэрэглэгч нэвтрээгүй бол null буцаана.
     *
     * @return int|null
     */
    public final function getUserId(): ?int
    {
        return $this->getUser()?->profile['id'];
    }


    /**
     * Хэрэглэгч нэвтэрсэн эсэхийг шалгах.
     *
     * @return bool
     */
    public final function isUserAuthorized(): bool
    {
        return $this->getUser() instanceof User;
    }

    /**
     * Хэрэглэгч тодорхой RBAC дүр (role)-тэй эсэх.
     *
     * @param string $role
     * @return bool
     */
    public final function isUser(string $role): bool
    {
        return $this->getUser()?->is($role) ?? false;
    }

    /**
     * Хэрэглэгч тодорхой RBAC permission-тэй эсэх.
     *
     * @param string $permission
     * @return bool
     */
    public final function isUserCan(string $permission): bool
    {
        return $this->getUser()?->can($permission) ?? false;
    }

    /**
     * Програмын суурин (subdirectory) замыг буцаана.
     * Apache + Nginx аль алинд зөв ажиллана.
     *
     * @return string
     */
    protected final function getScriptPath(): string
    {
        $script_path = \dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
        return ($script_path === '\\' || $script_path === '/' || $script_path === '.') ? '' : $script_path;
    }

    /**
     * Веб үндэс хавтасыг тодорхойлох.
     *
     * @return string
     */
    protected final function getDocumentRoot(): string
    {
        return \dirname($this->getRequest()->getServerParams()['SCRIPT_FILENAME']);
    }

    /**
     * Route нэр болон параметр ашиглан URL үүсгэх.
     *
     * @param string $routeName   Route name
     * @param array  $params      Dynamic parameters
     * @param bool   $is_absolute Absolute URL үүсгэх эсэх
     * @param string $default     Алдаа гарвал буцаах URL
     *
     * @return string
     */
    public final function generateRouteLink(
        string $routeName,
        array $params = [],
        bool $is_absolute = false,
        string $default = '#'
    ): string {
        try {
            $route_path = $this->getAttribute('router')->generate($routeName, $params);
            $pattern = $this->getScriptPath() . $route_path;

            if (!$is_absolute) {
                return $pattern;
            }

            return (string) $this->getRequest()->getUri()->withPath($pattern);
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return $default;
        }
    }

    /**
     * HTTP Response Code-г header-аар тохируулах.
     *
     * Энэ функц нь зөвхөн стандарт HTTP статус код
     * (ReasonPhrase::STATUS_XXX гэж тодорхойлогдсон) үед л
     * `http_response_code()`-г дуудах бөгөөд дараах тохиолдолд ямар ч үйлдэл хийгдэхгүй:
     *
     *   - Header аль хэдийн илгээгдсэн бол
     *   - Код хоосон буюу 200 (OK) бол
     *   - ReasonPhrase-д бүртгэгдээгүй, стандарт HTTP статус биш бол
     *
     * Өөрөөр хэлбэл, **стандарт бус HTTP код илгээгдэхээс сэргийлж юу ч хийхгүйгээр шууд буцна.**
     *
     * @param int|string $code  HTTP статус код
     * @return void
     */
    protected function headerResponseCode(int|string $code)
    {
        if (\headers_sent() || empty($code) || $code == 200
            || !\defined(ReasonPrhase::class . "::STATUS_$code")
        ) {
            return; // үйлдэл хийхгүй
        }

        \http_response_code($code);
    }

    /**
     * Системд идэвхтэй байгаа localization (хэлний) code-г буцаана.
     *
     * Анхаар: LocalizationMiddleware нь request объектын attributes дунд
     * 'localization' нэртэй массивыг inject хийгээгүй тохиолдолд
     * хоосон string ('') буцна. Энэ нь хэл тодорхойлоогүй гэсэн үг.
     *
     * @return string  Хэлний код (жишээ: 'mn', 'en', 'pl') эсвэл ''
     */
    public final function getLanguageCode(): string
    {
        return $this->getAttribute('localization')['code'] ?? '';
    }

    /**
     * Системд бүртгэлтэй бүх хэлний жагсаалтыг буцаана.
     *
     * Анхаар: LocalizationMiddleware ажиллаагүй бол 'localization'
     * attribute байхгүй тул хоосон массив [] буцна.
     *
     * @return array  Хэлний жагсаалт (code, name г.м) эсвэл []
     */
    public final function getLanguages(): array
    {
        return $this->getAttribute('localization')['language'] ?? [];
    }

    /**
     * Localization key-г орчуулаад буцаах.
     *
     * Анхаар: LocalizationMiddleware нь орчуулгын текстүүдийг
     * request attributes → ['localization']['text'] массивт inject хийгээгүй бол
     * тухайн текст олдохгүй бөгөөд:
     *   → $default утга эсвэл {key} форматаар буцна
     *
     *   - Хөгжүүлэлтийн горимд (CODESAUR_DEVELOPMENT = true)
     *       → "TEXT NOT FOUND: key" гэж system лог руу бичнэ
     *
     * @param string $key       Орчуулгын түлхүүр
     * @param mixed  $default   Түлхүүр олдохгүй үед буцах утга
     * @return string           Орчуулсан текст эсвэл default
     */
    public final function text($key, $default = null): string
    {
        if (isset($this->getAttribute('localization')['text'][$key])) {
            return $this->getAttribute('localization')['text'][$key];
        }

        if (CODESAUR_DEVELOPMENT) {
            \error_log("TEXT NOT FOUND: $key");
        }

        return $default ?? '{' . $key . '}';
    }

    /**
     * Twig темплейт рендерлэх тусгай wrapper.
     *
     * Энэ функц нь TwigTemplate объектыг үүсгээд түүнд нийтлэг хувьсагчдыг
     * автоматаар дамжуулна. Үүнд:
     *
     *   - user             → Нэвтэрсэн хэрэглэгчийн объект (User)
     *   - index            → Системийн суурин зам (script path)
     *   - localization     → Хэл / орчуулгын мэдээлэл
     *   - request          → Одоогийн хүсэлтийн URL зам
     *
     * Эдгээр бүх өгөгдөл нь PSR-7 стандартын дагуу
     * ServerRequestInterface::getAttribute() API-г ашиглан
     * middleware нь (LocalizationMiddleware, JWTAuthMiddleware гэх мэт)
     * request attributes дээр inject хийгдсэн байдаг.
     *
     * Мөн дараах Twig filter-үүдийг бүртгэж өгнө:
     *   - {{ 'key'|text }}     → Localization орчуулга
     *   - {{ 'route'|link() }} → Route name ашиглан URL үүсгэх
     *
     * @param string $template  Рендерлэх template файл
     * @param array  $vars      Template-д дамжуулах нэмэлт хувьсагчид
     *
     * @return TwigTemplate
     */
    public function twigTemplate(string $template, array $vars = []): TwigTemplate
    {
        $twig = new TwigTemplate($template, $vars);

        // PSR-7 request attributes-ээс дамжиж ирсэн өгөгдлүүд
        $twig->set('user', $this->getUser());
        $twig->set('index', $this->getScriptPath());
        $twig->set('localization', $this->getAttribute('localization'));
        $twig->set('request', \rawurldecode($this->getRequest()->getUri()->getPath()));

        // Localization filter
        $twig->addFilter(new TwigFilter('text', function (string $key, $default = null): string {
            return $this->text($key, $default);
        }));

        // Route generator filter
        $twig->addFilter(new TwigFilter('link', function (string $routeName, array $params = [], bool $is_absolute = false): string {
            return $this->generateRouteLink($routeName, $params, $is_absolute);
        }));

        return $twig;
    }


    /**
     * JSON буцаах зориулалттай utility функц.
     *
     * @param array $response
     * @param int|string $code
     * @return void
     */
    public function respondJSON(array $response, int|string $code = 0): void
    {
        if (!\headers_sent()) {
            if (!empty($code) && \is_int($code) && $code != 200
                && \defined(ReasonPrhase::class . "::STATUS_$code")
            ) {
                \http_response_code($code);
            }

            \header('Content-Type: application/json');
        }

        echo \json_encode($response) ?: '{}';
    }

    /**
     * Route нэрээр redirect хийх.
     *
     * @param string $routeName
     * @param array $params
     * @return void
     */
    public function redirectTo(string $routeName, array $params = [])
    {
        $link = $this->generateRouteLink($routeName, $params);
        \header("Location: $link", false, 302);
        exit;
    }

    /**
     * Индолог (Indoraptor logging) - системийн үйлдлийг log хүснэгтэд бичих.
     *
     * Энэ логийн механизм нь PSR-3 стандартын LogLevel болон
     * AbstractLogger загварт нийцсэн бөгөөд $level параметр нь заавал
     * \Psr\Log\LogLevel доторх стандарт log түвшнүүдийн аль нэг байх ёстой:
     *
     *   - LogLevel::EMERGENCY
     *   - LogLevel::ALERT
     *   - LogLevel::CRITICAL
     *   - LogLevel::ERROR
     *   - LogLevel::WARNING
     *   - LogLevel::NOTICE
     *   - LogLevel::INFO
     *   - LogLevel::DEBUG
     *
     * Log context дотор дараах мэдээллүүд автоматаар түүдэг:
     *   - server_request (method, path, remote_addr)
     *   - parsed body (POST өгөгдөл)
     *   - uploaded files (файл upload)
     *   - authenticated user info (id, name, email, гэх мэт)
     *
     * Хэрвээ Logger::log() дуудах үед хүснэгтийн нэр ($table) эсвэл
     * мессеж ($message) хоосон бол лог бичилтийг алгасан Exception шиднэ.
     *
     * @param string $table   Лог бичих хүснэгтийн нэр
     * @param string $level   PSR-3 стандартын log level
     * @param string $message Лог мессеж
     * @param array  $context Нэмэлт контекст мэдээлэл
     *
     * @see \Raptor\Log\Logger
     */
    protected final function indolog(string $table, string $level, string $message, array $context = [])
    {
        try {
            if (empty($table) || empty($message)) {
                throw new \InvalidArgumentException("Log table info can't be empty!");
            }

            // Server request metadata
            if (!isset($context['server_request'])) {
                $server_request = [
                    'code'   => $this->getLanguageCode(),
                    'method' => $this->getRequest()->getMethod(),
                    'target' => $this->getRequest()->getRequestTarget(),
                ];

                if (isset($this->getRequest()->getServerParams()['REMOTE_ADDR'])) {
                    $server_request['remote_addr'] =
                        $this->getRequest()->getServerParams()['REMOTE_ADDR'];
                }

                if (!empty($this->getRequest()->getParsedBody())) {
                    $server_request['body'] = $this->getRequest()->getParsedBody();
                }

                if (!empty($this->getRequest()->getUploadedFiles())) {
                    $server_request['files'] = $this->getRequest()->getUploadedFiles();
                }

                $context['server_request'] = $server_request;
            }

            // Authenticated user metadata
            $auth_user = $this->getUser()?->profile ?? [];
            if (isset($auth_user['id']) && !isset($context['auth_user'])) {
                $context['auth_user'] = [
                    'id'         => $auth_user['id'],
                    'username'   => $auth_user['username'],
                    'first_name' => $auth_user['first_name'],
                    'last_name'  => $auth_user['last_name'],
                    'phone'      => $auth_user['phone'],
                    'email'      => $auth_user['email'],
                ];
            }

            // Log бичих
            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $logger->log($level, $message, $context);

        } catch (\Throwable $e) {
            $this->errorLog($e);
        }
    }


    /**
     * Хөгжүүлэлтийн орчинд алдааны мессежийг системийн лог руу бичих.
     *
     * @param \Throwable $e
     * @return void
     */
    protected final function errorLog(\Throwable $e)
    {
        if (!CODESAUR_DEVELOPMENT) {
            return;
        }

        \error_log($e->getMessage());
    }
}
