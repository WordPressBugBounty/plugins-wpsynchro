<?php

/**
 * Class for handling service "initate" - Starting a migration
 */

namespace WPSynchro\API;

use WPSynchro\Transport\ReturnResult;
use WPSynchro\Utilities\Compatibility\MUPluginHandler;
use WPSynchro\Transport\TransferToken;
use WPSynchro\Utilities\CommonFunctions;
use WPSynchro\Utilities\Configuration\PluginConfiguration;
use WPSynchro\Utilities\Licensing\Licensing;

class Initiate extends WPSynchroService
{
    public function service()
    {
        $sync_response = new \stdClass();
        $sync_response->errors = [];
        $token_lifespan = 10800;

        $allowed_types = ['push', 'pull', 'local'];
        if (isset($_REQUEST['type']) && in_array($_REQUEST['type'], $allowed_types)) {
            $type = $_REQUEST['type'];
            $exclusive = (($_REQUEST['exclusive'] ?? '0') === '1');

            // Only callers that know the access key can start a transfer
            if (!TransferToken::verifyInitiateProof($type, $exclusive, $_REQUEST['ts'] ?? null, $_REQUEST['nonce'] ?? null, $_REQUEST['proof'] ?? null)) {
                $return_result = new ReturnResult();
                $return_result->init();
                $sync_response->errors[] = __('Authentication failed - Check that the access key is correct and that both sites run the same, updated version of WP Synchro', 'wpsynchro');
                $return_result->setDataObject($sync_response);
                return $return_result->echoDataFromServiceAndExit();
            }

            // Get allowed methods for this site
            $plugin_configuration = new PluginConfiguration();
            $methods_allowed = $plugin_configuration->getAllowedMigrationMethods();

            // Check the type and if it is allowed
            if ($type == 'pull' && !$methods_allowed->pull) {
                $sync_response->errors[] = __('Pulling from this site is not allowed - Change configuration on remote server', 'wpsynchro');
            } elseif ($type == 'push' && !$methods_allowed->push) {
                $sync_response->errors[] = __('Pushing to this site is not allowed - Change configuration on remote server', 'wpsynchro');
            }

            // Check licensing
            if (CommonFunctions::isPremiumVersion()) {
                $licensing = new Licensing();
                $licensecheck = $licensing->verifyLicense();

                if ($licensecheck == false) {
                    $sync_response->errors[] = $licensing->getLicenseErrorMessage();
                }
            }
        } else {
            $sync_response->errors[] = __('Remote host does not allow that - Make sure it is same WP Synchro version', 'wpsynchro');
        }

        if (count($sync_response->errors) > 0) {
            $return_result = new ReturnResult();
            $return_result->init();
            $return_result->setDataObject($sync_response);
            return $return_result->echoDataFromServiceAndExit();
        }

        // Create a new transfer object - Migrations are only allowed one at a time
        $token = TransferToken::setNewToken($token_lifespan, $exclusive, $type);
        if ($token === false) {
            $sync_response->errors[] = __('Another migration is currently running on this site - Wait for it to finish, or abort it, and try again', 'wpsynchro');
            $return_result = new ReturnResult();
            $return_result->init();
            $return_result->setDataObject($sync_response);
            return $return_result->echoDataFromServiceAndExit();
        }
        $sync_response->token = $token;

        // Check if MU plugin needs update
        $muplugin_handler = new MUPluginHandler();
        $muplugin_handler->checkNeedsUpdate();

        // Return
        $return_result = new ReturnResult();
        $return_result->init();
        $return_result->setDataObject($sync_response);
        return $return_result->echoDataFromServiceAndExit();
    }
}
