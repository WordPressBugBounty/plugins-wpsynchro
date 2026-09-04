<?php

/**
 * Represent a migration
 */

namespace WPSynchro\Migration;

use WPSynchro\Schedule\ScheduledMigration;

class Migration
{
    public string $id = '';
    public string $name = '';
    // Type
    public string $type = '';
    // From
    public string $site_url = '';
    public string $access_key = '';
    // Connection options
    public string $connection_type = "direct";
    public string $basic_auth_username = "";
    public string $basic_auth_password = "";
    // General settings
    public bool $verify_ssl = true;
    public bool $clear_cache_on_success = true;
    public string $success_notification_email_list = "";
    public string $error_notification_email_list = "";
    // Data to sync
    public string $sync_preset = "all";
    public bool $sync_database = false;
    public bool $sync_files = false;
    // Scheduling
    public string $schedule_interval = '';
    /*
     * Database
     */
    public bool $db_make_backup = true;
    public bool $db_table_prefix_change = true;
    // Exclusions DB
    public bool $include_all_database_tables = true;
    public array $only_include_database_table_names = [];
    // Search / replaces in db
    public bool $searchreplaces_regenerate = true;
    public array $searchreplaces = [];
    public bool $ignore_all_search_replaces = false;
    // Preserve wp_options keys
    public array $db_preserve_options_table_keys = [
        'active_plugins',
        'blog_public',
    ];
    public string $db_preserve_options_custom = "";

    /*
     *  Files
     */
    public array $file_locations = [];
    public string $files_exclude_files_match = "node_modules,.DS_Store,.git";
    public bool $files_ask_user_for_confirm = false;

    /*
     * Errors
     */
    public array $validate_errors = [];

    /**
     *  Generated content
     */
    public ?string $description = null;
    public bool $can_run = false;

    // Constants
    const SYNC_TYPES = ['pull', 'push'];
    const CONNECTION_TYPES = ['direct', 'basicauth'];
    const SYNC_PRESETS = ['all', 'db_all', 'file_all', 'none'];

    public function __construct()
    {
    }

    /**
     *  Prepare generated data on object
     */
    public function prepareGeneratedData()
    {
        $this->getOverviewDescription();
        $this->can_run = $this->canRun();
    }

