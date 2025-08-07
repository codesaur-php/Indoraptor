<?php

namespace Raptor\File;

use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LogLevel;

class FileController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    protected string $local;
    
    protected string $public;
    
    private bool $_overwrite = false;
    
    private int|false $_size_limit = false;
    
    private array|false $_allowed_exts = false;
    
    private int $_error = \UPLOAD_ERR_OK;
    
    public function setFolder(string $folder, bool $relative = true)
    {
        $script_path = $this->getScriptPath();
        $public_folder = "$script_path/public{$folder}";
        
        $this->local = $this->getDocumentPath('/public' . $folder);
        $this->public = $relative ? $public_folder : (string) $this->getRequest()->getUri()->withPath($public_folder);
    }
    
    public function getPath(string $fileName): string
    {
        return $this->public . "/$fileName";
    }

    protected function getDocumentPath(string $filePath): string
    {
        return $this->getDocumentRoot() . $filePath;
    }
    
    public function allowExtensions(array $exts)
    {
        $this->_allowed_exts = $exts;
    }

    public function allowAnything()
    {
        $this->_allowed_exts = false;
    }
    
    public function allowImageOnly()
    {
        $this->allowExtensions(['jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp']);
    }
    
    public function allowCommonTypes()
    {
        $this->allowExtensions([
            'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'ico',
            'pdf', 'doc', 'docx', 'ppt', 'pptx', 'pps', 'ppsx', 'xls', 'xlsx', 'odt', 'psd',
            'mp3', 'm4a', 'ogg', 'wav',
            'mp4', 'm4v', 'mov', 'wmv', 'avi', 'mpg', 'ogv', '3gp', '3g2',
            'txt', 'xml', 'json',
            'zip', 'rar'
        ]);
    }

    public function setSizeLimit(int $size)
    {
        $this->_size_limit = $size;
    }

    public function setOverwrite(bool $overwrite)
    {
        $this->_overwrite = $overwrite;
    }
    
    protected function uniqueName(string $uploadpath, string $name, string $ext): string
    {
        $filename = $name . '.' . $ext;
        if (\file_exists($uploadpath . $filename)) {
            $number = 1;
            while (true) {
                if (\file_exists($uploadpath . $name . "_($number)." . $ext)) {
                    $number++;
                } else {
                    break;
                }
            }
            $filename = $name . "_($number)." . $ext;
        }
        
        return $filename;
    }

    public function moveUploaded($uploadedFile, ?string $recordTableName = null, int $mode = 0755): array|false
    {
        try {
            if (\is_string($uploadedFile)) {
                $uploadedFile = $this->getRequest()->getUploadedFiles()[$uploadedFile] ?? null;
            }
            if (!$uploadedFile instanceof UploadedFileInterface) {
                throw new \Exception('No file upload provided', -1);
            }
            if ($uploadedFile->getError() != \UPLOAD_ERR_OK) {
                throw new \Exception('File upload error', $uploadedFile->getError());
            }

            $file_size = $uploadedFile->getSize();
            if ($this->_size_limit
                && $file_size > $this->_size_limit
            ) {
                throw new \Exception('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', \UPLOAD_ERR_FORM_SIZE);
            }

            $upload_path = "$this->local/";
            $file_name = \basename($uploadedFile->getClientFilename());
            $name = \pathinfo($file_name, \PATHINFO_FILENAME);
            $ext = \strtolower(\pathinfo($file_name, \PATHINFO_EXTENSION));
            if (!$this->_overwrite) {
                $file_name = $this->uniqueName($upload_path, $name, $ext);
            }

            if ($this->_allowed_exts
                && !\in_array($ext, $this->_allowed_exts)
            ) {
                throw new \Exception('The uploaded file ext is not allowed', 9);
            }

            if (!\file_exists($upload_path)
                || !\is_dir($upload_path)
            ) {
                \mkdir($upload_path, $mode, true);
            }
            
            $uploadedFile->moveTo($upload_path . $file_name);
            $this->_error = \UPLOAD_ERR_OK;
            
            $file_path = $upload_path . $file_name;
            $mime_type = \mime_content_type($file_path) ?: 'application/octet-stream';
            $context = [
                'path' => $this->getPath($file_name),
                'file' => $file_path,
                'size' => $file_size,
                'mime_content_type' => $mime_type,
                'type' => \explode('/', $mime_type)[0] ?? 'unknown'
            ];
            
            $this->indolog(
                $recordTableName ?? 'files',
                LogLevel::ALERT,
                "<a target=\"__blank\" href=\"{$context['path']}\">{$context['path']}</a> файл байршууллаа",
                $context + ['reason' => 'file-move-uploaded']
            );
            
            return $context;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            
            if (\is_numeric($e->getCode())) {
                $this->_error = (int) $e->getCode();
            }
            
            // failed to move uploaded file!
            return false;
        }
    }
    
    public function tryDeleteFile(string $fileName, ?string $recordTableName = null)
    {
        try {
            $filePath = $this->local . "/$fileName";
            if (!\file_exists($filePath)) {
                throw new \Exception(__CLASS__ . ": File [$filePath] doesn't exist!");
            }
            
            \unlink($filePath);
            
            $this->indolog($recordTableName ?? 'files', LogLevel::ALERT, "$filePath файлыг устгалаа");
        } catch (\Throwable $e) {
            $this->errorLog($e);
        }
    }
    
    public function getLastError(): int
    {
        return $this->_error;
    }
}
