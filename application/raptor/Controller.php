<?php

namespace Raptor;

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
    
    public final function getUserId(): ?int
    {
        return $this->getUser()?->profile['id'];
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
        if (\headers_sent() || empty($code) || $code == 200
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
    
    public function twigTemplate(string $template, array $vars = []): TwigTemplate
    {
        $twig = new TwigTemplate($template, $vars);
        $twig->set('user', $this->getUser());
        $twig->set('index', $this->getScriptPath());
        $twig->set('localization', $this->getAttribute('localization'));
        $twig->set('request', \rawurldecode($this->getRequest()->getUri()->getPath()));
        $twig->addFilter(new TwigFilter('text', function (string $key, $default = null): string
        {
            return $this->text($key, $default);
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
            if (!empty($code) && \is_int($code) && $code != 200
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
    
    protected final function indolog(string $table, string $level, string $message, array $context = [])
    {
        try {
            if (empty($table) || empty($message)) {
                throw new \InvalidArgumentException("Log table info can't be empty!");
            }
            
            $server_request = [
                'code' => $this->getLanguageCode(),
                'method' => $this->getRequest()->getMethod(),
                'target' => $this->getRequest()->getRequestTarget()
            ];
            if (isset($this->getRequest()->getServerParams()['REMOTE_ADDR'])) {
                $server_request['remote_addr'] = $this->getRequest()->getServerParams()['REMOTE_ADDR'];
            }
            if (!empty($this->getRequest()->getParsedBody())) {
                $server_request['body'] = $this->getRequest()->getParsedBody();
            }
            if (!empty($this->getRequest()->getUploadedFiles())) {
                $server_request['files'] = $this->getRequest()->getUploadedFiles();
            }
            $context['server_request'] = $server_request;
                    
            $auth_user = $this->getUser()?->profile ?? [];
            if (isset($auth_user['id'])
                && !isset($context['auth_user']))
            {
                $context['auth_user'] = [
                    'id' => $auth_user['id'],
                    'username' => $auth_user['username'],
                    'first_name' => $auth_user['first_name'],
                    'last_name' => $auth_user['last_name'],
                    'phone' => $auth_user['phone'],
                    'email' => $auth_user['email']
                ];
            }
            
            $logger = new Logger($this->pdo);
            $logger->setTable($table);
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
