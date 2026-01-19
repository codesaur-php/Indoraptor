<?php

namespace Raptor\Template;

use codesaur\Template\TwigTemplate;

use Raptor\User\UsersModel;

/**
 * DashboardTrait - Dashboard UI руу контент байрлуулах,
 * permission-гүй үед alert/modal үзүүлэх, хэрэглэгчийн
 * sidemenu-г динамикаар үүсгэх зэрэг нийтлэг dashboard
 * функциональ байдлыг Controller-д ашиглуулах зориулалттай trait.
 *
 * Үндсэн үүрэг:
 * ───────────────────────────────────────────────────────────────
 *  - twigDashboard()  
 *      → Dashboard layout (dashboard.html) дотор контент оруулах
 *
 *  - dashboardProhibited()  
 *      → Хэрэглэгч эрхгүй үед dashboard орчинд permission alert үзүүлэх
 *
 *  - modalProhibited()  
 *      → Modal орчинд permission alert үзүүлэх
 *
 *  - retrieveUsersDetail()  
 *      → Audit log, organization mapping зэрэг UI-д хэрэглэгчийн
 *         товч мэдээллийн жагсаалтыг авах
 *
 *  - getUserMenu()  
 *      → Permission, is_active, is_visible, organization alias
 *         зэрэг нөхцөлүүдээр хэрэглэгчийн sidemenu-г бүрдүүлэх
 *
 * Энэ trait нь Raptor\Controller-тэй цуг ажиллаж,
 * Indoraptor Dashboard UI-ийн суурь rendering pipeline-г бүрдүүлнэ.
 */
