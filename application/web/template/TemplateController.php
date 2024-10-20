<?php

namespace Web\Template;

use codesaur\Template\TwigTemplate;

use Raptor\Content\PagesModel;

class TemplateController extends \Raptor\Controller
{
    public function template(string $template, array $vars = []): TwigTemplate
    {
        $index = $this->twigTemplate(\dirname(__FILE__) . '/index.html');
        $index->set('content', $this->twigTemplate($template, $vars));
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $index->set($key, $value);
        }
        $index->set('mainmenu', $this->getMainMenu($this->getLanguageCode()));
        $index->set('footer_navigation', $this->getFooterNavigation($this->getLanguageCode()));
        return $index;
    }
    
    public function getMainMenu(string $code): array
    {
        $pages = [];
        $pages_table = (new PagesModel($this->pdo))->getName();
        $pages_query =
            'SELECT id, parent_id, title, position, category, type, link, name ' .
            "FROM $pages_table WHERE code=:code AND is_active=1 AND published=1 AND (type='menu' OR type='mega-menu' OR type='footer-menu') ORDER BY position, id";
        $stmt = $this->prepare($pages_query);
        $stmt->bindParam(':code', $code, \PDO::PARAM_STR);
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            while ($row = $stmt->fetch()) {
                $pages[$row['id']] = $row;
            }
        }
        return $this->buildMenu($pages);
    }
    
    private function buildMenu(array $pages, int $parent_id = 0): array
    {
        $navigation = [];
        foreach ($pages as $element) {
            if ($element['parent_id'] == $parent_id) {
                $children = $this->buildMenu($pages, $element['id']);
                if ($children) {
                    $element['submenu'] = $children;
                }
                $navigation[$element['id']] = $element;
            }
        }
        return $navigation;
    }
    
    public function getFooterNavigation(string $code): array
    {
        $pages = [];
        $pages_table = (new PagesModel($this->pdo))->getName();
        $pages_query =
            'SELECT id, title, position, link, name ' .
            "FROM $pages_table WHERE code=:code AND is_active=1 AND published=1 AND type='footer-menu' ORDER BY position, id";
        $stmt = $this->prepare($pages_query);
        $stmt->bindParam(':code', $code, \PDO::PARAM_STR);
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            while ($row = $stmt->fetch()) {
                $pages[$row['id']] = $row;
            }
        }
        return $pages;
    }
}
