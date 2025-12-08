<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

/**
 * Class SettingsController
 *
 * Indoraptor CMS-ийн "Тохиргоо" (Settings) модулийн удирдлагын controller.
 * Энэ контроллер нь дараах үндсэн үүргийг гүйцэтгэнэ:
 *
 *  1) Тохиргоо харах UI-г render хийх (`index`)
 *  2) Text-based тохиргоог хадгалах (`post`)
 *  3) Файл upload (logo, favicon, apple-touch-icon) хийх (`files`)
 *
 * Controller нь FileController-оос өвлөсөн тул:
 *  - Файл хадгалах хавтас (`setFolder`)
 *  - Upload file move хийх (`moveUploaded`)
 *  - File extension фильтер хийх (`allowExtensions`, `allowImageOnly`)
 *  зэрэг функцуудыг ашиглана.
 *
 * Мөн хэрэглэгчийн dashboard UI-г render хийхийн тулд DashboardTrait ашигладаг.
 *
 * @package Raptor\Content
 */
class SettingsController extends FileController
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Тохиргооны нүүр хуудас (settings.html)–г харуулах.
     *
     * - Хэрэглэгч system_content_settings эрхтэй эсэхийг шалгана.
     * - SettingsModel → retrieve() ашиглан идэвхтэй тохиргоог авна.
     * - Хэрэв тохиргоонд зураг (logo, favicon, apple-touch-icon) байгаа бол
     *      физик файл нь public/settings хавтсанд байвал
     *      файлын хэмжээг bytes → KB/MB форматад хөрвүүлж record массивт inject хийнэ.
     *
     * - Twig dashboard template рүү дамжуулж render хийнэ.
     * - Нэвтрүүлэлтийн лог (indolog) үлдээнэ.
     */
    public function index()
    {
        if (!$this->isUserCan('system_content_settings')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        // Тохиргоотой холбоотой бүх файлууд public/settings хавтасд хадгалагддаг.
        $this->setFolder('/settings');

        $record = (new SettingsModel($this->pdo))->retrieve();

        /*
         * Хэрэв тохиргоо бичлэг байгаа бол файлуудын absolute path → size шалгана.
         * DB нь зөвхөн public URL (= relative path) хадгалдаг тул,
         * FileController доторх $this->local ашиглан физик path бүтээж шалгана.
         */
        if (\array_key_exists('id', $record)) {
            // FAVICO
            if (!empty($record['favico'])) {
                $favicoFile = $this->local . '/' . \basename($record['favico']);
                if (\file_exists($favicoFile)) {
                    $record['favico_size'] = $this->formatSizeUnits(\filesize($favicoFile));
                }
            }

            // APPLE TOUCH ICON
            if (!empty($record['apple_touch_icon'])) {
                $appleFile = $this->local . '/' . \basename($record['apple_touch_icon']);
                if (\file_exists($appleFile)) {
                    $record['apple_touch_icon_size'] = $this->formatSizeUnits(\filesize($appleFile));
                }
            }

            /* LOGO (Олон хэл дээр) */
            if (!empty($record['localized']['logo'] ?? [])) {
                foreach ($record['localized']['logo'] as $code => $path) {
                    if (!empty($path)) {
                        $logoPath = $this->local . '/' . \basename($path);
                        if (\file_exists($logoPath)) {
                            $record['localized']['logo_size'][$code] =
                                $this->formatSizeUnits(\filesize($logoPath));
                        }
                    }
                }
            }
        }

        /* Dashboard template рүү record дамжуулж render хийх */
        $dashboard = $this->twigDashboard(__DIR__ . '/settings.html', ['record' => $record]);
        $dashboard->set('title', $this->text('settings'));
        $dashboard->render();

        /* Нэвтрүүлэлтийн лог */
        $this->indolog('content', LogLevel::NOTICE, 'Тохируулгыг нээж байна', ['action' => 'settings-index']);
    }

    /**
     * POST request - Текстэн тохиргоо (title, email, description, etc.) хадгалах.
     *
     * - Хэрэглэгч system_content_settings эрхтэй эсэхийг шалгана.
     * - Request body-г уншиж payload болон content болгон хоёр хуваана:
     *        payload → үндсэн хүснэгт
     *        content → localized хүснэгт
     *
     * - Хэрвээ ямар ч өөрчлөлтгүй бол алдаа шиднэ.
     * - config талбар JSON эсэхийг шалгана.
     * - settings байгаа бол updateById(), байхгүй бол insert().
     *
     * - JSON response буцаана.
     * - Амжилттай/алдаатай бүх тохиолдолд системд лог үлдээнэ.
     */
    public function post()
    {
        try {
            if (!$this->isUserCan('system_content_settings')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new SettingsModel($this->pdo);
            $current = $model->retrieve();
            $parsedBody = $this->getParsedBody();

            $payload = [];
            $content = [];
            $updates = [];

            /*
             * Request body-г хувьсагч болгон payload / localized-г ялгана.
             */
            foreach ($parsedBody as $index => $value) {

                /* Localization талбарууд (array хэлбэртэй) */
                if (\is_array($value)) {
                    foreach ($value as $key => $v) {
                        $content[$key][$index] = $v;

                        if (($current['localized'][$index][$key] ?? '') != $v) {
                            $updates[] = "{$index}_{$key}";
                        }
                    }

                } else {
                    /* Үндсэн багана */
                    $payload[$index] = $value;

                    if (($current[$index] ?? '') != $value) {
                        $updates[] = $index;
                    }
                }
            }

            if (empty($updates)) {
                throw new \InvalidArgumentException('No update!');
            }

            /* config талбар valid JSON эсэхийг шалгах */
            if (!empty($payload['config'])
                && \json_decode($payload['config']) === null
            ) {
                throw new \InvalidArgumentException('Extra config must be valid JSON!', 400);
            }

            /* Update эсвэл Insert */
            if (isset($current['id'])) {

                if (empty($model->updateById(
                    $current['id'],
                    $payload + ['updated_by' => $this->getUserId()],
                    $content
                ))) {
                    throw new \Exception($this->text('no-record-selected'));
                }

                $notify = 'primary';
                $notice = $this->text('record-update-success');

            } else {

                if (!$model->insert(
                    $payload + ['created_by' => $this->getUserId()],
                    $content
                )) {
                    throw new \Exception($this->text('record-insert-error'));
                }

                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }

            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

        } catch (\Throwable $err) {

            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());

        } finally {

            /* Лог үлдээх */
            $context = ['action' => 'settings-post'];

            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Тохируулгыг шинэчлэх үед алдаа гарч зогслоо';
                $context['error'] = ['code' => $err->getCode(), 'message' => $err->getMessage()];
            } else {
                $level = LogLevel::INFO;
                $message = 'Тохируулгыг амжилттай шинэчиллээ';
            }

            $this->indolog('content', $level, $message, $context);
        }
    }

    /**
     * File Upload (Logo, Favico, Apple Touch Icon) хадгалах.
     *
     * - Хэрэглэгчийн эрхийг шалгана.
     * - public/settings хавтас руу upload хийнэ.
     * - Хэрэв өмнөх файл байвал unlinkByName() ашиглан устгана.
     * - localized logo файлуудыг loop-оор боловсруулна.
     *
     * Энэ арга нь зөвхөн FILE PATH-ыг DB-д хадгалдаг бөгөөд
     * файлын хэмжээ, мета мэдээллийг хадгалдаггүй.
     * (size-г index() дотор UI-д зориулан runtime-р тооцдог)
     */
    public function files()
    {
        try {
            if (!$this->isUserCan('system_content_settings')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $this->setFolder('/settings');
            $parsedBody = $this->getParsedBody();

            $model = new SettingsModel($this->pdo);
            $current = $model->retrieve();

            $updates = [];
            $payload = [];

            /* -------------------- FAVICO -------------------- */
            $favico_name = \basename($current['favico'] ?? '');
            $this->allowExtensions(['ico']);
            $ico = $this->moveUploaded('favico');

            if (!empty($favico_name) && $parsedBody['favico_removed'] == 1) {
                $this->unlinkByName($favico_name);
                $payload['favico'] = '';
                $favico_name = null;
                $updates[] = 'favico';
            }

            if ($ico) {
                if (!empty($favico_name)
                    && \basename($ico['path']) != $favico_name
                ) {
                    $this->unlinkByName($favico_name);
                }
                $payload['favico'] = $ico['path'];
                $updates[] = 'favico';
            }

            /* -------------------- APPLE TOUCH ICON -------------------- */
            $this->allowImageOnly();
            $apple_touch_icon_name = \basename($current['apple_touch_icon'] ?? '');
            $apple_touch_icon = $this->moveUploaded('apple_touch_icon');

            if (!empty($apple_touch_icon_name)
                && $parsedBody['apple_touch_icon_removed'] == 1
            ) {
                $this->unlinkByName($apple_touch_icon_name);
                $payload['apple_touch_icon'] = '';
                $apple_touch_icon_name = null;
                $updates[] = 'apple_touch_icon';
            }

            if ($apple_touch_icon) {
                if (!empty($apple_touch_icon_name)
                    && \basename($apple_touch_icon['path']) != $apple_touch_icon_name
                ) {
                    $this->unlinkByName($apple_touch_icon_name);
                }
                $payload['apple_touch_icon'] = $apple_touch_icon['path'];
                $updates[] = 'apple_touch_icon';
            }

            /* -------------------- LOGO (олон хэл) -------------------- */
            $content = [];
            $uploadedLogos = $this->getRequest()->getUploadedFiles()['logo'] ?? [];

            foreach (\array_keys($uploadedLogos) as $code) {

                $logo_name = \basename($current['localized']['logo'][$code] ?? '');
                $logo = $this->moveUploaded($uploadedLogos[$code]);

                if (!empty($logo_name)
                    && $parsedBody["logo_{$code}_removed"] == 1
                ) {
                    $this->unlinkByName($logo_name);
                    $content[$code]['logo'] = '';
                    $logo_name = null;
                    $updates[] = "logo_{$code}_removed";
                }

                if ($logo) {
                    if (!empty($logo_name)
                        && \basename($logo['path']) != $logo_name
                    ) {
                        $this->unlinkByName($logo_name);
                    }
                    $content[$code]['logo'] = $logo['path'];
                    $updates[] = "logo_{$code}_removed";
                }
            }

            if (empty(\array_unique($updates))) {
                throw new \InvalidArgumentException('No update!');
            }

            /* update эсвэл insert */
            if (isset($current['id'])) {

                if (empty($model->updateById(
                    $current['id'],
                    $payload + ['updated_by' => $this->getUserId()],
                    $content
                ))) {
                    throw new \Exception($this->text('no-record-selected'));
                }

                $notify = 'primary';
                $notice = $this->text('record-update-success');

            } else {

                if (!$model->insert(
                    $payload + ['created_by' => $this->getUserId()],
                    $content
                )) {
                    throw new \Exception($this->text('record-insert-error'));
                }

                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }

            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

        } catch (\Throwable $err) {

            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());

        } finally {

            /* Системийн лог үлдээх */
            $context = ['action' => 'settings-files'];

            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Тохируулга файлуудыг шинэчлэх үед алдаа гарч зогслоо';
                $context['error'] = ['code' => $err->getCode(), 'message' => $err->getMessage()];
            } else {
                $level = LogLevel::INFO;
                $message = 'Тохируулга файлуудыг амжилттай шинэчиллээ';
            }

            $this->indolog('content', $level, $message, $context);
        }
    }

    /**
     * Файлыг физик байрлалаас устгах.
     *
     * @param string $fileName  Устгах шаардлагатай файлын нэр
     * @return bool              Амжилттай устгасан эсэх
     *
     * Алдаа гарвал лог үлдээнэ.
     */
    private function unlinkByName(string $fileName): bool
    {
        try {
            $filePath = $this->local . "/$fileName";

            if (!\file_exists($filePath)) {
                throw new \Exception(__CLASS__ . ": File [$filePath] doesn't exist!");
            }

            return \unlink($filePath);

        } catch (\Throwable $err) {

            $this->errorLog($err);
            return false;
        }
    }
}
