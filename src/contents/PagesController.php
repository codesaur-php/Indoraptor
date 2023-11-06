<?php

namespace Indoraptor\Contents;

use Psr\Http\Message\ResponseInterface;

class PagesController extends \Indoraptor\IndoController
{
    public function navigation(string $code): ResponseInterface
    {
        $queryParams = $this->getQueryParams();
        $is_active = $queryParams['is_active'] ?? 1;
        $published = $queryParams['published'] ?? 1;
        $language = \preg_replace('/[^a-z]/', '', $code);
        $condition = "code='$language'";
        if ($is_active == 1) {
            $condition .= ' AND is_active=1';
        }
        if ($published == 1) {
            $condition .= ' AND published=1';
        }
        
        $pages_model = new PagesModel($this->pdo);        
        $pages_query = 
            'SELECT id, code, title, parent_id, position, category, type, link, name, published, is_active ' .
            "FROM {$pages_model->getName()} WHERE $condition ORDER BY position, id";
        $stmt = $this->prepare($pages_query);
        $stmt->execute();
        $pages = [];
        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (isset($row['id'])) {
                    $pages[$row['id']] = $row;
                } else {
                    $pages[] = $row;
                }
            }
        }
        return $this->respond($this->buildNavigation($pages));
    }
    
    private function buildNavigation(array $pages, int $parent_id = 0)
    {
        $navigation = [];
        foreach ($pages as $element) {
            if ($element['parent_id'] == $parent_id) {
                $children = $this->buildNavigation($pages, $element['id']);
                if ($children) {
                    $element['submenu'] = $children;
                }
                $navigation[$element['id']] = $element;
            }
        }
        return $navigation;
    }
}
