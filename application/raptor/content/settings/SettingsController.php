<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

use Raptor\File\FileController;
use Raptor\Log\Logger;
use Raptor\Log\LogsController;

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
            ['model' => SettingsModel::class]
        );
    }
    
    public function post()
    {
        $context = ['model' => SettingsModel::class];
                
        $model = new SettingsModel($this->pdo);
        $current = $model->getRowBy(['p.is_active' => 1]);
        if ($this->getRequest()->getMethod() == 'POST') {
            try {
                if (!$this->isUserCan('system_content_settings')) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                
                $record = [];
                $content = [];
                $payload = $this->getParsedBody();
                $context['updates'] = [];
                $is_update = isset($current['id']);
                foreach ($payload as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                            if (($current['localized'][$index][$key] ?? $value) != $value) {
                                $context['updates'][] = "{$index}_{$key}";
                            }
                        }
                    } else {
                        $record[$index] = $value;
                        if (($current[$index] ?? $value) != $value) {
                            $context['updates'][] = $index;
                        }
                    }
                }
                if ($is_update) {
                    if (empty($context['updates'])) {
                        throw new \InvalidArgumentException('No update!');
                    }
                } elseif ($this->isArrayAllEmpty($record + $content)) {
                    throw new \InvalidArgumentException('No inputs!');
                }
                
                if (!empty($current['config'])) {
                    if (\json_decode($current['config']) == null) {
                        throw new \InvalidArgumentException('Extra config must be valid JSON!', 400);
                    }
                }
                            
                $context['payload'] = $payload;
                
                if (isset($current['id'])) {
                    $id = $current['id'];
                    $updated = $model->updateById($id, $current, $content);
                    if (empty($updated)) {
                        throw new \Exception($this->text('no-record-selected'));
                    }
                    $notify = 'primary';
                    $notice = $this->text('record-update-success');
                } else {
                    if (empty($content)) {
                        $content[$this->getLanguageCode()]['title'] = '';
                    }   
                    $id = $model->insert($current, $content);
                    if ($id == false) {
                        throw new \Exception($this->text('record-insert-error'));
                    }
                    $notify = 'success';
                    $notice = $this->text('record-insert-success');
                }
                $context['record']['id'] = $id;
                
                $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

                $level = LogLevel::INFO;
                $message = 'Системийн тохируулгыг амжилттай хадгаллаа';
            } catch (\Throwable $e) {
                echo $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
                
                $level = LogLevel::ERROR;
                $message = 'Системийн тохируулгыг хадгалах үед алдаа гарч зогслоо';
                $context['error'] = $e->getMessage();
            } finally {
                $this->indolog('content', $level, $message, $context);
            }
        } else {
            if (!$this->isUserCan('system_content_settings')) {
                $this->dashboardProhibited(null, 401)->render();
                return;
            }
            
            $logger = new Logger($this->pdo);
            $logger->setTable('content');
            $condition = ['ORDER BY' => 'id Desc'];
            if ($this->getDriverName() == 'pgsql') {
                $condition['WHERE'] =
                    'context::json->>\'model\'=' . $this->quote($context['model']);
            } else {
                $condition['WHERE'] =
                    'JSON_EXTRACT(context, "$.model")=' . $this->quote($context['model']);
            }
            $logs = $logger->getLogs($condition);
            \array_walk_recursive($logs, [LogsController::class, 'hideSecret']);
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/settings.html',
                ['record' => $current ?? [], 'logs' => $logs, 'users_detail' => $this->retrieveUsersDetail()]
            );
            $dashboard->set('title', $this->text('settings'));
            $dashboard->render();

            $this->indolog('content', LogLevel::NOTICE, 'Системийн тохируулгыг нээж үзэж байна', $context);
        }
    }
    
    public function index11()
    {
        $context = ['model' => SettingsModel::class];
                
        $model = new SettingsModel($this->pdo);
        $current = $model->getRowBy(['p.is_active' => 1]);
        if ($this->getRequest()->getMethod() == 'POST') {
            try {
                if (!$this->isUserCan('system_content_settings')) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                
                $record = [];
                $content = [];
                $payload = $this->getParsedBody();
                $context['updates'] = [];
                $is_update = isset($current['id']);
                foreach ($payload as $index => $value) {
                    if (\is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                            if (($current['localized'][$index][$key] ?? $value) != $value) {
                                $context['updates'][] = "{$index}_{$key}";
                            }
                        }
                    } else {
                        $record[$index] = $value;
                        if (($current[$index] ?? $value) != $value) {
                            $context['updates'][] = $index;
                        }
                    }
                }
                if ($is_update) {
                    if (empty($context['updates'])) {
                        throw new \InvalidArgumentException('No update!');
                    }
                } elseif ($this->isArrayAllEmpty($record + $content)) {
                    throw new \InvalidArgumentException('No inputs!');
                }
                
                if (!empty($current['config'])) {
                    if (\json_decode($current['config']) == null) {
                        throw new \InvalidArgumentException('Extra config must be valid JSON!', 400);
                    }
                }
                            
                $context['payload'] = $payload;
                
                if (isset($current['id'])) {
                    $id = $current['id'];
                    $updated = $model->updateById($id, $current, $content);
                    if (empty($updated)) {
                        throw new \Exception($this->text('no-record-selected'));
                    }
                    $notify = 'primary';
                    $notice = $this->text('record-update-success');
                } else {
                    if (empty($content)) {
                        $content[$this->getLanguageCode()]['title'] = '';
                    }   
                    $id = $model->insert($current, $content);
                    if ($id == false) {
                        throw new \Exception($this->text('record-insert-error'));
                    }
                    $notify = 'success';
                    $notice = $this->text('record-insert-success');
                }
                $context['record']['id'] = $id;
                
                $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

                $level = LogLevel::INFO;
                $message = 'Системийн тохируулгыг амжилттай хадгаллаа';
            } catch (\Throwable $e) {
                echo $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
                
                $level = LogLevel::ERROR;
                $message = 'Системийн тохируулгыг хадгалах үед алдаа гарч зогслоо';
                $context['error'] = $e->getMessage();
            } finally {
                $this->indolog('content', $level, $message, $context);
            }
        } else {
            if (!$this->isUserCan('system_content_settings')) {
                $this->dashboardProhibited(null, 401)->render();
                return;
            }
            
            $logger = new Logger($this->pdo);
            $logger->setTable('content');
            $condition = ['ORDER BY' => 'id Desc'];
            if ($this->getDriverName() == 'pgsql') {
                $condition['WHERE'] =
                    'context::json->>\'model\'=' . $this->quote($context['model']);
            } else {
                $condition['WHERE'] =
                    'JSON_EXTRACT(context, "$.model")=' . $this->quote($context['model']);
            }
            $logs = $logger->getLogs($condition);
            \array_walk_recursive($logs, [LogsController::class, 'hideSecret']);
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/settings.html',
                ['record' => $current ?? [], 'logs' => $logs, 'users_detail' => $this->retrieveUsersDetail()]
            );
            $dashboard->set('title', $this->text('settings'));
            $dashboard->render();

            $this->indolog('content', LogLevel::NOTICE, 'Системийн тохируулгыг нээж үзэж байна', $context);
        }
    }
    
    public function files()
    {
        try {
            $context = ['model' => SettingsModel::class];
            
            if (!$this->isUserCan('system_content_settings')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $model = new SettingsModel($this->pdo);
            $current_record = $model->getRowBy(['p.is_active' => 1]) ?? [];            
            $current_favico_file = \basename($current_record['favico'] ?? '');
            $current_apple_touch_icon_file = \basename($current_record['apple_touch_icon'] ?? '');
            
            $file = new FileController($this->getRequest());
            $file->setFolder('/settings');
            $file->allowImageOnly();
            
            $record = [];
            $content = [];
            foreach (\array_keys($this->getLanguages()) as $code) {
                $current_logo_file = \basename($current_record['content']['logo'][$code] ?? '');
                $logo = $file->moveUploaded("logo_$code", 'content');
                if ($logo) {
                    $content[$code]['logo'] = $logo['path'];
                }
                if (!empty($current_logo_file)) {
                    if ($file->getLastError() == -1) {
                        $file->tryDeleteFile($current_logo_file, 'content');
                        $content[$code]['logo'] = '';
                    } elseif (isset($content[$code]['logo'])
                        && \basename($content[$code]['logo']) != $current_logo_file
                    ) {
                        $file->tryDeleteFile($current_logo_file, 'content');
                    }
                }
                if (isset($content[$code]['logo'])) {
                    $context['record']['logo'] = $content[$code]['logo'];
                }
            }

            $file->allowExtensions(['ico']);
            $ico = $file->moveUploaded('favico', 'content');
            if ($ico) {
                $record['favico'] = $ico['path'];
            }
            if (!empty($current_favico_file)) {
                if ($file->getLastError() == -1) {
                    $file->tryDeleteFile($current_favico_file, 'content');
                    $record['favico'] = '';
                } elseif (isset($record['favico'])
                    && \basename($record['favico']) != $current_favico_file
                ) {
                    $file->tryDeleteFile($current_favico_file, 'content');
                }
            }
            if (isset($record['favico'])) {
                $context['record']['favico'] = $record['favico'];
            }
            
            $file->allowImageOnly();
            $apple_touch_icon = $file->moveUploaded('apple_touch_icon', 'content');
            if ($apple_touch_icon) {
                $record['apple_touch_icon'] = $apple_touch_icon['path'];
            }
            if (!empty($current_apple_touch_icon_file)) {
                if ($file->getLastError() == -1) {
                    $file->tryDeleteFile($current_apple_touch_icon_file, 'content');
                    $record['apple_touch_icon'] = '';
                } elseif (isset($record['apple_touch_icon'])
                    && \basename($record['apple_touch_icon']) != $current_apple_touch_icon_file
                ) {
                    $file->tryDeleteFile($current_apple_touch_icon_file, 'content');
                }
            }
            if (isset($record['apple_touch_icon'])) {
                $context['record']['apple_touch_icon'] = $record['apple_touch_icon'];
            }
            
            if (isset($current_record['id'])) {
                $id = $current_record['id'];
                $updated = $model->updateById($id, $record, $content);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $notify = 'primary';
                $notice = $this->text('record-update-success');
            } else {
                $id = $model->insert($record, $content);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $notify = 'success';
                $notice = $this->text('record-insert-success');
            }
            $context['record']['id'] = $id;
            $context['content'] = $content;
            
            $this->respondJSON(['status' => 'success', 'type' => $notify, 'message' => $notice]);

            $level = LogLevel::INFO;
            $message = 'Системийн тохируулгыг амжилттай хадгаллаа';
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Системийн тохируулгыг хадгалах үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    protected function isArrayAllEmpty($array): bool
    {
        foreach ($array as $value) {
            if (\is_array($value)) {
                if (!$this->isArrayAllEmpty($value)) {
                    return false;
                }
            } elseif ($value !== null
                && !(\is_string($value) && \trim($value) === '')
            ) {
                return false;
            }
        }
        return true;
    }
}