    /**
     *  Get text to show on overview for this migration
     */
    public function getOverviewDescription()
    {
        $this->checkAndUpdateToPreset();

        $desc = '<b>' . __("Migrate", "wpsynchro") . " ";
        // Type
        if ($this->type == 'push') {
            // translators: %s is a site url
            $desc .= sprintf(__("from this site to %s ", "wpsynchro"), $this->site_url) . " ";
        } else {
            // translators: %s is a site url
            $desc .= sprintf(__("from %s to this site", "wpsynchro"), $this->site_url) . " ";
        }
        $desc .= '</b><br><br>';


        $desc .= '<b>' . __("Migration options", 'wpsynchro') . ':</b><br>';


        $desc .= __("Type", 'wpsynchro') . ': ';
        if ($this->sync_preset == 'all') {
            $desc .= __("Migrate entire site (database and files)", "wpsynchro");
        } elseif ($this->sync_preset == 'db_all') {
            $desc .= __("Migrate entire database", "wpsynchro");
        } elseif ($this->sync_preset == 'file_all') {
            $desc .= __("Migrate all files", "wpsynchro");
        } elseif ($this->sync_preset == 'none') {
            if (!$this->sync_database && !$this->sync_files) {
                $desc .= __("Custom migration - But no data chosen for migration", "wpsynchro");
            } else {
                $desc .= __("Custom migration", "wpsynchro");
            }
        }
        $desc .= '<br>';

        if (!$this->verify_ssl) {
            $desc .= __("Self-signed and non-valid SSL certificates allowed", "wpsynchro") . '<br>';
        }

        if ($this->schedule_interval !== '') {
            $scheduled_migration = new ScheduledMigration();
            $desc .= __("Cron:", "wpsynchro") . ' ' . $scheduled_migration->getPrettySchedule($this->schedule_interval) . '<br>';
        }

        if ($this->connection_type == 'basicauth') {
            $desc .= __("Using basic authentication connection with user", "wpsynchro") . " '" .  $this->basic_auth_username . "'<br>";
        }

        if ($this->sync_database) {
            if ($this->db_make_backup) {
                $desc .= "<br><b>" . __("Database migration (including backup)", "wpsynchro") . ':</b><br>';
            } else {
                $desc .= "<br><b>" . __("Database migration", "wpsynchro") . ':</b><br>';
            }

            if ($this->include_all_database_tables) {
                $desc .= __("All database tables", "wpsynchro");
            } else {
                if (count($this->only_include_database_table_names) == 1) {
                    $desc .= $this->only_include_database_table_names[0];
                } elseif (count($this->only_include_database_table_names) > 5) {
                    $rest_count = count($this->only_include_database_table_names) - 5;
                    if ($rest_count > 0) {
                        $sliced_array = array_slice($this->only_include_database_table_names, 0, 5);
                        $sliced_array_rest = array_slice($this->only_include_database_table_names, 5);
                        $desc .= implode(', ', $sliced_array) . ' <span title="' . implode(', ', $sliced_array_rest) . '">+ ' . $rest_count . ' ' . __('more', 'wpsynchro') . '</span>';
                    } else {
                        $desc .= implode(', ', $this->only_include_database_table_names);
                    }
                } else {
                    $desc .= implode(', ', $this->only_include_database_table_names);
                }
            }
            $desc .= '<br>';
        }


        if ($this->sync_files) {
            if ($this->files_ask_user_for_confirm) {
                $desc .= "<br><b>" . __("File migration dirs/files (will ask for user confirmation)", "wpsynchro") . ':</b><br>';
            } else {
                $desc .= "<br><b>" . __("File migration dirs/files (will NOT ask for user confirmation)", "wpsynchro") . ':</b><br>';
            }

            if (count($this->file_locations) > 0) {
                foreach ($this->file_locations as $file_location) {
                    $desc .= '&lt;' . $file_location->base . '&gt;' . $file_location->path . '<br>';
                }
            } else {
                if ($this->sync_preset == 'all' || $this->sync_preset == 'file_all') {
                    $desc .= __("All files inside web root (except WordPress core files)", "wpsynchro") . '<br>';
                } else {
                    $desc .= __("No locations chosen for migration", "wpsynchro") . '<br>';
                }
            }
        }

        // check for errors
        $errors = $this->checkErrors();
        if (count($errors) > 0) {
            $desc .= "<br><br>";
            foreach ($errors as $error) {
                $desc .= "<b style='color:red;'>" . $error . "</b><br>";
            }
        }

        $this->description = $desc;
        return $desc;
    }

    /**
     *  Check for errors, also taking pro/free into account
     */
    public function checkErrors()
    {
        $errors = [];
        $ispro = \WPSynchro\Utilities\CommonFunctions::isPremiumVersion();

        if (!$ispro && ($this->sync_preset == "all" || $this->sync_preset == "files" || $this->sync_files == true)) {
            $errors[] = __("File migration is only available in PRO version", "wpsynchro");
        }

        return $errors;
    }

