<?php
# vim: set ts=4 sts=4 sw=4 expandtab:
# sublime: tab_size 4; translate_tabs_to_spaces true
/**
 * Signal manager
 *
 * @package phpservice
 */

namespace Timandes\CLI;

/**
 * Signal manager
 */
class SignalManager
{
    /**
     * Constructor
     */
    public function __construct($oEventBase)
    {
        $this->_eventBase = $oEventBase;
    }

    /**
     * Hook signal
     *
     * @param int $iSignal
     * @param callback $oCallback
     */
    public function hookSignal($iSignal, $oCallback)
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
    public function unhookSignal($iSignal)
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
    public function unhookAllSignals()
    {
        $a = $this->_signalEventMapping;
        foreach ($a as $signal => $event) {
            $this->unhookSignal($signal);
        }
    }

    /** @var resource global event base */
    protected $_eventBase = null;

    /** @var array to save events related to signals */
    protected $_signalEventMapping = array();
}
