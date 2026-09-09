<?php

namespace WPSynchro\Database;

use WPSynchro\Database\DatabaseHelperFunctions;
use WPSynchro\Logger\LoggerFactory;
use WPSynchro\Logger\LoggerInterface;
use WPSynchro\Migration\MigrationController;
use WPSynchro\Transport\Destination;
use WPSynchro\Transport\RemoteTransport;
use WPSynchro\Utilities\SyncTimerList;

/**
 * Class for handling database backup
 */
class DatabaseBackup
{
    // Data objects
    public $job = null;
    public $migration = null;
    public $databasesync = null;
    // Dependencies
    public $logger = null;
    public $timer = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->databasesync = new DatabaseSync();
        $this->logger = MigrationController::getInstance()->getLogger();
        $this->timer = SyncTimerList::getInstance();
    }

    /**
     *  Backup database on target
     */
    public function backupDatabase(&$migration, &$job)
    {
        $this->migration = &$migration;
        $this->job = &$job;

        $this->logger->log("INFO", "Starting database backup loop with remaining time: " . $this->timer->getRemainingSyncTime());

        // Make sure we have a list of tables to do
        if ($this->job->db_backup_tables == null) {
            $this->job->db_backup_tables = $this->createListOfTablesToBackup();
        }

        if (count($this->job->db_backup_tables) == 0) {
            // update progress
            $this->updateProgress();
            return;
        }

        // Create destination, we always want to backup the target
        $destination = new Destination(Destination::TARGET);

        // Do a work chunk on remote end
        $lastrun_time = 2; // init for 3 seconds
        foreach ($this->job->db_backup_tables as &$table) {
            if ($table->is_completed) {
                continue;
            }

            while (!$table->is_completed) {
                // Check if we should abort
                if (!$this->timer->shouldContinueWithLastrunTime($lastrun_time)) {
                    $this->logger->log("INFO", "Ending database backup loop due to time constraint with remaining time: " . $this->timer->getRemainingSyncTime());
                    return;
                }

                $lastrun_timer = $this->timer->startTimer("databasebackup", "while", "lastrun");

                // Set temp name on table to the same as table, as we are not renaming anything
                $table->temp_name = $table->name;

                // Send table to be dumped on remote
                $url = $destination->getFullURL() . "?action=wpsynchro_backupdatabase";
                $body = new \stdClass();
                $body->table = $table;
                $body->filename = "database_backup_" . $this->job->id . ".sql";
                $body->memory_limit = intval($this->job->to_memory_limit * 0.7);
                $body->time_limit = intval($this->timer->getRemainingSyncTime() / 2);

                $this->logger->log('DEBUG', 'Getting data from remote DB with data: ' . json_encode($body));

                // Get remote transfer object
                $remotetransport = new RemoteTransport();
                $remotetransport->setDestination($destination);
                $remotetransport->setJob($job);
                $remotetransport->init();
                $remotetransport->setUrl($url);
                $remotetransport->setDataObject($body);
                $databasebackup_result = $remotetransport->remotePOST();

                if ($databasebackup_result->isSuccess()) {
                    $result_body = $databasebackup_result->getBody();
                    $this->logger->log('DEBUG', 'Got response with content: ' . json_encode($result_body));
                    $result_table = $result_body->table;

                    // Copy data
                    $table->completed_rows = $result_table->completed_rows;
                    $table->last_primary_key = $result_table->last_primary_key;

                    // Check for completion
                    if (!$result_body->has_more_rows_in_table) {
                        $table->completed_rows = $table->rows;
                        $table->is_completed = true;
                    }
                } else {
                    $this->job->errors[] = __("Database backup Service responded with error, which means we can not continue the migration.", "wpsynchro");
                }

                // update progress
                $this->updateProgress();

                // Timings
                $remainingtime = $this->timer->getRemainingSyncTime();
                $lastrun_time = $this->timer->endTimer($lastrun_timer);
                $this->logger->log("DEBUG", sprintf("Backup table %s with rows %d / %d with remainingtime %s and lastrun %s", $table->name, $table->completed_rows, $table->rows, $remainingtime, $lastrun_time));

                // Check if we should abort
                if (!$this->timer->shouldContinueWithLastrunTime($lastrun_time)) {
                    $this->logger->log("INFO", "Ending database backup loop due to time constraint with remaining time: " . $this->timer->getRemainingSyncTime());
                    return;
                }
            }
        }

        $this->logger->log("INFO", "Ending database backup loop with remaining time: " . $this->timer->getRemainingSyncTime());
    }

    /**
     *  Create list of tables to backup
     */
    public function createListOfTablesToBackup()
    {

        $tables_to_backup = [];

        // Generate list of tables on target, that is not temp tables, aka possible list to backup
        $target_tables = [];
        foreach ($this->job->to_dbmasterdata as $table) {
            if (strpos($table->name, DatabaseSync::TMP_TABLE_PREFIX) === 0) {
                continue;
            }

            $table->is_completed = false;
            $target_tables[] = $table;
        }

        // Go through the database tables from the source and create a lookup
        $source_lookup = [];
        foreach ($this->job->from_dbmasterdata as $table) {
            $changed_prefix = DatabaseHelperFunctions::handleTablePrefixChange($table->name, $this->job->from_wpdb_prefix, $this->job->to_wpdb_prefix);
            $source_lookup[$changed_prefix] = true;
        }



        if ($this->migration->include_all_database_tables) {
            $tables_to_backup =  $target_tables;
        } else {
            // Generate the list of tables from source that will be moved
            $onlyinclude = $this->migration->only_include_database_table_names;
            $includes_with_prefix_changed = [];
            foreach ($onlyinclude as $tableinc) {
                $includes_with_prefix_changed[] = DatabaseHelperFunctions::handleTablePrefixChange($tableinc, $this->job->from_wpdb_prefix, $this->job->to_wpdb_prefix);
            }

            // Check which target tables should be included
            foreach ($target_tables as $table) {
                if (in_array($table->name, $includes_with_prefix_changed)) {
                    $tables_to_backup[] = $table;
                }
            }
        }

        return $tables_to_backup;
    }

    /**
     *  Create list of tables to backup
     */
    public function updateProgress()
    {
        $totalrows = 0;
        $completed_rows = 0;
        $completion_percent = 0;
        foreach ($this->job->db_backup_tables as $table) {
            $totalrows += $table->rows;
            $completed_rows += $table->completed_rows;
        }

        if ($totalrows == 0) {
            $this->job->database_backup_progress = 100;
            $this->job->database_backup_completed = true;
            $this->job->database_backup_progress_description = "";
            $this->job->save();
            return;
        }

        if ($completed_rows > 0) {
            $completion_percent = intval(($completed_rows / $totalrows) * 100);
            if ($completion_percent > 100) {
                $completion_percent = 100;
            }
        }

        $completed_rows = number_format_i18n($completed_rows, 0);
        $totalrows = number_format_i18n($totalrows, 0);

        if ($completion_percent == 100) {
            $this->job->database_backup_completed = true;
        }
        $this->job->database_backup_progress = $completion_percent;

        if ($completion_percent < 100) {
            $this->job->database_backup_progress_description = sprintf(__("Row %s / %s", "wpsynchro"), $completed_rows, $totalrows);
        } else {
            $this->job->database_backup_progress_description = "";
        }
        $this->job->save();
    }
}
