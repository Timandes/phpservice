<?php
# vim: set ts=4 sts=4 sw=4 expandtab:
# sublime: tab_size 4; translate_tabs_to_spaces true

namespace Timandes\CLI;

class Service
{
    public function __construct($oCallback, $nMaxSubProcesses = 5)
    {
        $this->_callback = $oCallback;
        $this->_maxSubProcessNum = $nMaxSubProcesses;
        $this->_eventBase = event_base_new();
    }


    public function __destruct()
    {
        event_base_free($this->_eventBase);
    }


    public static function create($oCallback, $nMaxSubProcesses = 5)
    {
        return new Service($oCallback, $nMaxSubProcesses);
    }


    public function start()
    {
        for ($i=0; $i<$this->_maxSubProcessNum; ++$i) {
            $iSubProcessId = pcntl_fork();
            if ($iSubProcessId < 0) {
                $this->_killAllWorkerProcesses();
                return 1;
            }

            if ($iSubProcessId == 0) { // sub process
                exit(call_user_func($this->_callback));
            }

            $this->_processMapping[$iSubProcessId] = $iSubProcessId;
            ++$this->_accProcessNum;
        }

        $this->_registerSignal(SIGTERM);
        $this->_registerSignal(SIGQUIT);
        $this->_registerSignal(SIGINT);

        // TODO: add loop here ...

        return 0;
    }


    protected function _killAllWorkerProcesses()
    {

    }

    protected function _registerSignal($iSignal)
    {

    }

    protected $_callback = null;
    protected $_maxSubProcessNum = 5;
    protected $_processMapping = array();
    protected $_accProcessNum = 0;
    protected $_eventBase = null;
    protected $_mainSignalHandler = function($iSignal) {
    };
    protected $_childSignalHandler = function($iSignal) {
    };
}