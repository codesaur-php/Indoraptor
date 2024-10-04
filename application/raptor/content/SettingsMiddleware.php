<?php

namespace Raptor\Content;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SettingsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $pdo = $request->getAttribute('pdo');
            $model = new SettingsModel($pdo);
            $stmt = $pdo->prepare(
                'SELECT p.keywords, p.email, p.phone, p.favico, p.shortcut_icon, p.apple_touch_icon, p.config, ' .
                'c.title, c.logo, c.description, c.urgent, c.contact, c.address, c.copyright ' .
                "FROM {$model->getName()} as p INNER JOIN {$model->getContentName()} as c ON p.id=c.parent_id " .
                'WHERE p.is_active=1 AND p.alias=:alias AND c.code=:code ' .
                'ORDER BY p.updated_at desc LIMIT 1'
            );
            $alias = $request->getAttribute('user')?->getOrganization()['alias'] ?? 'system';
            $code = $request->getAttribute('localization')['code']
                ?? throw new \Exception('Please run LocalizationMiddleware before me!');
            $stmt->bindParam(':alias', $alias, \PDO::PARAM_STR);
            $stmt->bindParam(':code', $code, \PDO::PARAM_STR);
            if ($stmt->execute()) {
                $settings = $stmt->fetchAll()[0] ?? [];
                if (!empty($settings['config'])) {
                    $settings['config'] = \json_decode($settings['config'], true);
                }
            }
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($e->getMessage());
            }
        }
        
        return $handler->handle($request->withAttribute('settings', $settings ?? []));
    }
}
