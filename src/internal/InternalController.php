<?php

namespace Indoraptor\Internal;

use Psr\Http\Message\ResponseInterface;

class InternalController extends \Indoraptor\IndoController
{
    public function executeFetchAll(): ResponseInterface
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
        
        $stmt = $this->prepare($payload['query']);
        if (isset($payload['bind'])) {
            foreach ($payload['bind'] as $parametr => $values) {
                if (isset($values['var'])) {
                    if (isset($values['length'])) {
                        $stmt->bindParam($parametr, $values['var'], $values['type'] ?? \PDO::PARAM_STR, $values['length']);
                    } else {
                        $stmt->bindParam($parametr, $values['var'], $values['type'] ?? \PDO::PARAM_STR);
                    }
                }
            }
        }
        $stmt->execute();
        
        return $this->respond($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
