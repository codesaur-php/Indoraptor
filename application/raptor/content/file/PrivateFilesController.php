<?php

namespace Raptor\Content;

class PrivateFilesController extends FilesController
{
    public function setFolder(string $folder, bool $relative = true)
    {
        $script_path = $this->getScriptPath();
        $public_folder = "$script_path/private/file?name=$folder";
        
        $this->local = $this->getDocumentPath('/../private' . $folder);
        $this->public = $relative ? $public_folder : (string) $this->getRequest()->getUri()->withPath($public_folder);
    }
    
    public function getPath(string $fileName): string
    {
        return "$this->public/" . \urlencode($fileName);
    }
    
    public function read()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $fileName = $this->getQueryParams()['name'] ?? '';
            $filePath = $this->getDocumentPath('/../private' . $fileName);
            if (empty($fileName) || !\file_exists($filePath)) {
                throw new \Exception('Not Found', 404);
            }

            $mimeType = \mime_content_type($filePath);
            if ($mimeType === false) {
                throw new \Exception('No Content', 204);
            }

            \header("Content-Type: $mimeType");
            \readfile($filePath);
        } catch (\Throwable $err) {
            $this->errorLog($err);
            $this->headerResponseCode($err->getCode());
        }
    }
}
