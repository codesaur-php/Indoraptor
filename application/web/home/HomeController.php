<?php

namespace Web\Home;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Raptor\Content\NewsModel;
use Raptor\Content\PagesModel;
use Raptor\Content\FilesModel;

class HomeController extends TemplateController
{
    public function index()
    {
        $language_code = $this->getLanguageCode();
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt_recent = $this->prepare(
            "SELECT id, title, photo, published_at FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND code=:code " .
            'ORDER BY published_at desc LIMIT 20'
        );
        $recent = $stmt_recent->execute([':code' => $language_code]) ? $stmt_recent->fetchAll() : [];        
        $vars = ['recent' => $recent];
        $home = $this->template(__DIR__ . '/home.html', $vars);
        $home->render();
        
        $this->indolog('web', LogLevel::NOTICE, '[{server_request.code}] Нүүр хуудсыг уншиж байна', ['action' => 'home']);
    }
    
    public function contact()
    {
        $pages_table = (new PagesModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT id FROM $pages_table " .
            "WHERE is_active=1 AND published=1 AND code=:code AND link LIKE '%/contact' " .
            'ORDER BY published_at desc LIMIT 1'
        );
        $contact = $stmt->execute([':code' => $this->getLanguageCode()]) ? $stmt ->fetch() : [];
        return $this->page($contact['id'] ?? -1);
    }
    
    public function page(int $id)
    {
        $model = new PagesModel($this->pdo);
        $table = $model->getName();
        $record = $model->getRowWhere([
            'id' => $id,
            'is_active' => 1
        ]);
        if (empty($record)) {
            throw new \Error('Хуудас олдсонгүй', 404);
        }
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows(
            [
                'WHERE' => "record_id=$id AND is_active=1"
            ]
        );
        $this->template(__DIR__ . '/page.html', $record)->render();
        
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE $table SET read_count=$read_count WHERE id=$id");
        
        $this->indolog(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /page/{id}] {title} - хуудсыг уншиж байна',
            ['action' => 'page', 'id' => $id, 'title' => $record['title']]
        );
    }
    
    public function news(int $id)
    {
        $model = new NewsModel($this->pdo);
        $table = $model->getName();
        $record = $model->getRowWhere([
            'id' => $id,
            'is_active' => 1
        ]);
        if (empty($record)) {
            throw new \Error('Мэдээ олдсонгүй', 404);
        }
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows(
            [
                'WHERE' => "record_id=$id AND is_active=1"
            ]
        );
        $this->template(__DIR__ . '/news.html', $record)->render();
        
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE $table SET read_count=$read_count WHERE id=$id");
        
        $this->indolog(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /news/{id}] {title} - мэдээг уншиж байна',
            ['action' => 'news', 'id' => $id, 'title' => $record['title']]
        );
    }
    
    public function language(string $code)
    {
        $from = $this->getLanguageCode();
        $language = $this->getLanguages();
        if (isset($language[$code]) && $code != $from) {
            $_SESSION['WEB_LANGUAGE_CODE'] = $code;
        }
        
        $script_path = $this->getScriptPath();
        $home = (string) $this->getRequest()->getUri()->withPath($script_path);
        \header("Location: $home", false, 302);
        exit;
    }
}
