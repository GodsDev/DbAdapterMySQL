<?php

namespace GodsDev\DbAdapterMySQL;

use GodsDev\Backyard\BackyardMysqli;



use GodsDev\Backyard\BackyardError; // not use Psr\Log\LoggerInterface; because method dieGraciously is used
/**
 * __construct
 * @param string $host_port accepts either hostname (or IPv4) or hostname:port
 * @param string $user
 * @param string $pass
 * @param string $db
 * @param BackyardError $BackyardError
 * 
 * To open as persistent use: $connection = new backyard_mysqli('p:' . $dbhost, $dbuser, $dbpass, $dbname);
 * class backyard_mysqli based on my_mysqli from https://github.com/GodsDev/repo1/blob/58fa783d4c7128579b729465dc36b45568f9ddb1/myreport/src/mreport_functions.php as of 120914
 * Sets the connection charset to utf-8 and collation to utf8_general_ci
 * @todo add IPv6 , e.g ::1 as $host_port
 */
/*
class BackyardMysqli extends \mysqli
{

    protected $BackyardError = null;

    /**
     * 
     * @param string $host_port accepts either hostname (or IPv4) or hostname:port
     * @param string $user
     * @param string $pass
     * @param string $db
     * @param BackyardError $BackyardError
     */
  /*  public function __construct($host_port, $user, $pass, $db, BackyardError $BackyardError)
    {
        //error_log("debug: " . __CLASS__ . ' ' . __METHOD__);
        $this->BackyardError = $BackyardError;
parent::__construct($host, $user, $pass, $db, $port);

*/

/**
 * Replacement class DbAdapterMySQL (file class_DbAdapterMySQL.php) for easier upgrade to PHP7 of legacy GODS apps
 * 
 */
class DbAdapterMySQL
{
    use \Nette\SmartObject;

    private $connect;

    public function __construct($dbServerHost, $dbUser, $dbPass, $dbSchema) {
        
        
        // @todo change to object Mysqli or even better to GodsDev\Backyard\BackyardMysqli
        $this->connect = mysqli_connect("p:" . $dbServerHost, $dbUser, $dbPass, $dbSchema);
        if (!$this->connect) {
            error_log('Connect Error (' . mysqli_connect_errno() . ') '
                    . mysqli_connect_error());
            die('DB server access denied');
        }
        mysqli_query($this->connect, 'SET CHARACTER SET utf8');
    }

    /**
     * 
     * @param string $qry
     * @return mixed array of array of strings on non-empty success, false otherwise
     */
    public function fetchNum($qry) {
        $qryResult = $this->execute($qry);
        if ($qryResult === false) {
            return false;
        }
        $resultAry = array();
        for ($i = 0; (bool) ($row = mysqli_fetch_array($qryResult, MYSQLI_NUM)); $i++) {
            $resultAry[$i] = $row;
        }
        if (!$i) {
            return false;
        }
        //array_walk($resultAry, 'convert'); 
        return $resultAry;
    }

    /**
     * 
     * @param string $qry
     * @return mixed array of array of strings on non-empty success, false otherwise
     */
    public function fetchAssoc($qry) {
        $qryResult = $this->execute($qry);
        if ($qryResult === false) {
            return false;
        }
        $resultAry = array();
        for ($i = 0; (bool) ($row = mysqli_fetch_array($qryResult, MYSQLI_ASSOC)); $i++) {
            $resultAry[$i] = $row;
        }
        if (!$i) {
            return false;
        }
        //array_walk($resultAry, 'convert'); 
        return $resultAry;
    }

    /**
     * 
     * @param string $qry
     * @return mixed one dimensional array of strings on non-empty success, false otherwise
     */
    public function fetch1Assoc($qry) {
        $qryResult = $this->execute($qry);
        if ($qryResult === false) {
            return false;
        }
        $row = mysqli_fetch_array($qryResult, MYSQLI_ASSOC);
        if (is_null($row)) { //let empty result return the same false as in case of failed request
            return false;
        }
        return $row;
    }

    /**
     * 
     * @param string $dbTableName
     * @param array $columnValueAry
     * @param string $conditions
     */
    public function update($dbTableName, array $columnValueAry, $conditions) {
        $columnValueStr = "";
        array_walk($columnValueAry, 'decodeStr');
        foreach ($columnValueAry as $rColumn => $rValue) {
            if ($rValue == 'NOW()') {
                $columnValueStr .= " {$rColumn} = NOW(),";
            } else {
                $columnValueStr .= " {$rColumn} = '{$rValue}',";
            }
        }
        $columnValueStr = substr($columnValueStr, 0, -1);
        $qry = "UPDATE `{$dbTableName}` SET {$columnValueStr} {$conditions}";
        $this->execute($qry);
    }

    /**
     * 
     * @param string $dbTableName
     * @param array $columnValueAry
     * @return mixed int on AUTO_INCREMENT, 0 on no AUTO_INCREMENT, false on no MySQL connection
     */
    public function insert($dbTableName, array $columnValueAry) {
        array_walk($columnValueAry, 'decodeStr');
        $qry = "INSERT INTO `{$dbTableName}` (`"
                . implode("`, `", array_keys($columnValueAry))
                . "`) VALUES ('"
                . implode("', '", $columnValueAry) . "')";
        $res = $this->execute($qry);
        if (!$res) {
            return false;
        }
        return mysqli_insert_id($this->connect);
    }

    /**
     * 
     * @param string $dbTableName
     * @param array $columnValueAry
     * @return mixed int on AUTO_INCREMENT, 0 on no AUTO_INCREMENT, false on no MySQL connection
     */
    public function insertMulti($dbTableName, array $columnValueAry) {
        array_walk($columnValueAry, 'decodeStr');
        $qry = "INSERT INTO `{$dbTableName}` ("
                . implode(", ", $columnValueAry[0])
                . ") VALUES ";

        $valuesStrAry = array();
        for ($i = 1; $i < count($columnValueAry); $i++) {
            $valuesStrAry[] = "('" . implode("', '", $columnValueAry[$i]) . "')";
        }
        $qry .= implode(",", $valuesStrAry);
        $res = $this->execute($qry);
        if (!$res) {
            return false;
        }
        return mysqli_insert_id($this->connect);
    }

    /**
     * 
     * @param string $dbTableName
     * @param string $condition
     */
    public function delete($dbTableName, $condition) {
        $this->execute("DELETE FROM `{$dbTableName}` {$condition}");
    }

    /**
     * 
     * @param string $qry
     * @param boolean $debug
     * @return mixed false on error, resource or true on success based on type of SQL statement
     */
    public function execute($qry, $debug = true) {
        $qry .= ";";
        app_log($qry, 4);
        $qry_result = mysqli_query($this->connect, $qry);
        if ($qry_result === false) {
            error_log("db error " . mysqli_errno($this->connect) . " " . mysqli_error($this->connect));
        }
        return $qry_result;
    }

    public function close() {
        mysqli_close($this->connect) or error_log("mysqli close failed");
    }

}

function decodeStr(&$item) {
    if (is_array($item)) {
        array_walk($item, 'decodeStr');
    } else {
        $item = str_replace('&quot;', '"', $item);
        $item = str_replace("'", "''", $item);
        $item = str_replace("&#039;", "''", $item);
    }
}

function convert(&$item, $key) {
    if (is_array($item)) {
        array_walk($item, 'convert');
    } else {
        $item = str_replace('"', '&quot;', $item);
        //$item = iconv("ISO-8859-2", "UTF-8", $item);
    }
}
