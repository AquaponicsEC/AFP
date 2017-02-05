<?php

/**
 * Functions to log system events
 */
define('SYSLOG_LVL_CRITICAL', 1);
define('SYSLOG_LVL_ERROR', 2);
define('SYSLOG_LVL_WARNING', 3);
define('SYSLOG_LVL_INFO', 4);
define('SYSLOG_LVL_VERBOSE', 5);

function syslogWriteEvent($logDbConnStr, $logEvent, $logDbname = null) {
    /*
     * Suggested Levels:
     * 1: Critical
     * 2: Error
     * 3: Warning
     * 4: Information
     * 5: Verbose
     */

    /**
     * Sample $logEvent structure
      $logEvent = [
      'user' => '',
      'event' => ''
      ];
     */
    if (empty($logDbConnStr))
        return false;

    // Check if source fields have been provided
    $requiredFields = ['event'];
    foreach ($requiredFields as $key):
        if (!isset($logEvent[$key])):
            throw new Exception('no_' . $key);
        endif;
    endforeach;


    // Set log_date if not provided
    if (!isset($logEvent['dateTime']))
        $logEvent['dateTime'] = date('Y-m-d H:i:s');

    // Check if we can open a connection to log DB
    for ($retry = 2; $retry > 0;) :
        try {
            if (isset($_SESSION['mongoAuth'])) {
                $m = new MongoClient($logDbConnStr, $_SESSION['mongoAuth']);
            } else {
                $m = new MongoClient($logDbConnStr);
            }
            $retry = 0;
        } catch (MongoConnectionException $e) {
            if (--$retry == 0)
                throw new Exception($logEvent['event'] . '.noDb');
        }
    endfor;

    // Insert new log record
    $logId = uniqid();
    $logEvent['_id'] = $logId;

    try {
        $db = $m->selectDB($logDbname);
        $collection = $db->selectCollection('syslog');
        $collection->insert($logEvent);
    } catch (MongoCursorException $e) {
        throw new Exception($logEvent['event'] . '.noDoc');
    }

    return $logId;
}
