<?php

namespace WPSynchro\Utilities;

use WPSynchro\Utilities\Configuration\PluginConfiguration;

/**
 * Class for handling all the timers for a migration
 *
 */
class SyncTimerList
{
    use SingletonTrait;

    // Over sync timer
    public $overall_sync_timer = null;

    // Max execution constants
    public $php_max_execution_time = null;
    public $sync_max_execution_time = null;

    // Other timers
    public $timers = [];

    // Constants
    const MAX_SYNC_TIME_LIMIT = 30;

    /**
     *  Initialize overall timer and set max execution time for sync and max for PHP
     */
    public function init()
    {
        // Initiate overall timer
        $beginsync = new SyncTimer();
        $beginsync->desc = "Overall sync timer";
        if (defined("WPSYNCHRO_TESTING")) {
            $beginsync->startNow();
        } else {
            $beginsync->setStart($_SERVER["REQUEST_TIME_FLOAT"]);
        }

        $this->overall_sync_timer = $beginsync;

        // Get time limit in seconds (float)
        if (defined("WPSYNCHRO_TESTING")) {
            $this->php_max_execution_time = 30;
        } else {
            $this->php_max_execution_time = intval(ini_get('max_execution_time'));
            // We dont do never ending
            if ($this->php_max_execution_time === 0) {
                $this->php_max_execution_time = 30;
            }
        }
        $this->sync_max_execution_time = $this->getAdjustedTimeLimit($this->php_max_execution_time);
    }

    /**
     *  Adjust the max_execution_time limit given, to adjust for limits and buffers
     */
    public function getAdjustedTimeLimit($timelimit)
    {
        if ($timelimit < 0) {
            return 0;
        }
        if ($timelimit > self::MAX_SYNC_TIME_LIMIT) {
            // We set it to max X seconds
            $timelimit = self::MAX_SYNC_TIME_LIMIT;
        }

        $timelimit = PluginConfiguration::factory()->getOverallTimeMargin() * $timelimit;
        return $timelimit;
    }

    /**
     *  Add another timelimit to the max time - Such as a service needing to take two max_execution_time into account
     */
    public function addOtherSyncTimeLimit($timelimit)
    {
        $adjusted_timelimit = $this->getAdjustedTimeLimit($timelimit);
        $this->sync_max_execution_time = min($this->sync_max_execution_time, $adjusted_timelimit);
    }

    /**
     *  End overall sync and stop all other timers
     */
    public function endSync()
    {
        if ($this->overall_sync_timer != null) {
            $this->overall_sync_timer->endNow();
        }

        foreach ($this->timers as $timer) {
            $timer->endNow();
        }
    }

    /**
     *  Return the current remaining time for this PHP execution
     */
    public function getRemainingSyncTime()
    {
        if ($this->overall_sync_timer == null) {
            // Default if just called outside the normal flow
            return self::MAX_SYNC_TIME_LIMIT * 0.8;
        }
        $remainingtime = $this->sync_max_execution_time - $this->overall_sync_timer->getTimeUntilNow();
        if ($remainingtime < 0) {
            $remainingtime = 0;
        }
        return $remainingtime;
    }

    /**
     *  Get the PHP max execution time
     */
    public function getSyncMaxExecutionTime()
    {
        return $this->sync_max_execution_time;
    }

    /**
     *  Return if task should continue, given that the lastrun of a task was "lastrun_time"
     */
    public function shouldContinueWithLastrunTime($lastrun_time)
    {
        // Increase last run time with 50%, to have a margin
        $lastrun_time = $lastrun_time * 1.5;
        $remainingtime = $this->getRemainingSyncTime();
        if ($lastrun_time >= $remainingtime) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *  Start other timer
     */
    public function startTimer($cat, $subcat = "", $name = "")
    {
        $beginsync = new SyncTimer();
        $beginsync->startNow();
        $beginsync->desc = $cat . "_" . $subcat . "_" . $name;
        $id = uniqid();
        $this->timers[$id] = $beginsync;
        return $id;
    }

    /**
     *  Stop other timer
     */
    public function endTimer($timer_id)
    {
        if (isset($this->timers[$timer_id])) {
            return $this->timers[$timer_id]->endNow();
        }
        return false;
    }

    /**
     *  Get elapsed for other timer
     */
    public function getElapsedTimeToNow($timer_id)
    {
        if (isset($this->timers[$timer_id])) {
            return $this->timers[$timer_id]->getTimeUntilNow();
        } else {
            return false;
        }
    }

    /**
     *  Get elapsed for overall timer
     */
    public function getElapsedOverallTimer()
    {
        return $this->overall_sync_timer->getTimeUntilNow();
    }
}
