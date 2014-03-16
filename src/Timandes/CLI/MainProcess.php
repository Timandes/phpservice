<?php
# vim: set ts=4 sts=4 sw=4 expandtab:
# sublime: tab_size 4; translate_tabs_to_spaces true
/**
 * Main process
 *
 * @package phpservice
 */

namespace Timandes\CLI;

/**
 * Main process
 */
class MainProcess
{
    /**
     * Constructor
     *
     * @param callback $oCallback
     */
    public function __construct($oCallback)
    {
        $this->_callback = $oCallback;
        $this->_eventBase = event_base_new();
        $this->_signalManager = new SignalManager($this->_eventBase);
    }


    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->_signalManager->unhookAllSignals();
        event_base_free($this->_eventBase);
    }


    public function start($nMaxSubProcesses = 5)
    {
        for ($i=0; $i<$nMaxSubProcesses; ++$i) {
            $iSubProcessId = $this->_forkChildProcess();
            if ($iSubProcessId < 0)
                return -1;
        }

        $this->_hookAllSignals();

        while (!event_base_loop($this->_eventBase)
                && !$this->_processExiting
                && $this->_accProcessNum < $this->_maxTotalProcesses) {
            $this->_signalManager->unhookAllSignals();

            $iSubProcessId = $this->_forkChildProcess();
            if ($iSubProcessId < 0) {
                $this->_killAllWorkerProcesses();
                return -1;
            }

            $this->_hookAllSignals();
        }

        return 0;
    }


    /**
     * Fork child process
     *
     * @return int process id of child process
     */
    protected function _forkChildProcess()
    {
        $oChildProcess = new ChildProcess($this->_callback);

        $iSubProcessId = pcntl_fork();
        if ($iSubProcessId < 0) {
            $this->_killAllWorkerProcesses();
            return $iSubProcessId;
        }

        if ($iSubProcessId == 0) { // sub process
            $oChildProcess->start();
        }
        $this->_output("Child process #" . $iSubProcessId . " started.", 2);

        $this->_processMapping[$iSubProcessId] = $oChildProcess;
        ++$this->_accProcessNum;

        return $iSubProcessId;
    }


    /**
     * Kill all child processes
     */
    protected function _killAllWorkerProcesses()
    {
        $this->_output("All child processes will be terminated.", 2);
        if(is_array($this->_processMapping)) foreach($this->_processMapping as $pid => $v)
            posix_kill($pid, SIGTERM);
    }


    public function setVerbose($iVerbose)
    {
        $this->_verbose = $iVerbose;
    }


    protected function _output($sOutputs, $iVerboseWhen = 1)
    {
        if ($this->_verbose >= $iVerboseWhen)
            fprintf(STDOUT, "%s %d: %s\n", date('Y-m-d H:i:s'), getmypid(), $sOutputs);
    }


    /**
     * Handle signal SIGCHILD
     */
    protected function _handleChildSignal()
    {
        $iSubProcessId = pcntl_wait($iWorkerProcessStatus, WNOHANG);
        $this->_output("Child process #" . $iSubProcessId . " exited.", 2);
        if ($iSubProcessId <= 0) {// error or no children quit
            return;
        }

        unset($this->_processMapping[$iSubProcessId]);

        event_base_loopexit($this->_eventBase);
    }

    /**
     * Handle signal SIGTERM
     */
    protected function _handleTerminateSignal() 
    {
        if ($this->_processExiting)
            return;
        $this->_processExiting = true;

        $this->_killAllWorkerProcesses();

        event_base_loopexit($this->_eventBase);
    }


    protected function _hookAllSignals()
    {
        $this->_output("Main process is going to hook signals.", 2);
        $this->_hookSignal(SIGTERM);
        $this->_hookSignal(SIGQUIT);
        $this->_hookSignal(SIGINT);
        $this->_hookSignal(SIGCHLD);
    }


    /**
     * Hook signal for main process
     */
    protected function _hookSignal($iSignal)
    {
        $this->_signalManager->hookSignal($iSignal, array($this, 'signalHandler'));
    }

    /**
     * Signal handler
     */
    public function signalHandler($iSignal) {
        $this->_output("Got signal {$iSignal}");

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
    }


    /** @var callback process function for child process */
    protected $_callback = null;

    /** @var array to save child process id ( pid => ChildProcess ) */
    protected $_processMapping = array();

    /** @var int accumulative created child processes since program started */
    protected $_accProcessNum = 0;

    /** @var resource global event base */
    protected $_eventBase = null;

    /** @var boolean whether if process is exiting */
    protected $_processExiting = false;

    /** @var int max processes */
    protected $_maxTotalProcesses = 10000;

    /** @var int verbose level */
    protected $_verbose = 0;

    protected $_signalManager = null;
}
