<?php

namespace MyFatoorah\Gateway\Cron;

class BackupLogFiles
{
    //---------------------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Create a backup file for the log every week
     *
     * @return boolean
     */
    public function createNewLogFile()
    {

        $logPath = BP . '/var/log';

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        if (file_exists(MYFATOORAH_LOG_FILE)) {
            $mfOldLog = "$logPath/mfOldLog";
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            if (!file_exists($mfOldLog)) {
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                mkdir($mfOldLog);
            }

            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            rename(MYFATOORAH_LOG_FILE, "$mfOldLog/myfatoorah_" . date('Y-m-d') . '.log');
        }
        return true;
    }

    //---------------------------------------------------------------------------------------------------------------------------------------------------
}
