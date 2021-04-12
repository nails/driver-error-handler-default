<?php

namespace Nails\Common\ErrorHandler;

use Nails\Common\Exception\NailsException;
use Nails\Common\Interfaces\ErrorHandlerDriver;
use Nails\Common\Service\ErrorHandler;
use Nails\Factory;

/**
 * Class DefaultHandler
 *
 * @package Nails\Common\ErrorHandler
 */
class DefaultHandler implements ErrorHandlerDriver
{
    /**
     * Instantiates the driver
     *
     * @return void
     */
    public static function init()
    {
    }

    // --------------------------------------------------------------------------

    /**
     * Called when a PHP error occurs
     *
     * @param int    $iErrorNumber The error number
     * @param string $sErrorString The error message
     * @param string $sErrorFile   The file where the error occurred
     * @param int    $iErrorLine   The line number where the error occurred
     *
     * @return void
     */
    public static function error($iErrorNumber, $sErrorString, $sErrorFile, $iErrorLine)
    {
        //  Don't clog the logs up with strict notices
        if ($iErrorNumber === E_STRICT) {
            return;
        }

        //  Ignore some errors
        $aIgnore = [
            //  The following come from CI and won't be fixed
            'ini_set(): Use of mbstring.internal_encoding is deprecated',
            'ini_set(): Use of iconv.internal_encoding is deprecated'
        ];

        if (in_array($sErrorString, $aIgnore)) {
            return;
        }

        $aData = [
            'iNumber'   => $iErrorNumber,
            'sMessage'  => $sErrorString,
            'sFile'     => $sErrorFile,
            'iLine'     => $iErrorLine,
            'sSeverity' => 'Unknown',
        ];

        //  Should we show this error?
        if ((bool) ini_get('display_errors') && error_reporting() !== 0) {

            /** @var ErrorHandler $oErrorHandler */
            $oErrorHandler = Factory::service('ErrorHandler');

            if (array_key_exists($iErrorNumber, $oErrorHandler::LEVELS)) {
                $aData['sSeverity'] = $oErrorHandler::LEVELS[$iErrorNumber];
            }

            $oErrorHandler->renderErrorView('php', $aData, true);
        }

        //  Show we log the item?
        if (function_exists('config_item') && config_item('log_threshold') != 0) {
            Factory::service('Logger')
                ->line($aData['sMessage'] . ' (' . $aData['sFile'] . ':' . $aData['iLine'] . ')');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Catches uncaught exceptions
     *
     * @param \Exception $oException     The uncaught exception
     * @param bool       $bHaltExecution Whether to show the error screen and halt execution
     *
     * @return void
     */
    public static function exception($oException, $bHaltExecution = true)
    {
        $oDetails = (object) [
            'type' => get_class($oException),
            'code' => $oException->getCode(),
            'msg'  => $oException->getMessage(),
            'file' => $oException->getFile(),
            'line' => $oException->getLine(),
            'url'  => $oException instanceof NailsException ? $oException->getDocumentationUrl() : null,
        ];

        $sSubject = $oDetails->msg;
        $sMessage = sprintf(
            'Uncaught %s Exception: code %s; file: %s, line %s',
            get_class($oException),
            $oDetails->code,
            $oDetails->file,
            $oDetails->line
        );

        //  Show we log the item?
        Factory::service('Logger')
            ->line($sMessage);

        if ($bHaltExecution) {
            /** @var ErrorHandler $oErrorHandler */
            $oErrorHandler = Factory::service('ErrorHandler');
            $oErrorHandler->showFatalErrorScreen($sSubject, $sMessage, $oDetails);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Catches fatal errors on shut down
     *
     * @return void
     */
    public static function fatal()
    {
        $aError = error_get_last();
        if (!is_null($aError) && $aError['type'] === E_ERROR) {

            /** @var ErrorHandler $oErrorHandler */
            $oErrorHandler = Factory::service('ErrorHandler');
            $oErrorHandler
                ->showFatalErrorScreen(
                    'Fatal Error',
                    $aError['message'] . ' in ' . $aError['file'] . ' on line ' . $aError['line'],
                    (object) [
                        'type' => 'Fatal Error',
                        'code' => $aError['type'],
                        'msg'  => $aError['message'],
                        'file' => $aError['file'],
                        'line' => $aError['line'],
                    ]
                );
        }
    }
}
