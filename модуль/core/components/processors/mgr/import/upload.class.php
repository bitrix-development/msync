<?php

class mSyncImportUploadProcessor extends modProcessor
{

    protected $mode;
    protected $filename;
    protected $link;
    protected $msync;

    public function initialize()
    {
        $this->msync = $this->modx->msync;
        $this->msync->initialize();

        return true;
    }

    public function process()
    {
        $path = $this->getProperty('path');
        $file = $this->getProperty('file');
        if (!$file && !is_array($file)) {
            return $this->failure('Не выбран файл');
        }
        $filePath = $path . '/' . $file['name'];
        $this->modx->runProcessor('browser/file/remove', array('file' => $filePath));

        $response = $this->modx->runProcessor('browser/file/upload', $this->getProperties());
        if ($response->isError()) {
            return $this->failure('Не удалось загрузить файл');
        } 
        return $this->success();
    }
}

return 'mSyncImportUploadProcessor';
