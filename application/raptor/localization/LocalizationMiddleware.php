<?php

namespace Raptor\Localization;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LocalizationMiddleware implements MiddlewareInterface
{   
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
            return ['en' => 'English'];
        }
    }
    
    private function retrieveTexts(ServerRequestInterface $request, string $langCode)
    {
        $texts = [];
        try {
            $tables = ['default', 'dashboard', 'user'];        
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
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $language = $this->retrieveLanguage($request);
        if (isset($_SESSION['RAPTOR_LANGUAGE_CODE'])
            && isset($language[$_SESSION['RAPTOR_LANGUAGE_CODE']])
        ) {
            $code = $_SESSION['RAPTOR_LANGUAGE_CODE'];
        } else {
            $code = \key($language);
        }
        
        $text = $this->retrieveTexts($request, $code);
        
        return $handler->handle($request->withAttribute('localization',
            ['language' => $language, 'code' => $code, 'text' => $text]));
    }
}
