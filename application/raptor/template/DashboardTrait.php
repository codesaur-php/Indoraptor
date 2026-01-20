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
2. Хүснэгт байвал яг доорх загвараар:
   <table style="width:100%;border-collapse:collapse;margin-bottom:1rem;">
     <tr>
       <th style="border:1px solid #dee2e6;padding:0.5rem;">Гарчиг</th>
     </tr>
     <tr>
       <td style="border:1px solid #dee2e6;padding:0.5rem;">Утга</td>
     </tr>
   </table>
3. Жагсаалт байвал: <ul> эсвэл <ol>
4. Гарчиг байвал: <h1>-<h6> ашигла
5. Параграф: <p> tag ашигла
6. CSS class ХЭРЭГЛЭХГҮЙ (зөвхөн table, th, td дээр inline style зөвшөөрнө)
7. doctype, html, head, body, script TAG ХЭРЭГГҮЙ

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
            } else {
                // HTML mode: Bootstrap 5 гоёжуулалт
                $prompt = <<<PROMPT
Доорх HTML контентыг Bootstrap 5 компонентууд ашиглан илүү гоё, мэргэжлийн түвшинд харагдуулах болгож өгнө үү.

Заавар:
1. Контентын бүтэц, агуулгыг шинжилж, тохирох Bootstrap компонент болго:
   - Жагсаалт мэдээлэл → card эсвэл list-group
   - Харьцуулалт, олон багана мэдээлэл → table (table-striped table-hover table-bordered)
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
                $response = $this->callOpenAI($apiKey, $prompt);
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
     * OpenAI API дуудах хэлпер функц.
     *
     * @param string $apiKey OpenAI API түлхүүр
     * @param string $prompt Хүсэлтийн текст
     * @return string AI-ийн хариу
     * @throws \Exception API алдаа гарвал
     */
    private function callOpenAI(string $apiKey, string $prompt): string
    {
        $ch = \curl_init('https://api.openai.com/v1/chat/completions');

        $payload = [
            'model'       => 'gpt-4o-mini',
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'Чи HTML контентыг Bootstrap 5 ашиглан гоёжуулдаг туслах юм. Зөвхөн HTML код буцаа.'
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt
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
            \CURLOPT_TIMEOUT        => 60
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

}
