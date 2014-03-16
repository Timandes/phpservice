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
        $this->_mainProcess = new MainProcess($oCallback);
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

        return $this->_mainProcess->start($nMaxSubProcesses);
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


    public function setVerbose($iVerbose)
    {
        $this->_mainProcess->setVerbose($iVerbose);
    }


    /** @var callback process function for child process */
    protected $_callback = null;

    protected $_mainProcess = null;
}