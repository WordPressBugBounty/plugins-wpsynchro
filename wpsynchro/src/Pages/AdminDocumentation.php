<?php

/**
 * Show documentation page
 */

namespace WPSynchro\Pages;

class AdminDocumentation
{
    public static function render()
    {
        // Data for JS
        $data_for_js = [
            'is_pro' => \WPSynchro\Utilities\CommonFunctions::isPremiumVersion(),
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_documentation', $data_for_js);

        // Print content
        echo '<div id="wpsynchro-documentation" class="wpsynchro"></div>';
    }
}