    /**
     *  Check if migration can run, taking PRO/FREE and functionalities into account
     */
    public function canRun()
    {
        $errors = $this->checkErrors();
        if (count($errors) > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *  Check if a preset is chosen and change the object accordingly
     */
    public function checkAndUpdateToPreset()
    {
        // Create a base migration, to fetch some stuff from
        $migration_clean = new Migration();

        // Is PRO version
        $is_pro = \WPSynchro\Utilities\CommonFunctions::isPremiumVersion();

        // Adjust settings to the correct ones
        if ($this->sync_preset == 'all') {
            // DB
            $this->sync_database = true;
            $this->db_make_backup = true;
            $this->searchreplaces_regenerate = true;
            $this->db_table_prefix_change = true;
            $this->db_preserve_options_table_keys = $migration_clean->db_preserve_options_table_keys;
            $this->include_all_database_tables = true;
            $this->only_include_database_table_names = [];
            // Files
            $this->sync_files = true;
            $this->file_locations = [];
            $this->files_ask_user_for_confirm = false;
            $this->files_exclude_files_match = "";
        } elseif ($this->sync_preset == 'db_all') {
            // DB
            $this->sync_database = true;
            $this->db_make_backup = true;
            $this->searchreplaces_regenerate = true;
            $this->db_table_prefix_change = true;
            $this->db_preserve_options_table_keys = $migration_clean->db_preserve_options_table_keys;
            $this->include_all_database_tables = true;
            $this->only_include_database_table_names = [];
            // Files
            $this->sync_files = false;
        } elseif ($this->sync_preset == 'file_all') {
            // DB
            $this->sync_database = false;
            $this->db_make_backup = false;
            $this->db_table_prefix_change = false;
            // Files
            $this->sync_files = true;
            $this->file_locations = [];
            $this->files_ask_user_for_confirm = false;
            $this->files_exclude_files_match = "";
        } elseif ($this->sync_preset == 'none') {
        }

        if (!$is_pro) {
            $this->schedule_interval = '';
            $this->sync_files = false;
            $this->success_notification_email_list = "";
            $this->error_notification_email_list = "";
            $this->connection_type = "direct";
            $this->basic_auth_username = "";
            $this->basic_auth_password = "";
        }
    }

    /**
     *  Map function
     */
    public static function map(object $obj)
    {
        $temp_migration = new self();
        if (is_object($obj)) {
            foreach ($obj as $key => $value) {
                $temp_migration->$key = $value;
            }
        }

        return $temp_migration;
    }

    /**
     *  Get success email list
     */
    public function getSuccessEmailList()
    {
        return $this->getEmailList("success");
    }

    /**
     *  Get failure email list
     */
    public function getFailureEmailList()
    {
        return $this->getEmailList("failure");
    }

    /**
     *  Retrieve a list of emails from a field
     */
    private function getEmailList(string $type)
    {
        // Get data
        $data = "";
        if ($type === "success") {
            $data = $this->success_notification_email_list;
        } elseif ($type === "failure") {
            $data = $this->error_notification_email_list;
        }

        // Go through list
        $exploded_list = explode(";", $data);
        $emails = [];
        foreach ($exploded_list as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }
        return $emails;
    }

    /**
     *  Remove preserve wp_options database key
     */
    public function removePreserveOptionsKey(string $key)
    {
        if (($key = array_search($key, $this->db_preserve_options_table_keys)) !== false) {
            unset($this->db_preserve_options_table_keys[$key]);
        }
    }

    /**
     *  Remove preserve wp_options database key
     */
    public function setPreserveOptionsKey(string $key)
    {
        if (!in_array($key, $this->db_preserve_options_table_keys)) {
            $this->db_preserve_options_table_keys[] = $key;
        }
        return true;
    }

    /**
     *  Sanitize data values
     */
    public function sanitize()
    {
        $this->name = sanitize_text_field(trim($this->name));
        $this->type = sanitize_text_field(trim($this->type));
        $this->site_url = sanitize_text_field(trim($this->site_url, ',/\\ '));
        $this->access_key = sanitize_text_field(trim($this->access_key));
        $this->connection_type = sanitize_text_field(trim($this->connection_type));
        $this->basic_auth_username = sanitize_text_field(trim($this->basic_auth_username));
        $this->basic_auth_password = sanitize_text_field(trim($this->basic_auth_password));

        // General settings
        $this->success_notification_email_list = sanitize_text_field(trim($this->success_notification_email_list));
        $this->error_notification_email_list = sanitize_text_field(trim($this->error_notification_email_list));

        // Data to migrate
        $this->sync_preset = sanitize_text_field(trim($this->sync_preset));

        // Database
        $this->db_preserve_options_custom = sanitize_text_field(trim($this->db_preserve_options_custom));

        foreach ($this->searchreplaces as $search_replace) {
            $search_replace->from = trim($search_replace->from);
            $search_replace->to = trim($search_replace->to);
        }

        // Files
        $this->files_exclude_files_match = sanitize_text_field(trim($this->files_exclude_files_match));
    }

    /**
     *  Handle cron/async migration environment
     */
    public function setAsynCronMode()
    {
        $this->files_ask_user_for_confirm = false;
    }
}
