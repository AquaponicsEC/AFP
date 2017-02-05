<?php
/** 
 * Mongo connection default values 
 * Usually variables are set up on loginproc or rf_locals
 * 
 * $dbhost or $_SESSION['dbhost'] string Mongo host
 * $dbport or $_SESSION['dbport'] string Mongo port
 * $dbname or $_SESSION['dbname'] string database name
 * $logDbname or $_SESSION['logdbname'] string log database name
 */
if(!isset($dbhost)){
    $dbhost = "localhost";
}
if(!isset($dbport)){
    $dbport = "27017";
}
if(!isset($dbname)){
    $dbname = "2001-Aquaponics";
}
if (version_compare(PHP_VERSION, '7.0.8') >= 0) {
    // include user-level libraries only if we're on PHP 7
    require_once $_SERVER['DOCUMENT_ROOT'] . '/AFP/utils/vendor/autoload.php';
}

if(isset($_SESSION['mongoRs'])) {
    $mongoRs = $_SESSION['mongoRs'];
    if (isset($_SESSION['mongoOpt']) and is_array($_SESSION['mongoOpt'])) {
        $DbConOpt = $_SESSION['mongoOpt'];
    } else {
        $DbConOpt = [];
    }

    for($retry=2; $retry>0;) {
        try{ $m = new MongoClient($mongoRs, $DbConOpt); $retry=0;}
        catch (MongoConnectionException $e ) { if(--$retry==0) die($e); }
    }
    
    if(isset($_SESSION['dbname'])) $svrDB = $dbName = $dbname = $_SESSION['dbname'];
    if(isset($_SESSION['logdbname'])) $logDbname = $_SESSION['logdbname'];
    $db = $m->$svrDB;
    $logConnection = $_SESSION['mongoRs'];
    $DB_CONN = [$svrDB => $mongoRs, $logDbname => $logConnection];
}

if(isset($_SESSION)) {
    if(isset($_SESSION['dbhost'])) $dbhost = $_SESSION['dbhost'];
    if(isset($_SESSION['dbport'])) $dbport = $_SESSION['dbport'];
    if(isset($_SESSION['dbname'])) $dbname = $_SESSION['dbname'];
    if(isset($_SESSION['logdbname'])) $logDbname = $_SESSION['logdbname'];
}

if(!isset($_SESSION['mongoRs'])) {
    if(isset($_SESSION['mongoAuth'])) {
        for($retry=2; $retry>0;) {
            try{ $m = new MongoClient("mongodb://".$dbhost.":".$dbport , $_SESSION['mongoAuth']); $retry=0;}
            catch (MongoConnectionException $e ) { if(--$retry==0) die($e); }
        }
    }
    else {        
        for($retry=2; $retry>0;) {
            try{ $m = new MongoClient("mongodb://".$dbhost.":".$dbport); $retry=0;}
            catch (MongoConnectionException $e ) { if(--$retry==0) die($e); }
        }
    }
    if(isset($_SESSION['dbname'])){
        $svrDB = $dbName = $dbname = $_SESSION['dbname'];
        $db = $m->$svrDB;
    }
    $logConnection = 'mongodb://'.$dbhost.':'.$dbport.'';
}
?>