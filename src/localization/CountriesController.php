<?php

namespace Indoraptor\Localization;

class CountriesController extends \Indoraptor\IndoController
{
    public function index()
    {
        if (!$this->isAuthorized()) {
            return $this->unauthorized();
        }
        
        $code = $this->getQueryParam('code');
        $model = new CountriesModel($this->pdo);
        $rows = $model->retrieve($code);        
        if (empty($rows)) {
            return $this->notFound();
        }
        
        return $this->respond($rows);
    }
}
