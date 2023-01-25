<?php

namespace Indoraptor\Localization;

use Psr\Http\Message\ResponseInterface;

class CountriesController extends \Indoraptor\IndoController
{
    public function index(): ResponseInterface
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $code = $this->getQueryParams()['code'] ?? null;
        $model = new CountriesModel($this->pdo);
        $rows = $model->retrieve($code);
        if (empty($rows)) {
            return $this->notFound();
        }
        
        return $this->respond($rows);
    }
}
