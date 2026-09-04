<?php

/**
 * Class for providing data for onboarding help component
 */

namespace WPSynchro\Utilities\JSData;

class OnboardingData
{
    /**
     *  Load the JS data for onboarding help component
     */
    public function load()
    {
        $onboarding_localize = [
            'introtext' => __('Need help getting started with WP Synchro?', 'wpsynchro'),
            'text1' => __('Let us show you how to get started quickly and understand how WP Synchro works.', 'wpsynchro'),
            'is_pro' => \WPSynchro\Utilities\CommonFunctions::isPremiumVersion(),
            'accept' => __('Show me', 'wpsynchro'),
            'decline' => __('Dismiss', 'wpsynchro'),
        ];
        wp_localize_script('wpsynchro_admin_js', 'wpsynchro_onboarding_help', $onboarding_localize);
    }
}
