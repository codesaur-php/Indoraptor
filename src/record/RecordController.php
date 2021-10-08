<?php

namespace Indoraptor\Record;

class RecordController extends \Indoraptor\IndoController
{
    public function internal()
    {
        $model = $this->grabmodel();
        $payload = $this->getParsedBody();
        if ($payload['record']
                && method_exists($model, 'update')
        ) {
            $id = $model->update($payload['record']);
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
