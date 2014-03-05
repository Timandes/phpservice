<?php
# vim: set ts=4 sts=4 sw=4 expandtab:
# sublime: tab_size 4; translate_tabs_to_spaces true

namespace Timandes\CLI;

class Service
{
    public function __construct($oCallback)
    {
        $this->_callback = $oCallback;
        $this->_eventBase = event_base_new();
    }


    public function __destruct()
    {
        event_base_free($this->_eventBase);
    }


    public static function create($oCallback)
    {
        return new Service($oCallback);
    }


    public function start($nMaxSubProcesses = 5)
    {
        // TODO: daemonize

        for ($i=0; $i<$nMaxSubProcesses; ++$i) {
            $iSubProcessId = $this->_forkChildProcess();
            if ($iSubProcessId < 0)
                return 1;
        }

        $this->_hookSignalForMainProcess(SIGTERM);
        $this->_hookSignalForMainProcess(SIGQUIT);
        $this->_hookSignalForMainProcess(SIGINT);
        $this->_hookSignalForMainProcess(SIGCHLD);


        while (!event_base_loop($this->_eventBase)
                && !$this->_processExiting
                && $this->_accProcessNum < $this->_maxTotalProcesses) {
            $iSubProcessId = $this->_forkChildProcess();
            if ($iSubProcessId < 0)
                return 1;
        }

        return 0;
    }


    protected function _startChildProcess()
    {
        $this->_unhookSignalForMainProcess(SIGTERM);
        $this->_unhookSignalForMainProcess(SIGQUIT);
        $this->_unhookSignalForMainProcess(SIGINT);
        $this->_unhookSignalForMainProcess(SIGCHLD);

        $this->_hookSignalForChildProcess(SIGTERM);
        $this->_hookSignalForChildProcess(SIGQUIT);
        $this->_hookSignalForChildProcess(SIGINT);

        exit(call_user_func($this->_callback));
    }


    protected function _forkChildProcess()
    {
        $iSubProcessId = pcntl_fork();
        if ($iSubProcessId < 0) {
            $this->_killAllWorkerProcesses();
            return $iSubProcessId;
        }

        if ($iSubProcessId == 0) { // sub process
            $this->_startChildProcess();
        }

        $this->_processMapping[$iSubProcessId] = $iSubProcessId;
        ++$this->_accProcessNum;

        return $iSubProcessId;
    }


    protected function _killAllWorkerProcesses()
    {

    }

    protected function _handleChildSignal()
    {
        $iSubProcessId = pcntl_wait($iWorkerProcessStatus, WNOHANG);
        if ($iSubProcessId <= 0) {// error or no children quit
            return;
        }

        unset($this->_processMapping[$iSubProcessId]);

        event_base_loopexit($this->_eventBase);
    }

    protected function _handleTerminateSignal() 
    {
        if ($this->_processExiting)
            return;
        $this->_processExiting = true;

        $this->_killAllWorkerProcesses();

        event_base_loopexit($this->_eventBase);
    }

    protected function _hookSignalForMainProcess($iSignal)
    {
    }

    protected function _unhookSignalForMainProcess($iSignal)
    {

    }

    protected function _hookSignalForChildProcess($iSignal)
    {
    }

    protected function _unhookSignalForChildProcess($iSignal)
    {

    }

    protected $_callback = null;
    protected $_processMapping = array();
    protected $_accProcessNum = 0;
    protected $_eventBase = null;
    protected $_mainSignalHandler = function($iSignal) {
        switch ($iSignal) {
            case SIGCHLD:
                $this->_handleChildSignal();
                break;
            case SIGTERM:
            case SIGQUIT:
            case SIGINT:
                $this->_handleTerminateSignal();
                break;
        }
    };
    protected $_childSignalHandler = function($iSignal) {
    };
    protected $_processExiting = false;
    protected $_maxTotalProcesses = 10000;
}