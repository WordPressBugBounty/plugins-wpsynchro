<?php

namespace WPSynchro\Database;

/**
 * Database helper functions
 */
class DatabaseHelperFunctions
{
    /**
     *  Handle table prefix name changes, if needed
     */
    public static function handleTablePrefixChange($table_name, $source_prefix, $target_prefix)
    {

        // Check if we need to change prefixes
        if ($source_prefix != $target_prefix) {
            if (substr($table_name, 0, strlen($source_prefix)) == $source_prefix) {
                $table_name = substr($table_name, strlen($source_prefix));
                $table_name = $target_prefix . $table_name;
            }
        }
        return $table_name;
    }

    /**
     *  Check if specific table is being moved, by search for table name ends with X
     */
    public static function isTableBeingTransferred($tablelist, $table_prefix, $table_ends_with)
    {
        foreach ($tablelist as $table) {
            $tablename_with_prefix = str_replace($table_prefix, "", $table->name);
            if ($tablename_with_prefix === $table_ends_with) {
                return true;
            }
        }
        return false;
    }

    /**
     *  Get last db query error
     */
    public function getLastDBQueryErrors()
    {
        global $wpdb;
        $log_errors = [];
        $user_errors = [];

        // Check what error we have
        $base_error = sprintf(
            __('Migration aborted, due to a SQL query failing. See WP Synchro log (found in menu "Logs") for specific information about the query that failed. The specific error from database server was: "%s".', 'wpsynchro'),
            $wpdb->last_error
        );
        if (strpos($wpdb->last_error, 'Specified key was too long') !== false) {
            // Too long key
            $user_errors[] = $base_error . " " . __('That means that the key was longer than supported on the target database. The table need to be fixed or excluded from migration. See documentation for further help.', 'wpsynchro');
        } elseif (strpos($wpdb->last_error, 'Unknown collation') !== false) {
            // Not supported collation/charset
            $user_errors[] = $base_error . " " . __('That means that the charset/collation used is not supported by the target database engine. The table charset/collations needs to be changed into a supported charset/collation for the target database or excluded from migration. See documentation for further help.', 'wpsynchro');
        } elseif (strpos($wpdb->last_query, 'CREATE VIEW') === 0) {
            // Could not create view. Typically, because the required other tables are not there
            $user_errors[] = $base_error . " " . __('The error was caused by trying to create a view in the database. The error is normally thrown from the database server, when the view references tables that do not exist on the target database, so make sure they are there.', 'wpsynchro');
        } else {
            // General error message
            $user_errors[] = $base_error . " " . __('If you need help, contact WP Synchro support.', 'wpsynchro');
        }

        // Logging for log files
        $log_errors[] = "SQL query failed execution: " . $wpdb->last_query;
        $log_errors[] = "WPDB last error: " . $wpdb->last_error;

        return [
            'log_errors' => $log_errors,
            'user_errors' => $user_errors,
        ];
    }

    /**
     *  Get data from local DB, with a certain primary key and max response size
     */
    public function getDataFromDB($table, $column_names, $primary_key_column, $last_primary_key, $completed_rows, $max_response_size, $default_rows_per_request, $time_limit_in_seconds)
    {
        global $wpdb;
        $data = [];
        $has_more_rows_in_table = true;
        $errors = [];
        $current_response_size = 0;
        $is_using_primary_key = strlen($primary_key_column) > 0;
        $start_time = microtime(true);

        while ($current_response_size < $max_response_size) {
            $rows_to_fetch = $default_rows_per_request;

            if ($is_using_primary_key) {
                $sql_stmt = $wpdb->prepare(
                    "SELECT * FROM `$table` WHERE `$primary_key_column` > %s ORDER BY `$primary_key_column` ASC LIMIT %d",
                    $last_primary_key,
                    $rows_to_fetch
                );
            } else {
                $sql_stmt = "SELECT * FROM `$table` LIMIT $completed_rows, $rows_to_fetch";
            }

            $sql_result = $wpdb->get_results($sql_stmt);

            if (!empty($wpdb->last_error)) {
                $errors[] = $wpdb->last_error;
                $wpdb->last_error = '';
            }

            if (empty($sql_result)) {
                $has_more_rows_in_table = false;
                break;
            }

            foreach ($sql_result as $data_row) {
                // Estimate row size in PHP
                $row_size = 0;
                foreach ($column_names as $col) {
                    if (isset($data_row->$col) && $data_row->$col !== null) {
                        $row_size += strlen((string)$data_row->$col);
                    }
                }

                // Always include the first row, even if it exceeds the max_response_size
                if (empty($data)) {
                    $current_response_size += $row_size;
                    if ($is_using_primary_key) {
                        $last_primary_key = $data_row->$primary_key_column;
                    } else {
                        $completed_rows += 1;
                    }
                    $data[] = $data_row;
                    continue;
                }

                // For subsequent rows, check if adding would exceed the max_response_size
                if ($current_response_size + $row_size > $max_response_size) {
                    $has_more_rows_in_table = true;
                    break 2; // Stop fetching more rows
                }

                $current_response_size += $row_size;

                if ($is_using_primary_key) {
                    $last_primary_key = $data_row->$primary_key_column;
                } else {
                    $completed_rows += 1;
                }

                $data[] = $data_row;
            }

            // Check time limit
            if ((microtime(true) - $start_time) > $time_limit_in_seconds) {
                break;
            }

            // If less than requested rows returned, no more data
            if (count($sql_result) < $rows_to_fetch) {
                $has_more_rows_in_table = false;
                break;
            }
        }

        return (object) [
            'data' => $data,
            'has_more_rows_in_table' => $has_more_rows_in_table,
            'errors' => $errors
        ];
    }
}
