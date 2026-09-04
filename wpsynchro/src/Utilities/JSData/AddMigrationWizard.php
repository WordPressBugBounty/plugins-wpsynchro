<?php

/**
 * Provide data for migration add wizard
 */

namespace WPSynchro\Utilities\JSData;

class AddMigrationWizard
{
    /**
     *  Load the JS data
     */
    public function load()
    {
        $data = [
            'add_migration_nonce' => wp_create_nonce('wpsynchro-addedit'),
            'is_pro' => \WPSynchro\Utilities\CommonFunctions::isPremiumVersion(),
            'home_url' => trailingslashit(get_home_url()),
            'advanced_add_edit_url' => admin_url('admin.php?page=wpsynchro_addedit'),

        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_add_migration_wizard', $data);
    }
}
