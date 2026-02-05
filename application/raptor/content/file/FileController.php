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
 *  • optimizeImage() -> зургийн файлыг web-д зориулж optimize хийх
 *  • MIME type илрүүлэх, filename collision хамгаалах  
 *  • upload_max_filesize / POST max size → format + convert bytes  
 *
 * @package Raptor\Content
 */
class FileController extends \Raptor\Controller
{
    protected string $local_folder;
    
    protected string $public_path;
    
    private bool $_overwrite = false;
    
    private int|false $_size_limit = false;
    
    private array|false $_allowed_exts = false;
    
    private int $_upload_error = \UPLOAD_ERR_OK;
    
    /**
     * Upload хийх фолдерийг тохируулна.
     *
     * @param string $folder  /users/1, /pages/22, /settings зэрэг харьцангуй path
     *
     * $this->local  → физик (document root дотор)
     * $this->public → браузер дээр харагдах public URL
     */
    public function setFolder(string $folder)
    {
        $this->local_folder = $this->getDocumentPath("/public{$folder}");
        $this->public_path = "{$this->getScriptPath()}/public{$folder}";        
    }
    
    /**
     * Public URL үүсгэх (site дээр харуулах)
     *
     * @param string $fileName
     * @return string example: /public/users/1/photo.jpg
     */
    public function getFilePublicPath(string $fileName): string
    {
        return $this->public_path . "/" . \rawurlencode($fileName);
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
     *        'type' => 'image',
     *        'mime_content_type' => 'image/jpeg'
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

            $upload_path = "$this->local_folder/";
            $file_name = \basename($uploadedFile->getClientFilename());
            $name = \pathinfo($file_name, \PATHINFO_FILENAME);
            $ext = \strtolower(\pathinfo($file_name, \PATHINFO_EXTENSION));

            if ($this->_allowed_exts
                && !\in_array($ext, $this->_allowed_exts)
            ) {
                throw new \Exception('The uploaded file ext is not allowed', 9);
            }

            // Path урт шалгах (VARCHAR(255) - unique suffix _(XXX) = 10 = 245)
            // base = public_path + "/" + "." + ext
            $base_length = \strlen($this->public_path) + 2 + \strlen(\rawurlencode($ext));
            $max_name_length = 255 - ($this->_overwrite ? 0 : 10) - $base_length;
            if (\strlen(\rawurlencode($name)) > $max_name_length) {
                // Нэр хэт урт - file-{uniqid} болгох
                $name = 'file-' . \uniqid();
                $file_name = "$name.$ext";
            }
            if (!$this->_overwrite) {
                $file_name = $this->uniqueName($upload_path, $name, $ext);
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
                'path' => $this->getFilePublicPath($file_name),
                'file' => $file_path,
                'size' => $file_size,
                'type' => \explode('/', $mime_type)[0] ?? 'unknown',
                'mime_content_type' => $mime_type
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
     * Зургийг web-д зориулж optimize хийх.
     *
     * Зургийн чанарыг тохируулж, хэрэв том бол хэмжээг багасгана.
     * JPEG, PNG, GIF, WebP форматуудыг дэмждэг.
     * PNG/GIF-ийн transparency хадгалагдана.
     *
     * Үйлдэл:
     *   - Бүх зурагт quality compression хийнэ (JPEG/WebP)
     *   - Хэрэв width > maxWidth бол resize хийнэ
     *   - Жижиг зурагт зөвхөн quality optimize хийнэ
     *   - Аль хэдийн optimize хийгдсэн зургийг давхар optimize хийхгүй
     *     (хэрэв шинэ файл 10%-аас бага хэмнэлттэй бол эх файлыг хэвээр үлдээнэ)
     *
     * Тохиргоо (.env):
     *   - INDO_CONTENT_IMG_MAX_WIDTH: Хамгийн их өргөн (default: 1920)
     *   - INDO_CONTENT_IMG_QUALITY: JPEG/WebP чанар 1-100 (default: 90)
     *
     * @param string $filePath Зургийн физик зам
     *
     * @return bool Optimize хийгдсэн эсэх:
     *   - true: Зураг амжилттай optimize хийгдсэн
     *   - false: Optimize шаардлагагүй, алдаа, эсвэл дэмжигдээгүй формат
     *
     * @requires ext-gd GD extension шаардлагатай
     */
    protected function optimizeImage(string $filePath): bool
    {
        // GD сан суусан эсэхийг шалгах
        if (!\extension_loaded('gd')) {
            \error_log('optimizeImage: GD extension суугаагүй байна');
            return false;
        }

        // Файл байгаа эсэхийг шалгах
        if (!\file_exists($filePath) || !\is_readable($filePath)) {
            return false;
        }

        $maxWidth = (int) (\getenv('INDO_CONTENT_IMG_MAX_WIDTH') ?: ($_ENV['INDO_CONTENT_IMG_MAX_WIDTH'] ?? 1920));
        $quality = (int) (\getenv('INDO_CONTENT_IMG_QUALITY') ?: ($_ENV['INDO_CONTENT_IMG_QUALITY'] ?? 90));

        $imageInfo = @\getimagesize($filePath);
        if (!$imageInfo) {
            return false;
        }

        [$width, $height, $type] = $imageInfo;

        // Эх файлын хэмжээг хадгалах (дараа нь харьцуулахад ашиглана)
        $originalSize = \filesize($filePath);

        // Resize хэрэгтэй эсэхийг шалгах
        $needsResize = $width > $maxWidth;

        // Шинэ хэмжээ тооцоолох (resize хэрэггүй бол хуучин хэмжээ)
        $newWidth = $needsResize ? $maxWidth : $width;
        $newHeight = $needsResize ? (int) ($height * ($maxWidth / $width)) : $height;

        // Зураг үүсгэх
        $source = null;
        switch ($type) {
            case \IMAGETYPE_JPEG:
                $source = @\imagecreatefromjpeg($filePath);
                break;
            case \IMAGETYPE_PNG:
                $source = @\imagecreatefrompng($filePath);
                break;
            case \IMAGETYPE_GIF:
                $source = @\imagecreatefromgif($filePath);
                break;
            case \IMAGETYPE_WEBP:
                if (\function_exists('imagecreatefromwebp')) {
                    $source = @\imagecreatefromwebp($filePath);
                }
                break;
            default:
                \error_log("optimizeImage: Дэмжигдээгүй зургийн төрөл: $type");
                return false;
        }

        if (!$source) {
            return false;
        }

        // EXIF orientation дагуу зургийг эргүүлэх (гар утасны зураг эргэх асуудлыг шийднэ)
        $exifRotated = false;
        if ($type === \IMAGETYPE_JPEG && \function_exists('exif_read_data')) {
            $exif = @\exif_read_data($filePath);
            if (!empty($exif['Orientation']) && $exif['Orientation'] != 1) {
                switch ($exif['Orientation']) {
                    case 3:
                        $source = \imagerotate($source, 180, 0);
                        $exifRotated = true;
                        break;
                    case 6:
                        $source = \imagerotate($source, -90, 0);
                        [$width, $height] = [$height, $width];
                        $exifRotated = true;
                        break;
                    case 8:
                        $source = \imagerotate($source, 90, 0);
                        [$width, $height] = [$height, $width];
                        $exifRotated = true;
                        break;
                }
                if ($exifRotated) {
                    $needsResize = $width > $maxWidth;
                    $newWidth = $needsResize ? $maxWidth : $width;
                    $newHeight = $needsResize ? (int) ($height * ($maxWidth / $width)) : $height;
                }
            }
        }

        // Зураг боловсруулах (resize эсвэл quality optimize)
        if ($needsResize) {
            // Resize хийх
            $output = \imagecreatetruecolor($newWidth, $newHeight);
            if (!$output) {
                \imagedestroy($source);
                return false;
            }

            // PNG болон GIF-ийн transparency хадгалах
            if ($type === \IMAGETYPE_PNG || $type === \IMAGETYPE_GIF) {
                \imagealphablending($output, false);
                \imagesavealpha($output, true);
                $transparent = \imagecolorallocatealpha($output, 255, 255, 255, 127);
                \imagefilledrectangle($output, 0, 0, $newWidth, $newHeight, $transparent);
            }

            \imagecopyresampled($output, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        } else {
            // Resize хэрэггүй, зөвхөн quality optimize хийнэ
            $output = $source;
            $source = null; // $output руу шилжүүлсэн тул дахин destroy хийхгүй
        }

        // Түр файлд хадгалах (хэмжээ харьцуулахын тулд)
        $tempPath = $filePath . '.tmp';
        $saved = false;
        switch ($type) {
            case \IMAGETYPE_JPEG:
                $saved = @\imagejpeg($output, $tempPath, $quality);
                break;
            case \IMAGETYPE_PNG:
                // PNG compression level: 0-9 (6 нь сайн харьцаа)
                $saved = @\imagepng($output, $tempPath, 6);
                break;
            case \IMAGETYPE_GIF:
                $saved = @\imagegif($output, $tempPath);
                break;
            case \IMAGETYPE_WEBP:
                if (\function_exists('imagewebp')) {
                    $saved = @\imagewebp($output, $tempPath, $quality);
                }
                break;
        }

        if ($source) {
            \imagedestroy($source);
        }
        \imagedestroy($output);

        if (!$saved || !\file_exists($tempPath)) {
            return false;
        }

        $optimizedSize = \filesize($tempPath);

        // EXIF эргүүлэлт хийгдсэн бол заавал солих, эсвэл 10%-аас дээш хэмнэлттэй бол солих
        if ($exifRotated || $optimizedSize < $originalSize * 0.90) {
            // Optimize үр дүнтэй - шинэ файлаар солих
            \unlink($filePath);
            \rename($tempPath, $filePath);
            return true;
        } else {
            // Optimize үр дүнгүй - эх файлыг хэвээр үлдээх
            \unlink($tempPath);
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
     * @return int  Byte хэмжээ
     */
    protected function getMaximumFileUploadSize(): int
    {
        return \min(
            $this->convertPHPSizeToBytes(\ini_get('post_max_size')),
            $this->convertPHPSizeToBytes(\ini_get('upload_max_filesize'))
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

    /**
     * Файлыг физик байрлалаас устгах.
     *
     * @param string $fileName  Устгах шаардлагатай файлын нэр
     * @return bool             Амжилттай устгасан эсэх
     *
     * Алдаа гарвал лог үлдээнэ.
     */
    protected function unlinkByName(string $fileName): bool
    {
        try {
            $filePath = $this->local_folder . "/$fileName";
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
