<?php

class mSyncImportProcessProcessor extends modProcessor
{

    protected $mode;
    protected $filename;
    protected $link;
    protected $msync;

    public function initialize()
    {
        $this->msync = $this->modx->msync;
        $this->msync->initialize();

        $this->mode = $this->getProperty('mode');
        if (!in_array($this->mode, array('init', 'checkauth', 'file', 'import'))) {
            return $this->modx->lexicon('msync_import_process_mode_error');
        }
        $this->filename = $this->getProperty('filename');
        if (empty($this->filename) && in_array($this->mode, array('file', 'import'))) {
            return $this->modx->lexicon('msync_import_process_filename_error');
        }

        $login = $this->modx->getOption('msync_1c_sync_login', null, '');
        $pass = $this->modx->getOption('msync_1c_sync_pass', null, '');
        $this->link = $this->msync->config['commercMlLink'] . "?http_auth=htauth:" .
            base64_encode($login . ":" . $pass) . "&type=catalog&mode={$this->mode}";
        if (!empty($this->filename)) {
            $this->link .= "&filename={$this->filename}";
        }



        return true;
    }

    public function process()
    {
        switch ($this->mode) {
            case 'checkauth':
                $response = $this->msync->catalog->checkauth();
                return $this->success($response);
                break;
            case 'init':
                unset($_SESSION['mSyncLogFile']);
                $this->msync->catalog->initialize();
                $response = $this->msync->catalog->resetSession();
                return $this->success($response);
                break;
            case 'file':
                $response = $this->msync->catalog->file($this->filename, @file_get_contents("php://input"));
                return $this->success($response);
                break;
            case 'import':
                $response = $this->msync->catalog->import($this->filename, @file_get_contents("php://input"));
                break;
            default:
        }

        $result = array('result' => $response);

        if (strpos($response, 'progress') === 0) {

            return $this->success('progress', $result);
        }

        if (strpos($response, 'success') === 0) {
            return $this->success('success', $result);
        }

        if (strpos($response, 'failure') === 0) {
            return $this->failure('failure', $response);
        }

        return $this->failure("Unknown error");
    }
}

return 'mSyncImportProcessProcessor';
