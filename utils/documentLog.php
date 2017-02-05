<?php

/**
 * Send Mongo document to log database (
 * @param string $logDbConnStr MongoDB log connection parameter string
 * @param array $event Source and event data
 * - srcDb: Source database name (required)
 * - srcCol: Source collection name (required)
 * - user: Identifier of the user that triggered the event / action (most of the times it would be the user’s email from Spectrum-E) (required)
 * - dateTime: Date and time of when the event / action took place (optional)
 * - event: Description of the action that triggered the document update in the DB (text / message that follows the logic of the system’s business rules) (optional)
 * @param array $srcDoc Complete document in its previous state (before the event / action took place) (required)
 * @param string $logDbName Database name for the logs (optional)
 * @return string Id of log document
 */
function documentLog($logDbConnStr, $event, $srcDoc, $logDbName = null) {
    /**
     * Sample $event structure
     * $logEvent = [
     * 'srcDb' => '',
     * 'srcCol' => '',
     * 'user' => '',
     * 'event' => ''
     * ];
     */
    if (empty($logDbConnStr))
        return false;

    if (isset($_SESSION['mongoOpt']) and is_array($_SESSION['mongoOpt'])) {
        $DbConOpt = $_SESSION['mongoOpt'];
    } else {
        $DbConOpt = [];
    }

    // Check if source fields have been provided
    $requiredFields = ['srcDb', 'srcCol', 'user'];
    foreach ($requiredFields as $key):
        if (!isset($event[$key])):
            throw new Exception($key . ' was not provided.');
        endif;
    endforeach;

    // Check if we have source document _id
    if (!isset($srcDoc['_id'])):
        throw new Exception('Source id was not found.');
    else:
        $event['srcId'] = $srcDoc['_id'];
    endif;

    // Set log_date if not provided
    if (!isset($event['dateTime']))
        $event['dateTime'] = date('Y-m-d H:i:s');

    // Check if we can open a connection to log DB
    for ($retry = 2; $retry > 0;) :
        try {
            if (isset($_SESSION['mongoAuth'])) {
                $m = new MongoClient($logDbConnStr, $_SESSION['mongoAuth']);
            } else {
                $m = new MongoClient($logDbConnStr, $DbConOpt);
            }
            $retry = 0;
        } catch (MongoConnectionException $e) {
            if (--$retry == 0)
                throw new Exception($e);
        }
    endfor;

    // Insert new log record
    $logId = uniqid();
    $event['_id'] = $logId;

    try {
        $event['prevDoc'] = $srcDoc;

        if ($logDbName === null) {
            $logDbName = $event['srcDb'];
        }

        $db = $m->selectDB($logDbName);
        $collection = $db->selectCollection('log');
        $collection->insert($event);
    } catch (MongoCursorException $e) {
        throw new Exception($e);
    }

    return $logId;
}

/**
 * Send Mongo document to log database (PHP7 & MongoDB driver version)
 * @param string $logDbConnStr MongoDB log connection parameter string
 * @param array $event Source and event data
 * - srcDb: Source database name (required)
 * - srcCol: Source collection name (required)
 * - user: Identifier of the user that triggered the event / action (most of the times it would be the user’s email from Spectrum-E) (required)
 * - dateTime: Date and time of when the event / action took place (optional)
 * - event: Description of the action that triggered the document update in the DB (text / message that follows the logic of the system’s business rules) (optional)
 * @param array $srcDoc Complete document in its previous state (before the event / action took place) (required)
 * @param string|null $logDbName Database name for the logs (optional)
 * @return bool|string Id of log document, or false on error
 * @throws Exception
 */
function documentLog7($logDbConnStr, $event, $srcDoc, $logDbName = null) {
    if (empty($logDbConnStr))
        return false;

    // Check if source fields have been provided
    $requiredFields = ['srcDb', 'srcCol', 'user'];
    foreach ($requiredFields as $key):
        if (!isset($event[$key])):
            throw new Exception($key . ' was not provided.');
        endif;
    endforeach;

    // Check if we have source document _id
    if (!isset($srcDoc['_id'])):
        throw new Exception('Source id was not found.');
    else:
        $event['srcId'] = $srcDoc['_id'];
    endif;

    // Set log_date if not provided
    if (!isset($event['dateTime']))
        $event['dateTime'] = date('Y-m-d H:i:s');

    $manager = new MongoDB\Driver\Manager($logDbConnStr);
    $bulk = new MongoDB\Driver\BulkWrite(['ordered' => true]);
    $writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);

    // Insert new log record
    try {
        $logId = uniqid();
        $event['_id'] = $logId;
        $event['prevDoc'] = $srcDoc;

        $bulk->insert($event);

        if ($logDbName === null) {
            $logDbName = $event['srcDb'];
        }


        $result = $manager->executeBulkWrite($logDbName . '.log', $bulk, $writeConcern);
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        $result = $e->getWriteResult();
        $logId = false;

        // Check if the write concern could not be fulfilled
        if ($writeConcernError = $result->getWriteConcernError()) {
            error_log('ERROR MongoDB writeConcernError: ' . $writeConcernError->getMessage() . ' (' . $writeConcernError->getCode());
        }

        // Check if any write operations did not complete at all
        foreach ($result->getWriteErrors() as $writeError) {
            error_log("ERROR MongoDB Operation #" . $writeError->getIndex() . ' ' . $writeError->getMessage() . ' (' . $writeError->getCode() . ')');
        }
    } catch (MongoDB\Driver\Exception\Exception $e) {
        error_log("ERROR MongoDB Other: ", $e->getMessage());
        $logId = false;
    }

    return $logId;
}