<?php
# vim: set ts=4 sts=4 sw=4 expandtab:
# sublime: tab_size 4; translate_tabs_to_spaces true
/**
 * Child process
 *
 * @package phpservice
 */

namespace Timandes\CLI;

/**
 * Child process
 */
class ChildProcess
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


    /**
     * Start child process
     */
    public function start($iInterval = 100)
    {
        $this->_hookSignal(SIGTERM);
        $this->_hookSignal(SIGQUIT);
        $this->_hookSignal(SIGINT);

        $this->_eventTimeout = event_new();
        event_set($this->_eventTimeout, 0, EV_TIMEOUT, array($this, 'timedout'));
        event_base_set($this->_eventTimeout, $this->_eventBase);
        event_add($this->_eventTimeout, $iInterval);

        event_base_loop($this->_eventBase);
    }


    public function timedout()
    {
        if($this->_processExiting) {
            event_base_loopexit($this->_eventBase);
            return;
        }

        call_user_func($this->_callback);
    }


    /**
     * Hook signal for child process
     */
    protected function _hookSignal($iSignal)
    {
        $this->_signalManager->hookSignal($iSignal, array($this, 'signalHandler'));
    }

    /**
     * Signal handler
     */
    public function signalHandler($iSignal) {
        if($iSignal != SIGTERM
                && $iSignal != SIGQUIT
                && $iSignal != SIGINT)
            return;

        if($this->_processExiting)
            return;
        $this->_processExiting = true;
    }

    /** @var resource global event base */
    protected $_eventBase = null;

    /** @var boolean whether if process is exiting */
    protected $_processExiting = false;

    protected $_signalManager = null;

    protected $_eventTimeout = null;
}
