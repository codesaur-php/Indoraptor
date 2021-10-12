<?php

namespace Indoraptor\Record;

class RecordController extends \Indoraptor\IndoController
{
    public function internal()
    {
        $payload = $this->getParsedBody();
        if (empty($payload['values'])) {
            return $this->badRequest();
        }
        
        $model = $this->grabmodel();
        if (method_exists($model, 'getRowBy')) {
            $record = $model->getRowBy($payload['values'], $payload['orderBy'] ?? null);
        }

        if (empty($record)) {
            return $this->notFound();
        }
        
        $this->respond(array(
            'record' => $record,
            'model'  => get_class($model),
            'table'  => $model->getName()
        ));
    }
    
    public function internal_rows()
    {
        $model = $this->grabmodel();
        $payload = $this->getParsedBody();
        if (method_exists($model, 'getRows')) {
            $rows = $model->getRows($payload);
        }

        if (empty($rows)) {
            return $this->notFound();
        }
        
        $this->respond(array(
            'rows'  => $rows,
            'model' => get_class($model),
            'table' => $model->getName()
        ));
    }
    
    public function internal_update()
    {
        $model = $this->grabmodel();
        $payload = $this->getParsedBody();
        if (isset($payload['record'])
                && !empty($payload['condition'])
                && method_exists($model, 'update')
        ) {
            if (isset($payload['content'])) {
                $id = $model->update($payload['record'], $payload['content'], $payload['condition']);
            } else {
                $id = $model->update($payload['record'], $payload['condition']);
            }            
        }

        if ($id ?? false) {
            $this->respond(array(
                'id'    => $id,
                'model' => get_class($model),
                'table' => $model->getName()
            ));
        }
        
        return $this->notFound();
    }
}
