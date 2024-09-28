<?php

namespace Raptor;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\TwigFilter;

use codesaur\Template\TwigTemplate;
use codesaur\Http\Message\ReasonPrhase;

use Raptor\Authentication\User;
use Raptor\Log\Logger;

abstract class Controller extends \codesaur\Http\Application\Controller
{
    use \codesaur\DataObject\PDOTrait;
    
    public function __construct(ServerRequestInterface $request)
    {
        parent::__construct($request);
        
        $this->pdo = $request->getAttribute('pdo');
    }
    
    public final function getUser(): ?User
    {
        return $this->getAttribute('user');
    }
    
    public final function isUserAuthorized(): bool
    {
        return $this->getUser() instanceof User;
    }
    
    public final function isUser(string $role): bool
    {
        return $this->getUser()?->is($role) ?? false;
    }    
    
    public final function isUserCan(string $permission): bool
    {
        return $this->getUser()?->can($permission) ?? false;
    }
    
    protected final function getScriptPath(): string
    {
        $script_path = \dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
        return ($script_path == '\\' || $script_path == '/' || $script_path == '.') ? '' : $script_path;
    }
    
    protected final function getDocumentRoot(): string
    {
        return \dirname($this->getRequest()->getServerParams()['SCRIPT_FILENAME']);
    }
    
    public final function generateRouteLink(string $routeName, array $params = [], bool $is_absolute = false, string $default = 'javascript:;'): string
    {
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
    
    protected function headerResponseCode(int|string $code)
    {
        if (\headers_sent()
            || empty($code)
            || $code == StatusCodeInterface::STATUS_OK
            || !\defined(ReasonPrhase::class . "::STATUS_$code")
        ) {
            return;
        }
        
        \http_response_code($code);
    }

    public final function getLanguageCode(): string
    {
        return $this->getAttribute('localization')['code'] ?? '';
    }
    
    public final function getLanguages(): array
    {
        return $this->getAttribute('localization')['language'] ?? [];
    }

    public final function text($key): string
    {
        if (isset($this->getAttribute('localization')['text'][$key])) {
            return $this->getAttribute('localization')['text'][$key];
        }

        if (CODESAUR_DEVELOPMENT) {
            \error_log("TEXT NOT FOUND: $key");
        }

        return '{' . $key . '}';
    }
    
    public function twigTemplate(string $template, array $vars = []): TwigTemplate
    {
        $request_path = $this->getRequest()->getUri()->getPath();
        
        $twig = new TwigTemplate($template, $vars);
        $twig->set('user', $this->getUser());
        $twig->set('index', $this->getScriptPath());
        $twig->set('request', \rawurldecode($request_path));
        $twig->set('localization', $this->getAttribute('localization'));

        $twig->addFilter(new TwigFilter('text', function (string $key): string
        {
            return $this->text($key);
        }));

        $twig->addFilter(new TwigFilter('link', function (string $routeName, array $params = [], bool $is_absolute = false): string
        {
            return $this->generateRouteLink($routeName, $params, $is_absolute);
        }));
        
        return $twig;
    }
    
    public function respondJSON(array $response, int|string $code = 0): void
    {
        if (!\headers_sent()) {
            if (!empty($code)
                && \is_int($code)
                && $code != StatusCodeInterface::STATUS_OK
                && \defined(ReasonPrhase::class . "::STATUS_$code")
            ) {
                \http_response_code($code);
            }
            \header('Content-Type: application/json');
        }
        
        echo \json_encode($response) ?: '{}';
    }
    
    public function redirectTo(string $routeName, array $params = [])
    {
        $link = $this->generateRouteLink($routeName, $params);
        \header("Location: $link", false, 302);
        exit;
    }
    
    protected final function indolog(string $table, string $level, string $message, array $context = [], ?int $created_by = null)
    {
        try {
            if (!isset($context['server_request'])) {
                $context['server_request'] = [
                    'code' => $this->getLanguageCode(),
                    'method' => $this->getRequest()->getMethod(),
                    'uri' => (string) $this->getRequest()->getUri()
                ];
                if ($this->getRequest()->getServerParams()['REMOTE_ADDR']) {
                    $context['server_request']['remote_addr'] = $this->getRequest()->getServerParams()['REMOTE_ADDR'];
                }
            }
            
            if (empty($table) || empty($message)) {
                throw new \Exception("Log table info can't be empty!");
            }
            
            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $logger->setCreatedByOnce($created_by ?? \getenv('CODESAUR_USER_ID', true) ?: null);
            $logger->log($level, $message, $context);
        } catch (\Throwable $e) {
            $this->errorLog($e);
        }
    }
    
    protected final function errorLog(\Throwable $e)
    {
        if (!CODESAUR_DEVELOPMENT) {
            return;
        }
        
        \error_log($e->getMessage());
    }
}
