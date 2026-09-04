<?php

/**
 * Class for providing data for usage reporting component
 */

namespace WPSynchro\Utilities\JSData;

class UsageReportingData
{
    /**
     *  Load the JS data for Usage reporting component
     */
    public function load()
    {
        $usage_reporting_localize = [
            'introtext' => __('Help us understand how you use WP Synchro', 'wpsynchro'),
            'text1' => __('Would you like to allow us to send fully anonymized usage data to our server to help improve WP Synchro?<br>No personal data is collected.', 'wpsynchro'),
            'accept' => __('I accept', 'wpsynchro'),
            'decline' => __('No thanks', 'wpsynchro'),

        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_usage_reporting', $usage_reporting_localize);
    }
}
