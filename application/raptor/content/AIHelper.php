<?php

namespace Raptor\Content;

use codesaur\Http\Client\JSONClient;

/**
 * AI туслах класс - OpenAI API ашиглан контент боловсруулах.
 *
 * moedit WYSIWYG editor-ийн AI функцуудыг (Shine, OCR) дэмжих backend endpoint-уудыг агуулна.
 * OpenAI GPT-4o болон GPT-4o-mini моделиудыг ашиглана.
 *
 * Тохиргоо (.env файлд):
 * ─────────────────────────────────────────────────────────────────────────────
 *   INDO_OPENAI_API_KEY=sk-proj-...    # OpenAI API түлхүүр (заавал)
 *
 * @package    Raptor\Content
 * @author     Narankhuu
 * @see        https://platform.openai.com/docs/api-reference OpenAI API Documentation
 */
class AIHelper extends \Raptor\Controller
{
    /**
     * moedit AI endpoint - HTML контент сайжруулах эсвэл зургаас текст таних (OCR).
     *
     * Энэ endpoint нь 2 горимоор ажиллана:
     *   1. HTML mode (mode='html') - Контентыг Bootstrap 5 компонентуудаар сайжруулах
     *   2. Vision mode (mode='vision') - Зураг дээрх текстийг таниж HTML болгох (OCR)
     *
     * Хүсэлт (HTML mode):
     * ─────────────────────────────────────────────────────────────────────────────
     *   POST /dashboard/moedit/ai
     *   Content-Type: application/json
     *   Body: {
     *     "mode": "html",
     *     "html": "<p>Контент...</p>",
     *     "prompt": "Bootstrap card болгон хувирга"
     *   }
     *
     * Хүсэлт (Vision/OCR mode):
     * ─────────────────────────────────────────────────────────────────────────────
     *   POST /dashboard/moedit/ai
     *   Content-Type: application/json
     *   Body: {
     *     "mode": "vision",
     *     "images": ["data:image/png;base64,...", "data:image/jpeg;base64,..."],
     *     "prompt": "Зураг дээрх текстийг HTML хүснэгт болго"
     *   }
     *
     * Хариу:
     * ─────────────────────────────────────────────────────────────────────────────
     *   Амжилттай: { "status": "success", "html": "<div class='card'>..." }
     *   Алдаа:     { "status": "error", "message": "Алдааны тайлбар" }
     *
     * HTTP статус кодууд:
     *   200 - Амжилттай
     *   400 - Буруу хүсэлт (хоосон контент, prompt гэх мэт)
     *   401 - Нэвтрээгүй хэрэглэгч
     *   500 - Серверийн алдаа (API key байхгүй, OpenAI алдаа гэх мэт)
     *
     * @return void JSON хариу буцаана
     *
     * @throws \Exception Нэвтрээгүй бол (401)
     * @throws \Exception API key тохируулаагүй бол (500)
     * @throws \InvalidArgumentException Контент эсвэл prompt хоосон бол (400)
     * @throws \InvalidArgumentException Vision mode-д зураг байхгүй бол (400)
     */
    public function moeditAI(): void
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

            // PSR-7 request body унших
            $body = $this->getParsedBody();
            $html = $body['html'] ?? '';
            $mode = $body['mode'] ?? 'html'; // 'html', 'vision', 'clean'
            $customPrompt = $body['prompt'] ?? ''; // Frontend-ээс ирсэн custom prompt

            // Vision mode-д html шалгахгүй (images массив ашиглана)
            if ($mode !== 'vision' && empty(\trim($html))) {
                throw new \InvalidArgumentException(
                    'Контент хоосон байна.',
                    400
                );
            }

            // Заавал нэмэгдэх систем заавар (frontend-д харагдахгүй)
            $systemInstruction = "ЧУХАЛ ЗААВАР: doctype, html, head, body, script, style TAG НЭМЭХГҮЙ. Зөвхөн HTML буцаа, comment бичихгүй.\n\n";

            // Frontend-ээс prompt ирээгүй бол алдаа
            if (empty(\trim($customPrompt))) {
                throw new \InvalidArgumentException('Prompt хоосон байна.', 400);
            }

