<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

class LocalizationController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {        
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($this->getDriverName() == 'pgsql') {
                $query = 
                    'SELECT tablename FROM pg_catalog.pg_tables ' .
                    "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like 'localization_text_%_content'";
            } else {
                $query = 'SHOW TABLES LIKE ' . $this->quote('localization_text_%_content');
            }
            $text_content_tables = $this->query($query)->fetchAll();
            $text_tables = [];
            foreach ($text_content_tables as $result) {
                $text_tables[] = \substr(reset($result), \strlen('localization_text_'), -\strlen('_content'));
            }
            $text_initials = \get_class_methods(TextInitial::class);
            foreach ($text_initials as $value) {
                $text_initial = \substr($value, \strlen('localization_text_'));
                if (!empty($text_initial) && !\in_array($text_initial, $text_tables)) {
                    $text_tables[] = $text_initial;
                }
            }

            $texts = [];
            foreach ($text_tables as $table) {
                $model = new TextModel($this->pdo);
                $model->setTable($table);
                $texts[$table] = $model->getRows(['WHERE' => 'p.is_active=1', 'ORDER BY' => 'p.keyword']);
            }
            $languages = (new LanguageModel($this->pdo))->getRows(['WHERE' => 'is_active=1']);
            $dashboard = $this->twigDashboard(
                __DIR__ . '/localization-index.html',
                ['languages' => $languages, 'texts' => $texts]
            );
            $dashboard->set('title', $this->text('localization'));
            $dashboard->render();
            
            $this->indolog('localization', LogLevel::NOTICE, 'Хэл ба Текстүүдийн жагсаалтыг үзэж байна', ['action' => 'localization-index']);
        } catch (\Throwable $err) {
             $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        }
    }
}
