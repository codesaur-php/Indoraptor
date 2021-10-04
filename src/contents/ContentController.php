<?php

namespace Indoraptor\Contents;

use codesaur\Contents\ContentModel;

class ContentController extends \Indoraptor\IndoController
{
    public function index()
    {
        $payload = $this->getParsedBody();
        if (empty($payload['table'])
                || empty($payload['keyword'])
        ) {
            return $this->badRequest();
        }
        
        if (is_array($payload['keyword'])) {
            $keywords = array_values($payload['keyword']);
        } else {
            $keywords = array($payload['keyword']);
        }

        $content = new ContentModel($this->pdo);
        $content->setTable($payload['table']);
        
        $values = array();
        if (!empty($payload['code'])) {
            $values['c.code'] = $payload['code'];
        }

        $data = array();
        foreach ($keywords as $word) {
            $values['p.keyword'] = $word;
            $data[$word] = $content->getRowBy($values);
        } 
        
        if (!empty($data)) {
            return $this->respond($data);
        }
        
        return $this->notFound('Content not found');
    }
}
