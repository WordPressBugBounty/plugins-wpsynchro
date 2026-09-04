<?php

/**
 * Save migration
 */

namespace WPSynchro\API;

use WPSynchro\Migration\Migration;
use WPSynchro\Migration\MigrationFactory;
use WPSynchro\Schedule\ScheduleFactory;
use WPSynchro\Utilities\CommonFunctions;
use WPSynchro\Files\Location;

class SaveMigration extends WPSynchroService
{
    public function service()
    {
        $nonce = $_GET['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wpsynchro-addedit')) {
            \http_response_code(401);
            return;
        }

        // Extract parameters
        $body = $this->getRequestBody();
        $parameters = json_decode($body);

        // If json is invalid for some reason
        if (is_null($parameters)) {
            \http_response_code(400);
            return;
        }

        // We can receive migration data from the wizard or from the advanced add/edit page, so we need to map it to a Migration object
        if (isset($parameters->type) && $parameters->type === "simple") {
            // Simple migration from the wizard
            $migration = $this->mapFromWizard($parameters);
        } else {
            // Advanced migration from the add/edit page
            $migration = Migration::map($parameters);
        }


        if (strlen($migration->id) == 0) {
            $migration->id = uniqid();
        }

        // Sanitize
        $migration->sanitize();

        // Save
        $migration_factory = MigrationFactory::getInstance();
        $migration_factory->addMigration($migration);


        // Update all the scheduled migrations according to our migrations
        if (CommonFunctions::isPremiumVersion()) {
            $all_migrations = $migration_factory->getAllMigrations();
            $schedule_factory = ScheduleFactory::getInstance();
            $schedule_factory->synchronizeWithMigrations($all_migrations);
        }

        // Return the sanitized version
        echo json_encode($migration);
    }

    private function mapFromWizard(object $parameters): Migration
    {
        $migration = new Migration();
        $migration->sync_preset = "none";

        $generated_name = __("Migrate from %s", "wpsynchro");
        if ($parameters->migrate_type === "push") {
            $generated_name = __("Migrate to %s", "wpsynchro");
        }
        $generated_name = sprintf($generated_name, $parameters->remote_server_url);

        $migration->name = $generated_name;
        $migration->site_url = $parameters->remote_server_url;
        $migration->access_key = $parameters->remote_server_key;
        $migration->sync_database = $parameters->migrate_db;
        $migration->searchreplaces_regenerate = true;
        $migration->sync_files = $parameters->migrate_files;
        $migration->files_ask_user_for_confirm = true;
        $migration->type = $parameters->migrate_type;
        $migration->db_make_backup = $parameters->backup_db;

        // If migrate files is done, we add the web root as a location
        if ($migration->sync_files) {
            $loc = new Location();
            $loc->base = "webroot";
            $loc->path = "/";
            $migration->file_locations[] = $loc;
        }

        return $migration;
    }
}
