<?php

namespace Indoraptor\Contents;

class ReferenceController extends \Indoraptor\IndoController
{
    public function index()
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody();
        if (empty($payload['table'])) {
            return $this->badRequest();
        }
        
        $records = array();
        $table = preg_replace('/[^A-Za-z0-9_-]/', '', $payload['table']);
        $initial = get_class_methods(ReferenceInitial::class);
        if (!empty($table)
            && (in_array("reference_$table", $initial)
            || $this->hasTable("reference_$table"))
        ) {
            $reference = new ReferenceModel($this->pdo);
            $reference->setTable($table, $_ENV['INDO_DB_COLLATION'] ?? 'utf8_unicode_ci');
            $rows = $reference->getRows($payload['condition'] ?? []);
            foreach ($rows as $row) {
                $records[$row['keyword']] = $row['content'];
            }
        }
        
        return $this->respond($records);
    }
}
