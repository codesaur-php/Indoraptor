<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

use Raptor\File\FileController;

class SettingsController extends \Raptor\Controller
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

        $this->indolog(
            'content',
            LogLevel::NOTICE,
            'Системийн тохируулгыг нээж үзэж байна',
            ['action' => 'settings']
        );
    }
    
    public function post()
    {
        try {
            $log_context = ['action' => 'settings'];
            
            if (!$this->isUserCan('system_content_settings')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new SettingsModel($this->pdo);
            $current = $model->retrieve();
            
            $payload = $this->getParsedBody();
            $log_context['payload'] = $payload;
            
            $record = [];
            $content = [];
            $log_context['updates'] = [];
            foreach ($payload as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;
                        if (($current['localized'][$index][$key] ?? '') != $value) {
                            $log_context['updates'][] = "{$index}_{$key}";
                        }
                    }
                } else {
                    $record[$index] = $value;
                    if (($current[$index] ?? '') != $value) {
                        $log_context['updates'][] = $index;
                    }
                }
            }
            if (empty($log_context['updates'])) {
                throw new \InvalidArgumentException('No update!');
            }

            if (!empty($record['config'])
                && \json_decode($record['config']) == null
            ) {
                throw new \InvalidArgumentException('Extra config must be valid JSON!', 400);
            }

            if (isset($current['id'])) {
                if (empty($model->updateById($current['id'], $record, $content))) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                if ($model->insert($record, $content) == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }

            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

            $log_level = LogLevel::INFO;
            $log_message = 'Системийн тохируулгыг амжилттай шинэчиллээ';
        } catch (\Throwable $e) {
            echo $this->respondJSON(['message' => $e->getMessage()], $e->getCode());

            $log_level = LogLevel::ERROR;
            $log_message = 'Системийн тохируулгыг шинэчлэх үед алдаа гарч зогслоо';
            $log_context['error'] = $e->getMessage();
        } finally {
            $this->indolog('content', $log_level, $log_message, $log_context);
        }
    }
    
    public function files()
    {
        try {
            $log_context = ['action' => 'settings'];
            
            if (!$this->isUserCan('system_content_settings')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new SettingsModel($this->pdo);
            $current_record = $model->retrieve();            
            $current_favico_name = \basename($current_record['favico'] ?? '');
            $current_apple_touch_icon_name = \basename($current_record['apple_touch_icon'] ?? '');
                        
            $file = new FileController($this->getRequest());
            $file->setFolder('/settings');
            $file->allowExtensions(['ico']);
            
            $record = [];
            $ico = $file->moveUploaded('favico', 'content');
            if ($ico) {
                $record['favico'] = $ico['path'];
            }
            if (!empty($current_favico_name)) {
                if ($this->getParsedBody()['favico_removed'] == 1) {
                    $file->unlinkByName($current_favico_name, 'content');
                    $record['favico'] = '';
                } elseif (isset($record['favico'])
                    && \basename($record['favico']) != $current_favico_name
                ) {
                    $file->unlinkByName($current_favico_name, 'content');
                }
            }
            if (isset($record['favico'])) {
                $log_context['record']['favico'] = $record['favico'];
            }
            
            $file->allowImageOnly();
            $apple_touch_icon = $file->moveUploaded('apple_touch_icon', 'content');
            if ($apple_touch_icon) {
                $record['apple_touch_icon'] = $apple_touch_icon['path'];
            }
            if (!empty($current_apple_touch_icon_name)) {
                if ($this->getParsedBody()['apple_touch_icon_removed'] == 1) {
                    $file->unlinkByName($current_apple_touch_icon_name, 'content');
                    $record['apple_touch_icon'] = '';
                } elseif (isset($record['apple_touch_icon'])
                    && \basename($record['apple_touch_icon']) != $current_apple_touch_icon_name
                ) {
                    $file->unlinkByName($current_apple_touch_icon_name, 'content');
                }
            }
            if (isset($record['apple_touch_icon'])) {
                $log_context['record']['apple_touch_icon'] = $record['apple_touch_icon'];
            }
            
            $content = [];            
            $uploadedLogos = $this->getRequest()->getUploadedFiles()['logo'] ?? [];
            foreach (\array_keys($uploadedLogos) as $code) {
                $current_logo_name = \basename($current_record['localized']['logo'][$code] ?? '');
                $logo = $file->moveUploaded($uploadedLogos[$code], 'content');
                if ($logo) {
                    $content[$code]['logo'] = $logo['path'];
                }
                if (!empty($current_logo_name)) {
                    if ($this->getParsedBody()["logo_{$code}_removed"] == 1) {
                        $file->unlinkByName($current_logo_name, 'content');
                        $content[$code]['logo'] = '';
                    } elseif (isset($content[$code]['logo'])
                        && \basename($content[$code]['logo']) != $current_logo_name
                    ) {
                        $file->unlinkByName($current_logo_name, 'content');
                    }
                }
                if (isset($content[$code]['logo'])) {
                    $log_context['record']['logo'][$code] = $content[$code]['logo'];
                }
            }
            
            if (isset($current_record['id'])) {
                if (empty($model->updateById($current_record['id'], $record, $content))) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                if ($model->insert($record, $content) == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }
            
            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

            $log_level = LogLevel::INFO;
            $log_message = 'Системийн тохируулгыг амжилттай шинэчиллээ';
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            
            $log_level = LogLevel::ERROR;
            $log_message = 'Системийн тохируулгыг шинэчлэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('content', $log_level, $log_message, $log_context);
        }
    }
}
