<?php

namespace Raptor\Content;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Class FileController
 *
 * Файл upload, validate, rename, move хийх бүх үйлдлийг
 * төвлөрүүлсэн Raptor Controller-ийн дэд класс.
 *
 * --------------------------------------------------------------
 * 📌 Үндсэн боломжууд
 * --------------------------------------------------------------
 *  • setFolder() → upload root (local) & public URL зохицуулна  
 *  • allowExtensions(), allowImageOnly(), allowCommonTypes()  
 *  • setSizeLimit(), setOverwrite()  
 *  • moveUploaded() → файлыг аюулгүй байршуулах гол функц  
 *  • renameTo() → файл сервер дотор байр солих  
 *  • MIME type илрүүлэх, filename collision хамгаалах  
 *  • upload_max_filesize / POST max size → format + convert bytes  
 *
 * @package Raptor\Content
 */
class FileController extends \Raptor\Controller
{
    protected string $local;
    
    protected string $public;
    
    private bool $_overwrite = false;
    
    private int|false $_size_limit = false;
    
    private array|false $_allowed_exts = false;
    
    private int $_upload_error = \UPLOAD_ERR_OK;
    
    /**
     * Upload хийх фолдерийг тохируулна.
     *
     * @param string $folder  /users/1, /pages/22/images зэрэг харьцангуй path
     * @param bool   $relative  true → public URL server root-оос автоматаар үүсгэнэ
     *
     * $this->local  → физик (document root дотор)
     * $this->public → браузер дээр харагдах public URL
     */
    public function setFolder(string $folder, bool $relative = true)
    {
        $script_path = $this->getScriptPath();
        $public_folder = "$script_path/public{$folder}";
        
        $this->local = $this->getDocumentPath('/public' . $folder);
        $this->public = $relative ? $public_folder : (string) $this->getRequest()->getUri()->withPath($public_folder);
    }
    
    /**
     * Public URL үүсгэх (site дээр харуулах)
     *
     * @param string $fileName
     * @return string example: /public/users/4/photo.jpg
     */
    public function getPath(string $fileName): string
    {
        return $this->public . "/$fileName";
    }

    protected function getDocumentPath(string $filePath): string
    {
        return $this->getDocumentRoot() . $filePath;
    }
    
    /**
     * Зөвшөөрөх файл өргөтгөлүүдийг зааж өгнө.
     *
     * @param array $exts
     * @return void
     */
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

    /**
     * Файл давхардах үед overwrite хийх эсэхийг тохируулна.
     *
     * @param bool $overwrite
     *      true  → Нэг нэртэй файл байвал шууд дарж бичнэ
     *      false → Давхцах нэртэй бол uniqueName() ашиглан шинэ нэр үүсгэнэ
     *
     * Анхдагч утга нь `false`.
     *
     * @return void
     */
    public function setOverwrite(bool $overwrite)
    {
        $this->_overwrite = $overwrite;
    }
    
    /**
     * Давхардсан нэртэй файл байвал collision-оос хамгаалж
     * автоматаар дараалсан нэр үүсгэх.
     *
     * Жишээ:
     *   avatar.jpg (байгаа)
     *   avatar_(1).jpg (байгаа)
     *   avatar_(2).jpg (шинэ → сонгоно)
     *
     * @param string $uploadpath   Файлыг хадгалах физик абсолют path ("/var/www/.../")
     * @param string $name         Файлын нэр (өргөтгөлгүй)
     * @param string $ext          Файлын өргөтгөл
     *
     * @return string              Давхцахгүй баталгаатай шинэ filename.ext
     */
    private function uniqueName(string $uploadpath, string $name, string $ext): string
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