            // Mode-оос хамааран API дуудлага ялгаатай
            if ($mode === 'vision') {
                // OCR mode: Base64 зургуудыг хүлээн авах
                $base64Images = $body['images'] ?? [];

                if (empty($base64Images)) {
                    throw new \InvalidArgumentException(
                        'Зураг олдсонгүй. OCR ашиглахын тулд зураг оруулна уу.',
                        400
                    );
                }

                // Frontend prompt + system instruction
                $prompt = $systemInstruction . $customPrompt;

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
                // HTML mode: Frontend prompt + system instruction + контент
                $prompt = $systemInstruction . $customPrompt . "\n\n---КОНТЕНТ ЭХЛЭЛ---\n{$html}\n---КОНТЕНТ ТӨГСГӨЛ---";
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
     * OpenAI Chat Completions API дуудах (текст боловсруулалт).
     *
     * GPT-4o-mini моделийг ашиглан HTML контентыг Bootstrap 5 компонентуудаар
     * сайжруулах хүсэлт илгээнэ. Markdown code block хариуг автоматаар цэвэрлэнэ.
     *
     * API тохиргоо:
     *   - Model: gpt-4o-mini (хурдан, хямд)
     *   - Temperature: 0.3 (тогтвортой үр дүн)
     *   - Max tokens: 4096
     *   - HTTP/1.1 протокол (HTTP/2 алдаанаас зайлсхийх)
     *
     * @param string $apiKey OpenAI API түлхүүр (sk-proj-... эсвэл sk-...)
     * @param string $prompt Системийн заавар болон хэрэглэгчийн контент агуулсан prompt
     *
     * @return string Цэвэрлэгдсэн HTML хариу (```html wrapper-гүй)
     *
     * @throws \Exception OpenAI API алдаа буцаавал (rate limit, invalid key гэх мэт)
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     */
    private function callOpenAI(string $apiKey, string $prompt): string
    {
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
        $data = (new JSONClient())->post(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            ['Authorization' => 'Bearer ' . $apiKey],
            [\CURLOPT_HTTP_VERSION => \CURL_HTTP_VERSION_1_1]
        );
        if (isset($data['error'])) {
            throw new \Exception('OpenAI API алдаа: ' . ($data['error']['message'] ?? 'Unknown error'), $data['error']['code'] ?? 500);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        // Markdown code block байвал арилгах
        $content = \preg_replace('/^```html?\s*/i', '', $content);
        $content = \preg_replace('/\s*```$/', '', $content);
        return \trim($content);
    }

    /**
     * OpenAI Vision API дуудах (зураг таних / OCR).
     *
     * GPT-4o моделийн vision чадварыг ашиглан зураг дээрх текстийг таниж
     * HTML болгон хувиргана. Нэг удаад нэг зураг боловсруулна.
     *
     * API тохиргоо:
     *   - Model: gpt-4o (vision чадвартай)
     *   - Temperature: 0.3 (тогтвортой үр дүн)
     *   - Max tokens: 4096
     *   - Image detail: high (өндөр нарийвчлал)
     *   - HTTP/1.1 протокол (HTTP/2 алдаанаас зайлсхийх)
     *
     * Дэмжигдэх зургийн формат:
     *   - Base64 data URL: data:image/png;base64,... эсвэл data:image/jpeg;base64,...
     *   - HTTPS URL: https://example.com/image.png
     *
     * @param string $apiKey   OpenAI API түлхүүр (sk-proj-... эсвэл sk-...)
     * @param string $prompt   Зургийг хэрхэн боловсруулах заавар
     * @param string $imageUrl Зургийн base64 data URL эсвэл HTTPS URL
     *
     * @return string Цэвэрлэгдсэн HTML хариу (```html wrapper-гүй)
     *
     * @throws \Exception OpenAI API алдаа буцаавал (rate limit, invalid key, image error гэх мэт)
     *
     * @see https://platform.openai.com/docs/guides/vision
     */
    private function callOpenAIVision(string $apiKey, string $prompt, string $imageUrl): string
    {
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
        $data = (new JSONClient())->post(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            ['Authorization' => 'Bearer ' . $apiKey],
            [\CURLOPT_HTTP_VERSION => \CURL_HTTP_VERSION_1_1]
        );
        if (isset($data['error'])) {
            throw new \Exception('OpenAI API алдаа: ' . ($data['error']['message'] ?? 'Unknown error'), $data['error']['code'] ?? 500);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        // Markdown code block байвал арилгах
        $content = \preg_replace('/^```html?\s*/i', '', $content);
        $content = \preg_replace('/\s*```$/', '', $content);
        return \trim($content);
    }
}
