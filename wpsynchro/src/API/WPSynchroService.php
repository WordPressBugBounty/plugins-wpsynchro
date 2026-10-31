<?php

namespace WPSynchro\API;

use WPSynchro\Transport\ReturnResult;
use WPSynchro\Transport\TransferToken;

abstract class WPSynchroService
{
    abstract public function service();

    /**
     *  Get the request body
     */
    public function getRequestBody()
    {
        return file_get_contents('php://input');
    }

    /**
     *  Check that the transfer token on this request was issued for the given direction (push = this site is written to, pull = this site is read from)
     *  Returns false and ends the request with an error, if not
     */
    protected function requireTransferDirection($direction)
    {
        if (TransferToken::transferTypeAllows($_REQUEST['token'] ?? '', $direction)) {
            return;
        }

        $result = new \stdClass();
        $result->errors = [__('This transfer is not allowed to do that - The migration type was not initiated for this operation', 'wpsynchro')];
        $returnresult = new ReturnResult();
        $returnresult->init();
        $returnresult->setHTTPStatus(403);
        $returnresult->setDataObject($result);
        $returnresult->echoDataFromServiceAndExit();
    }
}