    /**
     * Upload хийгдсэн файлыг баталгаажуулж server дээр байршуулна.
     *
     * Validate:
     *   • file exists  
     *   • error == UPLOAD_ERR_OK  
     *   • size < size_limit  
     *   • extension allowed  
     *
     * Хэрвээ overwrite=false → давхар filename collision-оос автоматаар хамгаална.
     *
     * @param string|UploadedFileInterface $uploadedFile
     * @param int $mode  mkdir() permission
     *
     * @return array|false  Амжилттай бол:
     *      [
     *        'path' => public URL,
     *        'file' => absolute local file path,
     *        'size' => байтын хэмжээ,
     *        'mime_content_type' => 'image/jpeg',
     *        'type' => 'image'
     *      ]
     *
     * Амжилтгүй бол false буцаана, алдааны code-г getLastUploadError() авч мэдэж болно.
     */
    protected function moveUploaded($uploadedFile, int $mode = 0755): array|false
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
        } catch (\Throwable $err) {
            if (\is_numeric($err->getCode())) {
                $this->_upload_error = (int) $err->getCode();
            }
            
            // failed to move uploaded file!
            return false;
        }
    }
    
     /**
     * Upload хийсэн файлыг шинэ фолдер руу зөөж нэр өөрчлөх.
     *
     * Тухайн file_id → files table-аас уншиж
     *      users_files
     *      products_files
     *   гэх мэт хүснэгтээс мэдээллийг татаж шинэ замд байршуулна.
     *
     * @param string $table      Үндсэн хүснэгт (users, products гэх мэт)
     * @param int    $record_id  Parent record ID
     * @param int    $file_id    Файлын ID (files table)
     * @return array|false
     */
    protected function renameTo(string $table, int $record_id, int $file_id, int $mode = 0755): array|false
    {
        try {
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record = $model->getRowWhere([
                'id' => $file_id,
                'is_active' => 1
            ]);
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
            $updated = $model->updateById($file_id, $update + ['updated_by' => $this->getUserId()]);
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            return $update;
        } catch (\Throwable $err) {
            $this->errorLog($err);
            return false;
        }
    }
    
    /**
     * Сүүлийн файл upload хийх явцад гарсан алдааны кодыг буцаана.
     *
     * @return int
     *      PHP UPLOAD_ERR_* тогтмолуудаас аль нэг нь буцна:
     *          UPLOAD_ERR_OK (0)
     *          UPLOAD_ERR_INI_SIZE
     *          UPLOAD_ERR_FORM_SIZE
     *          UPLOAD_ERR_NO_FILE
     *          … гэх мэт
     *
     * moveUploaded() → false буцаасан тохиолдолд
     * ямар шалтгаанаар upload амжилтгүй болсон гэдгийг
     * яг энэ функцээр шалгана.
     */
    protected function getLastUploadError(): int
    {
        return $this->_upload_error;
    }
    
    /**
     * PHP тохиргоонд зөвшөөрөгдөх хамгийн их upload хэмжээ
     * (post_max_size, upload_max_filesize) хоёрын хамгийн бага утгыг
     * хүн ойлгох форматаар (10mb, 512kb…) буцаана.
     *
     * Жишээ:
     *   ini: post_max_size = 32M
     *        upload_max_filesize = 8M
     *   → буцах утга: "8mb"
     *
     * @return string
     */
    protected function getMaximumFileUploadSize(): string
    {
        return $this->formatSizeUnits(
            \min(
                $this->convertPHPSizeToBytes(\ini_get('post_max_size')),
                $this->convertPHPSizeToBytes(\ini_get('upload_max_filesize'))
            )
        );
    }
    
    /**
     * php.ini доторх “2M”, “128M”, “1G” зэрэг утгыг byte болгон хөрвүүлэх.
     *
     * @param string|int $sSize
     *      php.ini хэмжээ (120M, 2G, 500K, 4096 гэх мэт)
     *
     * @return int  Byte болгон хөрвүүлсэн тоон утга
     */
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

    /**
     * Byte утгыг хүн уншихад ээлтэй формат руу хөрвүүлнэ:
     *   1024    → "1kb"
     *   1048576 → "1mb"
     *
     * @param int|null $bytes
     * @return string
     */
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
