<?php

namespace Web\Template;

use codesaur\Template\TwigTemplate;

use Raptor\Content\PagesModel;

class TemplateController extends \Raptor\Controller
{
    public function template(string $template, array $vars = []): TwigTemplate
    {
        $porto = $this->twigTemplate(\dirname(__FILE__) . '/porto-corporate-8.html');
        $porto->set('content', $this->twigTemplate($template, $vars));
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $porto->set($key, $value);
        }
        $porto->set('main_menu', $this->getMainMenu($this->getLanguageCode()));
        $porto->set('important_menu', $this->getImportantMenu($this->getLanguageCode()));
        return $porto;
    }
    
    public function getMainMenu(string $code): array
    {
        $pages = [];
        $pages_table = (new PagesModel($this->pdo))->getName();
        $pages_query =
            'SELECT id, parent_id, title, type, position, link ' .
            "FROM $pages_table " .
            "WHERE code=:code AND is_active=1 AND published=1 AND type!='special-page' " .
            'ORDER BY position, id';
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
    
    public function getImportantMenu(string $code): array
    {
        $pages = [];
        $pages_table = (new PagesModel($this->pdo))->getName();
        $pages_query =
            'SELECT id, title, link ' .
            "FROM $pages_table " .
            "WHERE code=:code AND is_active=1 AND published=1 AND type='important-menu' " .
            "ORDER BY position, id";
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
