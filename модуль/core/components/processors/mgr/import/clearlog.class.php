<?php

class mSyncClearLogProcessor extends modProcessor
{
    protected $msync;

    public function initialize()
    {
        $this->msync = $this->modx->msync;
        return true;
    }

    public function process()
    {
        $this->msync->clearLogs();
        return $this->success();
    }
}

return 'mSyncClearLogProcessor';
