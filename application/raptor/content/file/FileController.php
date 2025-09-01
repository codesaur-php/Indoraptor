<?php

namespace Raptor\Content;

use Psr\Http\Message\UploadedFileInterface;

class FileController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;
    
    protected string $local;
    
    protected string $public;
    
    private bool $_overwrite = false;
    
    private int|false $_size_limit = false;
    
    private array|false $_allowed_exts = false;
    
    private int $_upload_error = \UPLOAD_ERR_OK;
    
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

    public function moveUploaded($uploadedFile, int $mode = 0755): array|false
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
            $this->_upload_error = \UPLOAD_ERR_OK;
            
            $file_path = $upload_path . $file_name;
            $mime_type = \mime_content_type($file_path) ?: 'application/octet-stream';
            return [
                'path' => $this->getPath($file_name),
                'file' => $file_path,
                'size' => $file_size,
                'mime_content_type' => $mime_type,
                'type' => \explode('/', $mime_type)[0] ?? 'unknown'
            ];
        } catch (\Throwable $e) {
            $this->errorLog($e);
            
            if (\is_numeric($e->getCode())) {
                $this->_upload_error = (int) $e->getCode();
            }
            
            // failed to move uploaded file!
            return false;
        }
    }
    
    public function renameTo(string $table, int $record_id, int $file_id, int $mode = 0755): array|false
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }
            
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record = $model->getById($file_id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->setFolder("/$table/$record_id");
            $upload_path = "$this->local/";
            $file_name = \basename($record['file']);
            if (!\file_exists($upload_path)
                || !\is_dir($upload_path)
            ) {
                \mkdir($upload_path, $mode, true);
            } else {
                $name = \pathinfo($file_name, \PATHINFO_FILENAME);
                $ext = \strtolower(\pathinfo($file_name, \PATHINFO_EXTENSION));
                $file_name = $this->uniqueName($upload_path, $name, $ext);
            }
            $newPath = $upload_path . $file_name;
            if (!\rename($record['file'], $newPath)) {
                throw new \Exception("Can't rename file [{$record['file']}] to [$newPath]");
            }
            $update = [
                'file' => $newPath,
                'path' => $this->getPath($file_name),
                'record_id' => $record_id,
            ];
            $updated = $model->updateById($file_id, $update);
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            return $update;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return false;
        }
    }
    
    protected function deleteUnlink(string $fileName): bool
    {
        try {
            $filePath = $this->local . "/$fileName";
            if (!\file_exists($filePath)) {
                throw new \Exception(__CLASS__ . ": File [$filePath] doesn't exist!");
            }
            
            return \unlink($filePath);
        } catch (\Throwable $e) {
            $this->errorLog($e);
            
            return false;
        }
    }
    
    protected function getLastUploadError(): int
    {
        return $this->_upload_error;
    }
    
    protected function getMaximumFileUploadSize(): string
    {
        return $this->formatSizeUnits(
            \min(
                $this->convertPHPSizeToBytes(\ini_get('post_max_size')),
                $this->convertPHPSizeToBytes(\ini_get('upload_max_filesize'))
            )
        );
    }
    
    protected function convertPHPSizeToBytes($sSize): int
    {
        $sSuffix = \strtoupper(\substr($sSize, -1));
        if (!\in_array($sSuffix, ['P','T','G','M','K'])){
            return (int)$sSize;
        }
        $iValue = \substr($sSize, 0, -1);
        switch ($sSuffix) {
            case 'P':
                $iValue *= 1024;
            case 'T':
                $iValue *= 1024;
            case 'G':
                $iValue *= 1024;
            case 'M':
                $iValue *= 1024;
            case 'K':
                $iValue *= 1024;
                break;
        }
        return (int)$iValue;
    }

    protected function formatSizeUnits(?int $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return \number_format($bytes / 1099511627776, 2) . 'tb';
        } elseif ($bytes >= 1073741824) {
            return \number_format($bytes / 1073741824, 2) . 'gb';
        } elseif ($bytes >= 1048576) {
            return \number_format($bytes / 1048576, 2) . 'mb';
        } elseif ($bytes >= 1024) {
            return \number_format($bytes / 1024, 2) . 'kb';
        } else {
            return $bytes . 'b';
        }
    }
}
