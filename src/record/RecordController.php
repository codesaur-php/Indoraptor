<?php

namespace Indoraptor\Record;

class RecordController extends \Indoraptor\IndoController
{
    public function internal()
    {
        $model = $this->grabmodel();
        $payload = $this->getParsedBody();
        if ($payload['record']
                && !empty($payload['condition'])
                && method_exists($model, 'update')
        ) {
            if (isset($payload['content'])) {
                $id = $model->update($payload['record'], $payload['content'], $payload['condition']);
            } else {
                $id = $model->update($payload['record'], $payload['condition']);
            }            
        }

        if ($id === false) {
            return $this->notFound();
        }
        
        $this->respond(array(
            'id'    => $id,
            'model' => $this->getClass($model),
            'table' => $model->getName()
        ));
    }
}
