<?php

namespace Web\Home;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Raptor\Content\NewsModel;
use Raptor\Content\PagesModel;
use Raptor\File\FilesModel;

class HomeController extends TemplateController
{
    public function index()
    {
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt_recent = $this->prepare(
            "SELECT id, title, photo, published_at FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND code=:code " .
            'ORDER BY published_at desc LIMIT 25'
        );
        $recent = $stmt_recent->execute([':code' => $this->getLanguageCode()]) ? $stmt_recent->fetchAll() : [];
        
        $vars = ['recent' => $recent];
        $home = $this->template(\dirname(__FILE__) . '/home.html', $vars);
        $home->render();
        $this->indolog('web', LogLevel::NOTICE, 'Нүүр хуудсыг уншиж байна');
    }
    
    public function contact()
    {
        $pages_table = (new PagesModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT id FROM $pages_table " .
            "WHERE is_active=1 AND published=1 AND code=:code AND type='contact' " .
            'ORDER BY published_at desc LIMIT 1'
        );
        $contact = $stmt->execute([':code' => $this->getLanguageCode()]) ? $stmt ->fetch() : [];
        return $this->page($contact['id'] ?? -1);
    }
    
    public function page(int $id)
    {
        $model = new PagesModel($this->pdo);
        $record = $model->getById($id);
        if (empty($record)) {
            throw new \Error('Хуудас олдсонгүй', 404);
        }
        $files = new FilesModel($this->pdo);
        $files->setTable($model->getName());
        $record['files'] = $files->getRows(
            [
                'WHERE' => "record_id=$id AND is_active=1"
            ]
        );
        $this->template(\dirname(__FILE__) . '/page.html', $record)->render();
        
        $this->indolog(
            'web',
            LogLevel::NOTICE,
            "[{$this->getLanguageCode()} : /page/{$record['id']}] {$record['title']} - хуудсыг уншиж байна",
            ['id' => $id, 'model' => PagesModel::class]
        );
        
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE {$model->getName()} SET read_count=$read_count WHERE id=$id");
    }
    
    public function news(int $id)
    {
        $model = new NewsModel($this->pdo);
        $record = $model->getById($id);
        if (empty($record)) {
            throw new \Error('Мэдээ олдсонгүй', 404);
        }
        $files = new FilesModel($this->pdo);
        $files->setTable($model->getName());
        $record['files'] = $files->getRows(
            [
                'WHERE' => "record_id=$id AND is_active=1"
            ]
        );
        $this->template(\dirname(__FILE__) . '/news.html', $record)->render();
        
        $this->indolog(
            'web',
            LogLevel::NOTICE,
            "[{$this->getLanguageCode()} : /news/{$record['id']}] {$record['title']} - мэдээг уншиж байна",
            ['id' => $id, 'model' => PagesModel::class]
        );
        
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE {$model->getName()} SET read_count=$read_count WHERE id=$id");
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

