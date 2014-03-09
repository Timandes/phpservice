<?php
# vim: set ts=4 sts=4 sw=4 expandtab:
# sublime: tab_size 4; translate_tabs_to_spaces true
/**
 * Service
 *
 * @package phpservice
 */

namespace Timandes\CLI;

/**
 * Service
 */
class Service
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
    }


    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->_unhookAllSignals();
        event_base_free($this->_eventBase);
    }


    /**
     * Create service
     *
     * @param callback $oCallback
     * @return \Timandes\CLI\Service
     */
    public static function create($oCallback)
    {
        return new Service($oCallback);
    }


    /**
     * Start service
     *
     * @param int $nMaxSubProcesses
     * @return int
     */
    public function start($nMaxSubProcesses = 5)
    {
        if ($this->daemonize() < 0)
            return -1;

        for ($i=0; $i<$nMaxSubProcesses; ++$i) {
            $iSubProcessId = $this->_forkChildProcess();
            if ($iSubProcessId < 0)
                return -1;
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
                return -1;
        }

        return 0;
    }


    /**
     * Daemonize
     *
     * @return int
     */
    public function daemonize() {
        $pid = pcntl_fork();
        switch($pid) {
            case -1:
                return -1;
            case 0:
                break;
            default:
                exit(0);
        }

        if(posix_setsid() < 0)
            return -1;

        return 0;
    }


    /**
     * Call callback function
     */
    protected function _startChildProcess()
    {
        $this->_unhookSignal(SIGTERM);
        $this->_unhookSignal(SIGQUIT);
        $this->_unhookSignal(SIGINT);
        $this->_unhookSignal(SIGCHLD);

        $this->_hookSignalForChildProcess(SIGTERM);
        $this->_hookSignalForChildProcess(SIGQUIT);
        $this->_hookSignalForChildProcess(SIGINT);

        exit(call_user_func($this->_callback));
    }


    /**
     * Fork child process
     *
     * @return int process id of child process
     */
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


    /**
     * Kill all child processes
     */
    protected function _killAllWorkerProcesses()
    {
        if(is_array($this->_processMapping)) foreach($this->_processMapping as $pid => $v)
            posix_kill($pid, SIGTERM);
    }


    /**
     * Handle signal SIGCHILD
     */
    protected function _handleChildSignal()
    {
        $iSubProcessId = pcntl_wait($iWorkerProcessStatus, WNOHANG);
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


    /**
     * Hook signal
     *
     * @param int $iSignal
     * @param callback $oCallback
     */
    protected function _hookSignal($iSignal, $oCallback)
    {
        if (isset($this->_signalEventMapping[$iSignal]))
            return;

        $oEvent = event_new();

        event_set($oEvent, $iSignal, EV_SIGNAL | EV_PERSIST, $oCallback);
        event_base_set($oEvent, $this->_eventBase);
        event_add($oEvent);

        $this->_signalEventMapping[$iSignal] = $oEvent;
    }

    /**
     * Unhook signal
     *
     * @param int $iSignal
     */
    protected function _unhookSignal($iSignal)
    {
        if (!isset($this->_signalEventMapping[$iSignal]))
            return;

        $oEvent = $this->_signalEventMapping[$iSignal];
        unset($this->_signalEventMapping[$iSignal]);

        event_del($oEvent);
        event_free($oEvent);
    }

    /**
     * Unhook all signals
     */
    protected function _unhookAllSignals()
    {
        $a = $this->_signalEventMapping;
        foreach ($a as $signal => $event) {
            $this->_unhookSignal($signal);
        }
    }

    /**
     * Hook signal for main process
     */
    protected function _hookSignalForMainProcess($iSignal)
    {
        $oMe = $this;

        $this->_hookSignal($iSignal, function ($iSignal) use ($oMe) {
            switch ($iSignal) {
                case SIGCHLD:
                    $oMe->_handleChildSignal();
                    break;
                case SIGTERM:
                case SIGQUIT:
                case SIGINT:
                    $oMe->_handleTerminateSignal();
                    break;
            }
        });
    }


    /**
     * Hook signal for child process
     */
    protected function _hookSignalForChildProcess($iSignal)
    {
        $this->_hookSignal($iSignal, function ($iSignal) {
            if($iSignal != SIGTERM
                    && $iSignal != SIGQUIT
                    && $iSignal != SIGINT)
                return;

            if($this->_processExiting)
                return;
            $this->_processExiting = true;
        });
    }

    /** @var callback process function for child process */
    protected $_callback = null;

    /** @var array to save child process id ( pid => pid ) */
    protected $_processMapping = array();

    /** @var int accumulative created child processes since program started */
    protected $_accProcessNum = 0;

    /** @var resource global event base */
    protected $_eventBase = null;

    /** @var boolean whether if process is exiting */
    protected $_processExiting = false;

    /** @var int max processes */
    protected $_maxTotalProcesses = 10000;

    /** @var array to save events related to signals */
    protected $_signalEventMapping = array();
}