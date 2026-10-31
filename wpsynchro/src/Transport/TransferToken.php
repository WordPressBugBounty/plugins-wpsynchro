<?php

namespace WPSynchro\Transport;

use WPSynchro\Transport\TransferAccessKey;

/**
 * Transfer token
 */
class TransferToken
{
    const TOKEN_ALGORITM = "sha256";
    const TOKEN_OPTION_NAME = "wpsynchro_current_transfers";
    const LEGACY_TOKEN_OPTION_NAME = "wpsynchro_current_transfer";
    // Absolute max age of a transfer, no matter the activity
    const MAX_TRANSFER_AGE = 86400;
    const MAX_ACTIVE_TRANSFERS = 20;
    const LOCK_OPTION_NAME = "wpsynchro_transfers_lock";
    const LOCAL_SECRET_OPTION_NAME = "wpsynchro_local_secret";
    // An exclusive transfer (migration) with activity within this many seconds blocks new exclusive ones, unless released
    // Must be longer than the max time between two requests of a running migration (max sync time is 30 seconds)
    const EXCLUSIVE_BUSY_WINDOW = 60;
    // Initiate proof
    const PROOF_MAX_TIME_SKEW = 300;
    const PROOF_NONCE_TRANSIENT_PREFIX = "wpsynchro_init_nonce_";

    /**
     * Get transfer token based on access key and job token
     */
    public static function getTransferToken($accesskey, $jobtoken)
    {
        return hash_hmac(self::TOKEN_ALGORITM, (string) $jobtoken, (string) $accesskey);
    }

    /**
     * Get the active transfers, keyed by job token, with expired ones removed
     */
    private static function getActiveTransfers()
    {
        $transfers = get_option(self::TOKEN_OPTION_NAME, []);
        if (!is_array($transfers)) {
            return [];
        }
        $now = time();
        foreach ($transfers as $jobtoken => $transfer) {
            if (
                !is_object($transfer)
                || $transfer->last_activity <= ($now - $transfer->lifetime)
                || $transfer->created <= ($now - self::MAX_TRANSFER_AGE)
            ) {
                unset($transfers[$jobtoken]);
            }
        }
        return $transfers;
    }

    /**
     * Validate transfer token
     */
    public static function validateTransferToken($token_to_validate)
    {
        if (!is_string($token_to_validate) || strlen($token_to_validate) == 0) {
            return false;
        }

        $accesskey = TransferAccessKey::getAccessKey();
        if (strlen($accesskey) == 0) {
            return false;
        }

        $valid = false;
        $locked = self::acquireLock();
        $transfers = self::getActiveTransfers();
        foreach ($transfers as $jobtoken => $transfer) {
            $expected_transfer_token = self::getTransferToken($accesskey, $jobtoken);
            if (hash_equals($expected_transfer_token, $token_to_validate)) {
                // A released migration (stopped on an error) can be resumed, as long as no other migration has taken over
                if (!empty($transfer->released)) {
                    if (self::hasBusyExclusiveTransfer($transfers, $jobtoken)) {
                        break;
                    }
                    $transfer->released = false;
                }
                $valid = true;
                // Only write activity under lock, to not overwrite concurrent changes
                if ($locked) {
                    $transfer->last_activity = time();
                    update_option(self::TOKEN_OPTION_NAME, $transfers, false);
                }
                break;
            }
        }
        if ($locked) {
            self::releaseLock();
        }

        return $valid;
    }

    /**
     * Check if there is an exclusive transfer, other than the given, that is in progress
     */
    private static function hasBusyExclusiveTransfer($transfers, $except_jobtoken = null)
    {
        $now = time();
        foreach ($transfers as $jobtoken => $transfer) {
            if ($jobtoken === $except_jobtoken || empty($transfer->exclusive) || !empty($transfer->released)) {
                continue;
            }
            if ($transfer->last_activity > $now - self::EXCLUSIVE_BUSY_WINDOW) {
                return true;
            }
        }
        return false;
    }

