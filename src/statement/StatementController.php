<?php

namespace Indoraptor\Statement;

use PDO;

class StatementController extends \Indoraptor\IndoController
{
    public function index()
    {
        if ($this->getRequest()->getMethod() != 'INTERNAL'
            && !$this->isAuthorized()
        ) {
            return $this->unauthorized();
        }
        
        $payload = $this->getParsedBody();
        if (!isset($payload['query'])) {
            return $this->badRequest('Invalid payload');
        }
        
        $stmt = $this->pdo->prepare($payload['query']);
        if (isset($payload['bind'])) {
            foreach ($payload['bind'] as $parametr => $values) {
                if (isset($values['var'])) {
                    if (isset($values['length'])) {
                        $stmt->bindParam($parametr, $values['var'], $values['type'] ?? PDO::PARAM_STR, $values['length']);
                    } else {
                        $stmt->bindParam($parametr, $values['var'], $values['type'] ?? PDO::PARAM_STR);
                    }
                }
            }
        }
        $stmt->execute();
        
        $result = array();
        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($row['id'])) {
                    $result[$row['id']] = $row;
                } else {
                    $result[] = $row;
                }
            }
        }

        return $this->respond($result);
    }
}
