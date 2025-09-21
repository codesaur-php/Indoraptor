<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

class SettingsController extends FileController
{
    use \Raptor\Template\DashboardTrait;
    
    public function index()
    {
        if (!$this->isUserCan('system_content_settings')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $dashboard = $this->twigDashboard(
            \dirname(__FILE__) . '/settings.html',
            [
                'record' => (new SettingsModel($this->pdo))->retrieve()
            ]
        );
        $dashboard->set('title', $this->text('settings'));
        $dashboard->render();

        $this->indolog('content', LogLevel::NOTICE, 'Тохируулгыг нээж байна', ['action' => 'settings-index']);
    }
    
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
            foreach ($parsedBody as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;
                        if (($current['localized'][$index][$key] ?? '') != $value) {
                            $updates[] = "{$index}_{$key}";
                        }
                    }
                } else {
                    $payload[$index] = $value;
                    if (($current[$index] ?? '') != $value) {
                        $updates[] = $index;
                    }
                }
            }
            if (empty($updates)) {
                throw new \InvalidArgumentException('No update!');
            }
            if (!empty($payload['config'])
                && \json_decode($payload['config']) == null
            ) {
                throw new \InvalidArgumentException('Extra config must be valid JSON!', 400);
            }
            if (isset($current['id'])) {
                if (empty($model->updateById(
                        $current['id'], $payload + ['updated_by' => $this->getUserId()], $content)
                    )
                ) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                if ($model->insert($payload + ['created_by' => $this->getUserId()], $content) == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }
            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);
       } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'settings-post'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Тохируулгыг шинэчлэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = 'Тохируулгыг амжилттай шинэчиллээ';
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
    
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
            $favico_name = \basename($current['favico'] ?? '');
            $this->allowExtensions(['ico']);
            $ico = $this->moveUploaded('favico');
            if (!empty($favico_name)
                && $parsedBody['favico_removed'] == 1
            ) {
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
            
            if (isset($current['id'])) {
                if (empty($model->updateById(
                        $current['id'], $payload + ['updated_by' => $this->getUserId()], $content
                    )
                )) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                if ($model->insert($payload + ['created_by' => $this->getUserId()], $content) == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }            
            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'settings-files'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Тохируулга файлуудыг шинэчлэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = 'Тохируулга файлуудыг амжилттай шинэчиллээ';
            }
            $this->indolog('content', $level, $message, $context);
        }
    }
}