    /**
     * Release a migration transfer, so a new migration can start right away. The transfer token stays valid, so the migration can be resumed
     */
    public static function releaseTransfer($transfer_token)
    {
        if (!is_string($transfer_token) || strlen($transfer_token) == 0) {
            return;
        }
        $accesskey = TransferAccessKey::getAccessKey();
        $locked = self::acquireLock();
        $transfers = self::getActiveTransfers();
        foreach ($transfers as $jobtoken => $transfer) {
            if (!empty($transfer->exclusive) && hash_equals(self::getTransferToken($accesskey, $jobtoken), $transfer_token)) {
                $transfer->released = true;
                update_option(self::TOKEN_OPTION_NAME, $transfers, false);
                break;
            }
        }
        if ($locked) {
            self::releaseLock();
        }
    }

    /**
     * Lock for changes to the list of transfers, as option read/write is not atomic
     */
    private static function acquireLock()
    {
        $deadline = microtime(true) + 5;
        do {
            if (add_option(self::LOCK_OPTION_NAME, time(), '', false)) {
                return true;
            }
            // Remove lock left behind by a crashed request
            $lock_time = (int) get_option(self::LOCK_OPTION_NAME, 0);
            if ($lock_time > 0 && $lock_time < time() - 10) {
                delete_option(self::LOCK_OPTION_NAME);
                continue;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        return false;
    }

    private static function releaseLock()
    {
        delete_option(self::LOCK_OPTION_NAME);
    }

    /**
     *  Set new token
     *  Exclusive transfers (migrations) are only allowed one at a time, so returns false if another is in progress
     */
    public static function setNewToken($lifespan, $exclusive = false, $type = 'local')
    {
        if (!self::acquireLock()) {
            return false;
        }
        $token = self::addTransfer($lifespan, $exclusive, $type);
        self::releaseLock();
        return $token;
    }

    /**
     * Check if the transfer, that the token belongs to, was initiated for the given direction
     * Push: This site is written to. Pull: This site is read from. Local transfers are this site talking to itself and are allowed both
     */
    public static function transferTypeAllows($transfer_token, $direction)
    {
        if (!is_string($transfer_token) || strlen($transfer_token) == 0) {
            return false;
        }
        $accesskey = TransferAccessKey::getAccessKey();
        foreach (self::getActiveTransfers() as $jobtoken => $transfer) {
            if (hash_equals(self::getTransferToken($accesskey, $jobtoken), $transfer_token)) {
                $type = $transfer->type ?? 'local';
                return $type === 'local' || $type === $direction;
            }
        }
        return false;
    }

    private static function addTransfer($lifespan, $exclusive, $type)
    {
        $transfers = self::getActiveTransfers();

        if ($exclusive) {
            if (self::hasBusyExclusiveTransfer($transfers)) {
                return false;
            }
            // Released or abandoned migrations are replaced by the new one
            foreach ($transfers as $jobtoken => $transfer) {
                if (!empty($transfer->exclusive)) {
                    unset($transfers[$jobtoken]);
                }
            }
        }

        // Make room, by dropping the transfers with oldest activity
        if (count($transfers) >= self::MAX_ACTIVE_TRANSFERS) {
            uasort($transfers, function ($a, $b) {
                return $a->last_activity <=> $b->last_activity;
            });
            $transfers = array_slice($transfers, count($transfers) - self::MAX_ACTIVE_TRANSFERS + 1, null, true);
        }

        $transfer = new \stdClass();
        $transfer->token = hash('sha256', random_bytes(30));
        $transfer->created = time();
        $transfer->last_activity = time();
        $transfer->lifetime = $lifespan;
        $transfer->exclusive = (bool) $exclusive;
        $transfer->type = $type;
        $transfers[$transfer->token] = $transfer;
        update_option(self::TOKEN_OPTION_NAME, $transfers, false);
        delete_option(self::LEGACY_TOKEN_OPTION_NAME);
        return $transfer->token;
    }

    /**
     * Delete transfer token - Only the one matching the given transfer token, or all if none given
     */
    public static function deleteTransferToken($transfer_token = null)
    {
        if (!is_string($transfer_token) || strlen($transfer_token) == 0) {
            delete_option(self::TOKEN_OPTION_NAME);
            delete_option(self::LEGACY_TOKEN_OPTION_NAME);
            return;
        }

        $accesskey = TransferAccessKey::getAccessKey();
        $locked = self::acquireLock();
        $transfers = self::getActiveTransfers();
        foreach ($transfers as $jobtoken => $transfer) {
            if (hash_equals(self::getTransferToken($accesskey, $jobtoken), $transfer_token)) {
                unset($transfers[$jobtoken]);
            }
        }
        update_option(self::TOKEN_OPTION_NAME, $transfers, false);
        if ($locked) {
            self::releaseLock();
        }
    }

    /**
     * Get the secret only this site knows, used to prove that a "local" initiate comes from this site itself
     * The access key is shared with other sites, so it can not be used for that
     */
    private static function getLocalSecret()
    {
        $secret = get_option(self::LOCAL_SECRET_OPTION_NAME, '');
        if (!is_string($secret) || strlen($secret) < 32) {
            // add_option does not overwrite, so concurrent requests end up with the same secret
            add_option(self::LOCAL_SECRET_OPTION_NAME, bin2hex(random_bytes(32)), '', false);
            $secret = (string) get_option(self::LOCAL_SECRET_OPTION_NAME, '');
        }
        return $secret;
    }

    /**
     * Compute proof of key knowledge for the initiate request
     * Local initiates are proven with the local secret and all others with the access key
     */
    private static function computeInitiateProof($accesskey, $type, $exclusive, $timestamp, $nonce)
    {
        $key = ($type === 'local') ? self::getLocalSecret() : (string) $accesskey;
        return hash_hmac(self::TOKEN_ALGORITM, "initiate|" . $type . "|" . ($exclusive ? '1' : '0') . "|" . $timestamp . "|" . $nonce, $key);
    }

    /**
     * Get the query parameters that proves knowledge of the access key, for the initiate request
     * Exclusive is for actual migrations, which only one can run at a time
     */
    public static function getInitiateProofParameters($accesskey, $type, $exclusive = false)
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        return [
            'exclusive' => $exclusive ? '1' : '0',
            'ts' => $timestamp,
            'nonce' => $nonce,
            'proof' => self::computeInitiateProof($accesskey, $type, $exclusive, $timestamp, $nonce),
        ];
    }

    /**
     * Verify the proof on an initiate request. Each proof can only be used once
     */
    public static function verifyInitiateProof($type, $exclusive, $timestamp, $nonce, $proof)
    {
        $accesskey = TransferAccessKey::getAccessKey();
        if ($type !== 'local' && strlen($accesskey) == 0) {
            return false;
        }
        if (!is_scalar($timestamp) || !is_scalar($nonce) || !is_scalar($proof)) {
            return false;
        }
        $timestamp = (string) $timestamp;
        $nonce = (string) $nonce;
        $proof = (string) $proof;
        if (!ctype_digit($timestamp) || !preg_match('/^[a-f0-9]{16,64}$/', $nonce)) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > self::PROOF_MAX_TIME_SKEW) {
            return false;
        }

        $expected = self::computeInitiateProof($accesskey, $type, $exclusive, $timestamp, $nonce);
        if (!hash_equals($expected, $proof)) {
            return false;
        }

        // Replay protection
        $transient_name = self::PROOF_NONCE_TRANSIENT_PREFIX . $nonce;
        if (get_transient($transient_name) !== false) {
            return false;
        }
        set_transient($transient_name, 1, self::PROOF_MAX_TIME_SKEW * 2);

        return true;
    }
}
