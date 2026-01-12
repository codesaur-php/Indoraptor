<?php

namespace Web\Home;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Raptor\Content\NewsModel;
use Raptor\Content\PagesModel;
use Raptor\Content\FilesModel;

/**
 * Class HomeController
 * ========================================================================
 * 🌐 Public Website Controller (Web Layer)
 * - Indoraptor Framework-ийн веб нүүр хуудасны үндсэн Controller.
 *
 * Энэ контроллерийн үүрэг:
 *   ✔ Нүүр хуудас (/) руу ирсэн хүсэлтийг боловсруулах
 *   ✔ Хуудасны мэдээлэл (PagesModel) үзүүлэх
 *   ✔ Мэдээ мэдээлэл (NewsModel) үзүүлэх
 *   ✔ Контакт хуудасны dynamic routing хийх
 *   ✔ Хэл солих route (`/language/{code}`)
 *   ✔ Хуудасны үзэлт (read_count) нэмэгдүүлэх
 *   ✔ Web-level action-уудыг лог бүртгэлд (indolog) хадгалах
 *
 * Анхаарах зүйлс:
 *   - TemplateController-г өргөтгөж template() ашиглан public UI руу рендерлэнэ.
 *   - Developer өөрийн вэб сайт дээр home, page, news гэх мэт хуудасуудыг
 *     өөриймшүүлэн сайжруулж өргөтгөх боломжтой.
 *
 * @package Web\Home
 */
class HomeController extends TemplateController
{
    /**
     * ------------------------------------------------------------
     * 🏠  Нүүр хуудас (/)
     * ------------------------------------------------------------
     * Logic:
     *   1) Хэлний кодыг авах
     *   2) Сүүлийн мэдээнүүдээс 20-г татах (is_active=1 & published=1)
     *   3) home.html template-ийг рендерлэнэ
     *   4) Web layer-т зориулсан лог үлдээх
     */
    public function index()
    {
        $code = $this->getLanguageCode();
        // news хүснэгтийн нэрийг NewsModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt_recent = $this->prepare(
            "SELECT id, title, photo, published_at 
             FROM $news_table
             WHERE is_active=1 AND published=1 AND code=:code
             ORDER BY published_at DESC
             LIMIT 20"
        );
        $recent = $stmt_recent->execute([':code' => $code])
            ? $stmt_recent->fetchAll()
            : [];
        $vars = ['recent' => $recent];
        
        // Public layout template
        $home = $this->template(__DIR__ . '/home.html', $vars);
        $home->render();

        // Log: вебийн нүүр хуудас уншигдсан
        $this->indolog(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code}] Нүүр хуудсыг уншиж байна',
            ['action' => 'home']
        );
    }

    /**
     * ------------------------------------------------------------
     * 📞  Contact хуудас
     * ------------------------------------------------------------
     * PagesModel дотор хамгийн сүүлд нийтлэгдсэн төлөвтэй “/contact” гэсэн линктэй хуудасыг олж
     * page() функцээр үзүүлнэ.
     */
    public function contact()
    {
        // pages хүснэгтийн нэрийг PagesModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
        $pages_table = (new PagesModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT id 
             FROM $pages_table
             WHERE is_active=1 AND published=1 
               AND code=:code 
               AND link LIKE '%/contact'
             ORDER BY published_at DESC
             LIMIT 1"
        );
        $contact = $stmt->execute([':code' => $this->getLanguageCode()])
            ? $stmt->fetch()
            : [];
        return $this->page($contact['id'] ?? -1);
    }

    /**
     * ------------------------------------------------------------
     * 📄  Хуудас үзүүлэх (/page/{id})
     * ------------------------------------------------------------
     * Процесс:
     *   1) PagesModel → тухайн ID-тай хуудас татах
     *   2) Олдохгүй бол 404 Error
     *   3) FilesModel ашиглан хавсаргасан файлуудыг татах
     *   4) page.html template рүү дамжуулж рендерлэх
     *   5) read_count-ыг нэмэгдүүлэх
     *   6) Үйлдлийн лог үлдээх
     *
     * @param int $id
     * @return void
     * @throws Error
     */
    public function page(int $id)
    {
        $model = new PagesModel($this->pdo);
        // Хүснэгтийн нэрийг PagesModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
        $table = $model->getName();
        $record = $model->getRowWhere([
            'id' => $id,
            'is_active' => 1
        ]);
        if (empty($record)) {
            throw new \Error('Хуудас олдсонгүй', 404);
        }

        // Файлуудыг татах
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id AND is_active=1"
        ]);

        // Render page template
        $this->template(__DIR__ . '/page.html', $record)->render();

        // Read count нэмэгдүүлэх
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE $table SET read_count=$read_count WHERE id=$id");

        // Лог
        $this->indolog(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /page/{id}] {title} - хуудсыг уншиж байна',
            ['action' => 'page', 'id' => $id, 'title' => $record['title']]
        );
    }

    /**
     * ------------------------------------------------------------
     * 📰  Мэдээ үзүүлэх (/news/{id})
     * ------------------------------------------------------------
     * Процесс:
     *   1) NewsModel → тухайн ID-тай мэдээ татах
     *   2) Мэдээ байхгүй бол 404 Error
     *   3) NewsModel ашиглан хавсаргасан файлуудыг татах
     *   4) news.html template рүү дамжуулж рендерлэх
     *   5) read_count-ыг нэмэгдүүлэх
     *   6) Үйлдлийн лог үлдээх
     *
     * @param int $id
     * @return void
     * @throws Error
     */
    public function news(int $id)
    {
        $model = new NewsModel($this->pdo);
        // Хүснэгтийн нэрийг NewsModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
        $table = $model->getName();
        $record = $model->getRowWhere([
            'id' => $id,
            'is_active' => 1
        ]);
        if (empty($record)) {
            throw new \Error('Мэдээ олдсонгүй', 404);
        }

        // Файлууд
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id AND is_active=1"
        ]);

        // Render template
        $this->template(__DIR__ . '/news.html', $record)->render();

        // Read count
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE $table SET read_count=$read_count WHERE id=$id");

        // Лог
        $this->indolog(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /news/{id}] {title} - мэдээг уншиж байна',
            ['action' => 'news', 'id' => $id, 'title' => $record['title']]
        );
    }

    /**
     * ------------------------------------------------------------
     * 🌐  Хэл солих (/language/{code})
     * ------------------------------------------------------------
     * SESSION['WEB_LANGUAGE_CODE'] утгыг шинэчлээд нүүр рүү буцаана.
     *
     * @param string $code
     * @return void
     */
    public function language(string $code)
    {
        $from = $this->getLanguageCode();
        $language = $this->getLanguages();
        if (isset($language[$code]) && $code !== $from) {
            $_SESSION['WEB_LANGUAGE_CODE'] = $code;
        }

        $script_path = $this->getScriptPath();
        $home = (string)$this->getRequest()->getUri()->withPath($script_path);
        \header("Location: $home", false, 302);
        exit;
    }
}