trait DashboardTrait
{
    /**
     * Dashboard Layout ашиглан контент рендерлэх гол метод.
     *
     * Энэхүү функц нь бүх Dashboard төрлийн хуудасны мастер layout юм.
     * Хэрэглэгчийн харагдах UI бүтэц (sidebar, user info, content area)
     * бүгдийг энд төвлөрүүлж, динамикаар бүрдүүлдэг.
     *
     * Процесс:
     * ───────────────────────────────────────────────────────────────
     * 1) `dashboard.html` мастер layout-ийг twigTemplate() ашиглан ачаална.
     *
     * 2) Хэрэглэгчийн зөвшөөрөлд (RBAC) тулгуурлан харагдах ёстой
     *    sidemenu-г getUserMenu() функцээр тооцож → `sidemenu` хувьсагчид онооно.
     *
     * 3) Контент хэсэгт харуулах тухайн хуудасны template-г
     *    twigTemplate($template, $vars) дуудаж → `content` болгон оруулна.
     *    (Жич: Контент template нь зөвхөн `<main>` хэсэг дотор байрлана.)
     *
     * 4) Системийн тохируулгууд (`settings` аттрибут) - тухайлбал:
     *    footer мэдээлэл, брэндингийн өгөгдөл, favicon, logo зэрэг
     *    layout түвшинд хэрэгтэй бүх өгөгдлийг `$dashboard->set()` ашиглан нэг нэгээр нь оруулна.
     *
     * Товчхондоо:
     * ───────────────────────────────────────────────────────────────
     *  ➤ Dashboard layout + Dynamic sidebar + Dynamic content + System settings 
     *  → нэг TwigTemplate объект болж буцна.
     *
     * @param string $template  Контент template-ийн файл зам
     * @param array  $vars      Контент template-д дамжуулах хувьсагчид
     *
     * @return TwigTemplate     Бүрэн бэлтгэгдсэн Dashboard-ийн view объект
     */
    public function twigDashboard(string $template, array $vars = []): TwigTemplate
    {
        $dashboard = $this->twigTemplate(__DIR__ . '/dashboard.html');
        $dashboard->set('sidemenu', $this->getUserMenu());
        $dashboard->set('content', $this->twigTemplate($template, $vars));        
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $dashboard->set($key, $value);
        }
        return $dashboard;
    }

    /**
     * Dashboard орчинд permission-гүй үед alert харуулах.
     *
     * - Хариу код тохируулна (403 гэх мэт)
     * - Dashboard layout дотор "no-permission" контент байрлуулна
     *
     * @param string|null    $alert Alert текст
     * @param int|string     $code  HTTP статус код
     *
     * @return TwigTemplate
     */
    public function dashboardProhibited(?string $alert = null, int|string $code = 0): TwigTemplate
    {
        $this->headerResponseCode($code);

        return $this->twigDashboard(
            __DIR__ . '/alert-no-permission.html',
            ['alert' => $alert ?? $this->text('system-no-permission')]
        );
    }

    /**
     * Modal орчинд permission-гүй үед харуулах template.
     *
     * - Dashboard layout ашиглахгүй
     * - Зөвхөн modal-д тохирох жижиг template буцаана
     *
     * @param string|null $alert
     * @param int|string  $code
     *
     * @return TwigTemplate
     */
    public function modalProhibited(?string $alert = null, int|string $code = 0): TwigTemplate
    {
        $this->headerResponseCode($code);

        return new TwigTemplate(
            __DIR__ . '/modal-no-permission.html',
            [
                'alert' => $alert ?? $this->text('system-no-permission'),
                'close' => $this->text('close')
            ]
        );
    }

    /**
     * Хэрэглэгчдийн товч мэдээллийн жагсаалт авах (audit trail-д ашиглагдана).
     *
     * Оролт:
     *   - Хэдэн ч user_id дамжуулж болно.
     *   - user_id = null эсвэл ямар ч ID дамжуулаагүй бол:
     *         → users хүснэгт дэх **бүх хэрэглэгчийн** мэдээллийг авна.
     *
     * Гаралт:
     *   [
     *      4 => "batka » Бат Эрдэнэ (bat@example.com)",
     *      9 => "saraa » Сараа Мөнх (saraa@example.com)",
     *   ]
     *
     * Алдаа гарвал хоосон массив буцаана.
     *
     * @param int|null ...$ids
     * @return array [user_id => label]
     */
    protected function retrieveUsersDetail(?int ...$ids)
    {
        $users = [];
        
        try {
            $had_condition = !empty($ids);
            $table = (new UsersModel($this->pdo))->getName();
            $select_users =
                "SELECT id, username, first_name, last_name, email FROM $table";
            // WHERE нөхцөл боловсруулах
            if ($had_condition) {
                $ids = \array_filter($ids, fn($v) => $v !== null);
                if (empty($ids)) {
                    throw new \InvalidArgumentException(__FUNCTION__ . ': invalid arguments!');
                }
                \array_walk($ids, fn(&$v) => $v = "id=$v");
                $select_users .= ' WHERE ' . \implode(' OR ', $ids);
            }

            $pdo_stmt = $this->prepare($select_users);
            if ($pdo_stmt->execute()) {
                while ($row = $pdo_stmt->fetch()) {
                    $users[$row['id']] =
                        "{$row['username']} » {$row['first_name']} {$row['last_name']} ({$row['email']})";
                }
            }
        } catch (\Throwable) {
            // Хүсвэл алдааг development үед логлох боломжтой
        }
        
        return $users;
    }

    /**
     * Хэрэглэгчийн sidemenu-г динамикаар үүсгэх.
     *
     * Filter-лэгдэх нөхцөлүүд:
     * ───────────────────────────────────────────────────────────────
     *  - p.is_active = 1  → идэвхтэй меню
     *  - p.is_visible = 1 → харагдах боломжтой
     *  - Organization alias тохирох эсэх:
     *        menu.alias != current_user.organization.alias → skip
     *  - Permission заасан бол:
     *        !isUserCan(menu.permission) → skip
     *  - Localization: title нь тухайн хэл дээр байх ёстой
     *
     * Menu бүтэц:
     * ───────────────────────────────────────────────────────────────
     *  [
     *      parent_menu_id => [
     *          'title' => '...',
     *          'submenu' => [
     *              ['title' => '...', 'link' => '...', ...],
     *              ...
     *          ]
     *      ],
     *      ...
     *  ]
     *
     * Эцэст нь submenu хоосон parent-уудыг устгана.
     *
     * @return array Sidemenu-н бүтэц
     */
    public function getUserMenu(): array
    {
        $sidemenu = [];

        try {
            $alias = $this->getUser()->organization['alias'];
            
            $model = new MenuModel($this->pdo);
            $rows = $model->getRowsByCode(
                $this->getLanguageCode(),
                [
                    'ORDER BY' => 'p.position',
                    'WHERE'    => 'p.is_active=1 AND p.is_visible=1'
                ]
            );
            foreach ($rows as $row) {
                // Localization title авах
                $title = $row['localized']['title'] ?? null;

                // Organization alias filter
                if (!empty($row['alias']) && $alias !== $row['alias']) {
                    continue;
                }

                // Permission filter
                if (!empty($row['permission'])
                    && !$this->isUserCan($row['permission'])) {
                    continue;
                }

                // Parent menu
                if ($row['parent_id'] == 0) {
                    if (!isset($sidemenu[$row['id']])) {
                        $sidemenu[$row['id']] = ['title' => $title, 'submenu' => []];
                    } else {
                        $sidemenu[$row['id']]['title'] = $title;
                    }
                }
                // Child menu
                else {
                    unset($row['localized']);
                    $row['title'] = $title;

                    if (!isset($sidemenu[$row['parent_id']])) {
                        $sidemenu[$row['parent_id']] =
                            ['title' => '', 'submenu' => [$row]];
                    } else {
                        $sidemenu[$row['parent_id']]['submenu'][] = $row;
                    }
                }
            }

            // submenu хоосон parent-уудыг устгах
            foreach ($sidemenu as $key => $rows) {
                if (empty($rows['submenu'])) {
                    unset($sidemenu[$key]);
                }
            }
        } catch (\Throwable $e) {
            $this->errorLog($e);
        }

        return $sidemenu;
    }

    /**
     * moedit "shine" товчны API endpoint.
     *
     * HTML контентыг OpenAI API ашиглан Bootstrap 5 компонентуудтай
     * (table, card, accordion, alert, badge гэх мэт) гоё болгон хувиргана.
     *
     * Хүсэлт:
     * ───────────────────────────────────────────────────────────────
     *   POST /dashboard/shine
     *   Content-Type: application/json
     *   Body: { "html": "<p>Контент...</p>" }
     *
     * Хариу:
     * ───────────────────────────────────────────────────────────────
     *   Амжилттай: { "status": "success", "html": "<div class='card'>..." }
     *   Алдаа:     { "status": "error", "message": "..." }
     *
     * Тохиргоо (.env файлд):
     * ───────────────────────────────────────────────────────────────
     *   INDO_OPENAI_API_KEY=sk-...
     *
     * @return void
     */
    public function AIShine(): void
    {
        try {
            // Хэрэглэгч нэвтэрсэн байх ёстой
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            // API key шалгах ($_ENV эсвэл getenv)
            $apiKey = $_ENV['INDO_OPENAI_API_KEY'] ?? \getenv('INDO_OPENAI_API_KEY');
            if (empty($apiKey)) {
                throw new \Exception(
                    'OpenAI API key тохируулаагүй байна. ' .
                    '.env файлд INDO_OPENAI_API_KEY нэмнэ үү.',
                    500
                );
            }

            // JSON body унших (php://input ашиглах - stream нэг л удаа уншигддаг тул)
            $rawBody = \file_get_contents('php://input');
            $body = \json_decode($rawBody, true);
            $html = $body['html'] ?? '';
            $mode = $body['mode'] ?? 'html'; // 'html', 'vision', 'clean'

            // Vision mode-д html шалгахгүй (images массив ашиглана)
            if ($mode !== 'vision' && empty(\trim($html))) {
                throw new \InvalidArgumentException(
                    'Контент хоосон байна.',
                    400
                );
            }

            // Mode-оос хамааран prompt болон API дуудлага ялгаатай
            if ($mode === 'vision') {
                // OCR mode: Base64 зургуудыг хүлээн авах
                $base64Images = $body['images'] ?? [];

                if (empty($base64Images)) {
                    throw new \InvalidArgumentException(
                        'Зураг олдсонгүй. OCR ашиглахын тулд зураг оруулна уу.',
                        400
                    );
                }

                $prompt = <<<PROMPT
Чиний даалгавар: Хавсаргасан ЗУРАГ дээрх текстийг уншаад HTML болгох.

Заавар:
1. Зөвхөн ЗУРАГ дээрх текстийг унш, энэ prompt-ийн текстийг БУС
2. Хүснэгт байвал: <table class="table table-striped table-hover table-bordered">
3. Жагсаалт байвал: <ul> эсвэл <ol>
4. Гарчиг байвал: <h1>-<h6> ашигла
5. Зөвхөн контент HTML буцаа (doctype, html, head, body, script TAG ХЭРЭГГҮЙ)

АНХААРУУЛГА: Дээрх заавар бол ЧИ ДАГАХ ЗҮЙЛ, контент биш! Зөвхөн ЗУРАГ дээрх текстийг HTML болго.
PROMPT;

                // Зураг тус бүрийг тусад нь боловсруулж, үр дүнг нэгтгэх
                $results = [];
                foreach ($base64Images as $base64Image) {
                    $singleImageResult = $this->callOpenAIVision($apiKey, $prompt, $base64Image);
                    if (!empty(\trim($singleImageResult))) {
                        $results[] = $singleImageResult;
                    }
                }

                $response = \implode("\n\n", $results);
            } elseif ($mode === 'clean') {
                // Clean mode: Vanilla HTML (framework-гүй, хаана ч ажиллах)
                $prompt = <<<PROMPT
Доорх HTML-г цэвэр, семантик HTML болго. Framework class ХЭРЭГЛЭХГҮЙ.

Заавар:
1. Bootstrap, Tailwind гэх мэт framework class-уудыг БҮГДИЙГ УСТГА
2. Зөвхөн inline style ашигла (style="...")
3. Хүснэгтэд: style="width:100%; border-collapse:collapse;" болон cell-д border, padding нэм
4. Зургийг: style="max-width:100%; height:auto;"
5. Текст, агуулгыг ӨӨРЧЛӨХГҮЙ
6. Семантик HTML tag ашигла (article, section, header, footer, nav гэх мэт)
7. doctype, html, head, body, script TAG НЭМЭХГҮЙ
8. Зөвхөн контент HTML буцаа

---КОНТЕНТ ЭХЛЭЛ---
$html
---КОНТЕНТ ТӨГСГӨЛ---

Дээрх КОНТЕНТ хэсгийг цэвэр vanilla HTML болгож буцаа.
PROMPT;
                $response = $this->callOpenAI($apiKey, $prompt, $html, false);
            } else {
                // HTML mode: Bootstrap 5 гоёжуулалт
                $prompt = <<<PROMPT
Доорх HTML контентыг Bootstrap 5 компонентууд ашиглан илүү гоё, мэргэжлийн түвшинд харагдуулах болгож өгнө үү.

Заавар:
1. Контентын бүтэц, агуулгыг шинжилж, тохирох Bootstrap компонент болго:
   - Жагсаалт мэдээлэл → card эсвэл list-group
   - Харьцуулалт, олон багана мэдээлэл → table (table-striped table-hover table-bordered, div.table-responsive-т ор)
   - Асуулт-хариулт, FAQ → accordion
   - Алхам алхмаар заавар → list-group эсвэл card
   - Онцлох мэдээлэл → alert эсвэл callout
   - Холбоотой зүйлсийн жагсаалт → row/col grid
2. img → class="img-fluid rounded"
3. Текст агуулгыг ӨӨРЧЛӨХГҮЙ, зөвхөн HTML бүтцийг сайжруул
4. doctype, html, head, body, script TAG НЭМЭХГҮЙ
5. Хэт их биш, зөвхөн тохирох хэсэгт компонент ашигла
6. Хэрэв контент энгийн текст бол class нэмэхээс өөр юм хийхгүй

---КОНТЕНТ ЭХЛЭЛ---
$html
---КОНТЕНТ ТӨГСГӨЛ---

Дээрх КОНТЕНТ хэсгийг Bootstrap 5-аар гоёжуулж буцаа. Зөвхөн HTML буцаа, тайлбар бичихгүй.
PROMPT;
                $response = $this->callOpenAI($apiKey, $prompt, $html, false);
            }
            $this->respondJSON([
                'status' => 'success',
                'html'   => $response
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * OpenAI API дуудах хэлпер функц (Vision дэмжлэгтэй).
     *
     * @param string $apiKey OpenAI API түлхүүр
     * @param string $prompt Хүсэлтийн текст
     * @param string $html Анхны HTML (зураг илрүүлэхэд)
     * @param bool $useVision Vision mode идэвхжүүлэх эсэх
     * @return string AI-ийн хариу
     * @throws \Exception API алдаа гарвал
     */
    private function callOpenAI(string $apiKey, string $prompt, string $html = '', bool $useVision = false): string
    {
        // Vision mode байвал зургийн URL-үүд олох
        $imageUrls = $useVision ? $this->extractImageUrls($html) : [];

        // Vision mode бөгөөд зураг олдвол gpt-4o, үгүй бол gpt-4o-mini
        $model = ($useVision && !empty($imageUrls)) ? 'gpt-4o' : 'gpt-4o-mini';

        // User message бэлдэх
        if ($useVision && !empty($imageUrls)) {
            // Vision API format: text + images
            $userContent = [
                ['type' => 'text', 'text' => $prompt]
            ];

            foreach ($imageUrls as $url) {
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $url, 'detail' => 'high']
                ];
            }
        } else {
            $userContent = $prompt;
        }

        $ch = \curl_init('https://api.openai.com/v1/chat/completions');

        $payload = [
            'model'       => $model,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Чи HTML контентыг Bootstrap 5 ашиглан гоёжуулдаг туслах юм. Зөвхөн HTML код буцаа.'
                ],
                [
                    'role'    => 'user',
                    'content' => $userContent
                ]
            ],
            'temperature' => 0.3,
            'max_tokens'  => 4096
        ];

        \curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST           => true,
            \CURLOPT_POSTFIELDS     => \json_encode($payload),
            \CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            \CURLOPT_TIMEOUT        => 120 // Vision илүү удаан байж болно
        ]);

        $result = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error = \curl_error($ch);
        \curl_close($ch);

        if ($error) {
            throw new \Exception('OpenAI холболтын алдаа: ' . $error, 500);
        }

        $data = \json_decode($result, true);

        if ($httpCode !== 200) {
            $errorMsg = $data['error']['message'] ?? 'Unknown error';
            throw new \Exception('OpenAI API алдаа: ' . $errorMsg, $httpCode);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';

        // Markdown code block байвал арилгах
        $content = \preg_replace('/^```html?\s*/i', '', $content);
        $content = \preg_replace('/\s*```$/', '', $content);

        return \trim($content);
    }

    /**
     * HTML-ээс зургийн URL-үүд задлах.
     *
     * @param string $html HTML контент
     * @return array Зургийн URL-үүдийн массив
     */
    private function extractImageUrls(string $html): array
    {
        $urls = [];

        if (empty($html)) {
            return $urls;
        }

        // img tag-ийн src attribute олох
        \preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                // Зөвхөн http/https URL авах (data: эсвэл relative path биш)
                if (\preg_match('/^https?:\/\//i', $url)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * OpenAI Vision API-г ганц зурагтай дуудах.
     *
     * @param string $apiKey OpenAI API түлхүүр
     * @param string $prompt Хүсэлтийн текст
     * @param string $imageUrl Зургийн URL
     * @return string AI-ийн хариу
     * @throws \Exception API алдаа гарвал
     */
    private function callOpenAIVision(string $apiKey, string $prompt, string $imageUrl): string
    {
        $ch = \curl_init('https://api.openai.com/v1/chat/completions');

        $payload = [
            'model'       => 'gpt-4o',
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Чи зураг дээрх текстийг уншиж HTML болгодог туслах юм. Зөвхөн HTML код буцаа.'
                ],
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => $imageUrl, 'detail' => 'high']
                        ]
                    ]
                ]
            ],
            'temperature' => 0.3,
            'max_tokens'  => 4096
        ];

        \curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST           => true,
            \CURLOPT_POSTFIELDS     => \json_encode($payload),
            \CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            \CURLOPT_TIMEOUT        => 120
        ]);

        $result = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $error = \curl_error($ch);
        \curl_close($ch);

        if ($error) {
            throw new \Exception('OpenAI холболтын алдаа: ' . $error, 500);
        }

        $data = \json_decode($result, true);

        if ($httpCode !== 200) {
            $errorMsg = $data['error']['message'] ?? 'Unknown error';
            throw new \Exception('OpenAI API алдаа: ' . $errorMsg, $httpCode);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';

        // Markdown code block байвал арилгах
        $content = \preg_replace('/^```html?\s*/i', '', $content);
        $content = \preg_replace('/\s*```$/', '', $content);

        return \trim($content);
    }

    /**
     * PDF файлыг HTML болгож буцаах API endpoint.
     *
     * smalot/pdfparser сан ашиглан PDF-ийн текстийг задлаж,
     * HTML формат руу хөрвүүлнэ.
     *
     * Хэрэглээ:
     * ───────────────────────────────────────────────────────────────
     *   POST /dashboard/moedit/pdf-parse
     *   Content-Type: multipart/form-data
     *   Body: file (PDF файл)
     *
     * Хариу:
     * ───────────────────────────────────────────────────────────────
     *   { "status": "success", "html": "<p>...</p>" }
     *
     * Суулгах:
     * ───────────────────────────────────────────────────────────────
     *   composer require smalot/pdfparser
     *
     * @return void
     */
    public function PDFParse(): void
    {
        try {
            // Хэрэглэгч нэвтэрсэн байх ёстой
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            // smalot/pdfparser сан байгаа эсэх
            if (!\class_exists(\Smalot\PdfParser\Parser::class)) {
                throw new \Exception(
                    'PDF Parser сан суулгаагүй байна. ' .
                    'Терминалд: composer require smalot/pdfparser',
                    500
                );
            }

            // Файл upload шалгах
            $uploadedFiles = $this->getRequest()->getUploadedFiles();
            $pdfFile = $uploadedFiles['file'] ?? null;

            if (!$pdfFile || $pdfFile->getError() !== \UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('PDF файл олдсонгүй', 400);
            }

            // MIME type шалгах
            $mimeType = $pdfFile->getClientMediaType();
            if ($mimeType !== 'application/pdf') {
                throw new \InvalidArgumentException(
                    'Зөвхөн PDF файл зөвшөөрөгдөнө. Таны файл: ' . $mimeType,
                    400
                );
            }

            // Түр файл үүсгэх
            $tempFile = \sys_get_temp_dir() . '/' . \uniqid('pdf_') . '.pdf';
            $pdfFile->moveTo($tempFile);

            try {
                // PDF parse хийх - Кирилл үсэгт тохируулсан тохиргоо
                $config = new \Smalot\PdfParser\Config();
                $config->setRetainImageContent(false);
                $config->setDecodeMemoryLimit(0); // Санах ойн хязгааргүй

                $parser = new \Smalot\PdfParser\Parser([], $config);
                $pdf = $parser->parseFile($tempFile);

                $htmlParts = [];
                $pages = $pdf->getPages();
                $pageCount = \count($pages);
                $hasReadableText = false;

                foreach ($pages as $pageNum => $page) {
                    $text = $page->getText();

                    if (!empty(\trim($text))) {
                        // Encoding засах оролдлого
                        $text = $this->fixPdfEncoding($text);

                        // Текст уншигдаж байгаа эсэхийг шалгах
                        if ($this->isReadableText($text)) {
                            $hasReadableText = true;
                        }

                        // Текстийг HTML болгох
                        $pageHtml = $this->textToHtml($text);

                        // Хэрэв олон хуудастай бол хуудас тусгаарлагч нэмэх
                        if ($pageCount > 1) {
                            $htmlParts[] = "<!-- Хуудас " . ($pageNum + 1) . " -->\n" . $pageHtml;
                        } else {
                            $htmlParts[] = $pageHtml;
                        }
                    }
                }

                $html = \implode("\n\n<hr class=\"my-4\">\n\n", $htmlParts);

                // Текст олдсонгүй эсвэл уншигдахгүй байвал
                if (empty(\trim($html))) {
                    throw new \Exception(
                        'PDF файлаас текст олдсонгүй. ' .
                        'Зураг PDF эсвэл сканнердсан баримт бол AI OCR ашиглана уу.',
                        400
                    );
                }

                // Текст олдсон боловч уншигдахгүй байвал анхааруулга нэмэх
                if (!$hasReadableText) {
                    $html = '<div class="alert alert-warning mb-3">' .
                        '<i class="bi bi-exclamation-triangle"></i> ' .
                        'PDF-ийн текст encoding асуудалтай байж магадгүй. ' .
                        'Хэрэв текст буруу харагдаж байвал AI OCR ашиглана уу.' .
                        '</div>' . $html;
                }

                $this->respondJSON([
                    'status' => 'success',
                    'html'   => $html,
                    'pages'  => $pageCount
                ]);
            } finally {
                // Түр файл устгах
                if (\file_exists($tempFile)) {
                    \unlink($tempFile);
                }
            }
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Энгийн текстийг HTML формат руу хөрвүүлэх.
     *
     * @param string $text Текст
     * @return string HTML
     */
    private function textToHtml(string $text): string
    {
        // Windows мөр төгсгөлийг нэгтгэх
        $text = \str_replace("\r\n", "\n", $text);
        $text = \str_replace("\r", "\n", $text);

        // Хоосон мөрүүдээр параграф болгох
        $paragraphs = \preg_split('/\n{2,}/', $text);

        $html = '';
        foreach ($paragraphs as $para) {
            $para = \trim($para);
            if (empty($para)) {
                continue;
            }

            // Нэг мөрөн дотор newline-уудыг <br> болгох
            $para = \nl2br(\htmlspecialchars($para, \ENT_QUOTES, 'UTF-8'));

            // Хүснэгт мэт харагдаж байвал (tab-аар тусгаарлагдсан)
            if (\preg_match('/\t/', $para)) {
                $html .= $this->tabsToTable($para);
            } else {
                $html .= "<p>$para</p>\n";
            }
        }

        return $html;
    }

    /**
     * Tab-аар тусгаарлагдсан текстийг хүснэгт болгох.
     *
     * @param string $text Tab-тай текст
     * @return string HTML хүснэгт
     */
    private function tabsToTable(string $text): string
    {
        // <br> тэмдэгтийг буцааж newline болгох
        $text = \str_replace(['<br />', '<br>'], "\n", $text);
        $text = \html_entity_decode($text, \ENT_QUOTES, 'UTF-8');

        $lines = \explode("\n", $text);
        $rows = [];

        foreach ($lines as $line) {
            $line = \trim($line);
            if (empty($line)) {
                continue;
            }

            $cells = \preg_split('/\t+/', $line);
            $rows[] = $cells;
        }

        if (empty($rows)) {
            return '';
        }

        $html = "<div class=\"table-responsive\">\n";
        $html .= "<table class=\"table table-striped table-hover table-bordered\">\n";

        // Эхний мөрийг header гэж үзэх (2+ мөр байвал)
        $isFirst = true;
        foreach ($rows as $row) {
            if ($isFirst && \count($rows) > 1) {
                $html .= "<thead><tr>\n";
                foreach ($row as $cell) {
                    $cell = \htmlspecialchars(\trim($cell), \ENT_QUOTES, 'UTF-8');
                    $html .= "<th>$cell</th>\n";
                }
                $html .= "</tr></thead>\n<tbody>\n";
                $isFirst = false;
            } else {
                $html .= "<tr>\n";
                foreach ($row as $cell) {
                    $cell = \htmlspecialchars(\trim($cell), \ENT_QUOTES, 'UTF-8');
                    $html .= "<td>$cell</td>\n";
                }
                $html .= "</tr>\n";
            }
        }

        if (\count($rows) > 1) {
            $html .= "</tbody>\n";
        }

        $html .= "</table>\n</div>\n";

        return $html;
    }

    /**
     * PDF-ээс задалсан текстийн encoding-ийг засах.
     *
     * Кирилл болон бусад Unicode текстүүдийг зөв харуулахын тулд
     * encoding илрүүлж хөрвүүлэх оролдлого хийнэ.
     *
     * @param string $text PDF-ээс задалсан текст
     * @return string Encoding засагдсан текст
     */
    private function fixPdfEncoding(string $text): string
    {
        // Аль хэдийн UTF-8 бол буцаах
        if (\mb_check_encoding($text, 'UTF-8') && $this->isReadableText($text)) {
            return $text;
        }

        // Түгээмэл encoding-үүдийг туршиж үзэх
        $encodings = [
            'UTF-8',
            'Windows-1251',  // Кирилл
            'KOI8-R',        // Орос Кирилл
            'ISO-8859-5',    // Кирилл
            'CP866',         // DOS Кирилл
            'UTF-16BE',
            'UTF-16LE',
            'Windows-1252',  // Latin
            'ISO-8859-1',
        ];

        foreach ($encodings as $encoding) {
            if ($encoding === 'UTF-8') {
                continue;
            }

            $converted = @\mb_convert_encoding($text, 'UTF-8', $encoding);
            if ($converted !== false && $this->isReadableText($converted)) {
                return $converted;
            }
        }

        // Detected encoding ашиглах оролдлого
        $detected = \mb_detect_encoding($text, $encodings, true);
        if ($detected && $detected !== 'UTF-8') {
            $converted = \mb_convert_encoding($text, 'UTF-8', $detected);
            if ($converted !== false) {
                return $converted;
            }
        }

        // Хэрэв юу ч болохгүй бол хуучин текстээ буцаах
        return $text;
    }

    /**
     * Текст уншигдаж болох эсэхийг шалгах.
     *
     * Кирилл, Латин, тоо, түгээмэл тэмдэгтүүд байгаа эсэхийг шалгана.
     * Хэрэв ихэнх тэмдэгт нь уншигдахгүй бол false буцаана.
     *
     * @param string $text Шалгах текст
     * @return bool Уншигдаж болох эсэх
     */
    private function isReadableText(string $text): bool
    {
        if (empty(\trim($text))) {
            return false;
        }

        // Уншигдах тэмдэгтүүдийн тоог тоолох
        // Латин, Кирилл, тоо, хоосон зай, түгээмэл цэг тэмдэгтүүд
        $readablePattern = '/[\p{L}\p{N}\s\.\,\!\?\;\:\-\(\)\[\]\{\}\"\'\/\\\@\#\$\%\^\&\*\+\=\_\~\`]/u';

        \preg_match_all($readablePattern, $text, $matches);
        $readableCount = isset($matches[0]) ? \count($matches[0]) : 0;
        $totalLength = \mb_strlen($text, 'UTF-8');

        if ($totalLength === 0) {
            return false;
        }

        // 50%-аас дээш уншигдах тэмдэгт байвал OK
        $readableRatio = $readableCount / $totalLength;

        return $readableRatio > 0.5;
    }
}
