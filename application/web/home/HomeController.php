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
        $language_code = $this->getLanguageCode();
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt_slider = $this->prepare(
            "SELECT id, title, description, photo FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND category='featured' AND code=:code ".
            'ORDER BY published_at desc'
        );
        $slider_results = $stmt_slider->execute([':code' => $language_code]) ? $stmt_slider->fetchAll() : [];
        $sliders = [];
        $sliders_id_exclude = '';
        foreach ($slider_results as $row) {
            $sliders[$row['id']] = $row;
            $sliders_id_exclude .= "AND id!={$row['id']} ";
        }
        $stmt_latest = $this->prepare(
            "SELECT id, title, photo, published_at FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND code=:code $sliders_id_exclude" .
            'ORDER BY published_at desc LIMIT 4'
        );
        $latest = $stmt_latest ->execute([':code' => $language_code]) ? $stmt_latest->fetchAll() : [];
        
        $stmt_recent = $this->prepare(
            "SELECT id, title, photo, published_at FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND code=:code AND type<>'announcement' AND type<>'price' " .
            'ORDER BY published_at desc LIMIT 20'
        );
        $recent = $stmt_recent->execute([':code' => $language_code]) ? $stmt_recent->fetchAll() : [];
        
        $stmt_announcement = $this->prepare(
            "SELECT id, title, photo, published_at FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND code=:code AND type='announcement' " .
            'ORDER BY published_at desc LIMIT 15'
        );
        $announcements = $stmt_announcement->execute([':code' => $language_code]) ? $stmt_announcement->fetchAll() : [];
        
        $stmt_videos = $this->prepare(
            "SELECT id, title, published_at FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND code=:code AND type='video' " .
            'ORDER BY published_at desc'
        );
        $videos = $stmt_videos->execute([':code' => $language_code]) ? $stmt_videos->fetchAll() : [];
        
        $stmt_prices = $this->prepare(
            "SELECT id, title, photo, published_at FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND code=:code AND type='price' " .
            'ORDER BY published_at desc LIMIT 5'
        );
        $prices = $stmt_prices->execute([':code' => $language_code]) ? $stmt_prices->fetchAll() : [];
        
        $vars = [
            'sliders' => $sliders,
            'latest' => $latest,
            'recent' => $recent,
            'announcements' => $announcements,
            'videos' => $videos,
            'prices' => $prices
        ];
        $home = $this->template(\dirname(__FILE__) . '/home.html', $vars);
        $home->render();
        
        $this->indolog('web', LogLevel::NOTICE, "[$language_code] Нүүр хуудсыг уншиж байна");
    }
    
    public function contact()
    {
        $pages_table = (new PagesModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT id FROM $pages_table " .
            "WHERE is_active=1 AND published=1 AND code=:code AND link='/contact' " .
            'ORDER BY published_at desc LIMIT 1'
        );
        $contact = $stmt->execute([':code' => $this->getLanguageCode()]) ? $stmt->fetch() : [];
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
        $record['breadcrumbs'] = $this->getPageBreadCrumbs($id);
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
    
    private function getPageBreadCrumbs(int $id): array
    {
        $pages = new PagesModel($this->pdo);
        $page = $pages->getById($id);
        $breadcrumbs = [];
        while (!empty($page['parent_id'])) {
            $page = $pages->getById($page['parent_id']);
            $breadcrumbs[] = $page;
        }
        
        return \array_reverse($breadcrumbs);
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
            ['id' => $id, 'model' => NewsModel::class]
        );
        
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE {$model->getName()} SET read_count=$read_count WHERE id=$id");
    }
    
    public function newsType(string $type)
    {
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT id, title, description, photo, read_count, published_at, type, category FROM $news_table " .
            "WHERE is_active=1 AND published=1 AND type=:type AND code=:code " .
            'ORDER BY published_at desc'
        );
        $code = $this->getLanguageCode();
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':code', $code);
        $records = $stmt->execute() ? $stmt->fetchAll() : [];
        if (empty($records)) {
            throw new \Error('Мэдээ олдсонгүй', 404);
        }
        $this->template(\dirname(__FILE__) . '/news-type.html', ['records' => $records])->render();
        
        $this->indolog(
            'web',
            LogLevel::NOTICE,
            "[$code : /news/type/$type] Mэдээнүүдийн жагсаалтыг нээж байна",
            ['type' => $type, 'model' => NewsModel::class]
        );
    }
    
    public function language(string $code)
    {
        $from = $this->getLanguageCode();
        $language = $this->getLanguages();
        if (isset($language[$code]) && $code != $from) {
            $_SESSION['MRPAM_LANGUAGE_CODE'] = $code;
        }
        
        $script_path = $this->getScriptPath();
        $home = (string) $this->getRequest()->getUri()->withPath($script_path);
        \header("Location: $home", false, 302);
        exit;
    }
}

