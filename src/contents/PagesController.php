<?php

namespace Indoraptor\Contents;

use Psr\Http\Message\ResponseInterface;

class PagesController extends \Indoraptor\IndoController
{
    public function navigation(string $code): ResponseInterface
    {
        $language = \preg_replace('/[^a-z]/', '', $code);
        $queryParams = $this->getQueryParams();
        $is_active = $queryParams['is_active'] ?? 1;
        $is_visible = $queryParams['is_visible'] ?? 1;
        $condition = "c.code='$language'";
        if ($is_active == 1) {
            $condition .= ' AND p.is_active=1';
        }
        if ($is_visible == 1) {
            $condition .= ' AND c.is_visible=1';
        }
        $pages_query = 
            'SELECT p.id, c.title, p.parent_id, p.position, c.is_visible, p.is_active ' .
            'FROM pages as p JOIN pages_content as c ON p.id=c.parent_id ' .
            "WHERE $condition ORDER By p.position, p.id";
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
