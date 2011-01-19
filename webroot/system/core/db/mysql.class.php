<?php
// -------------------------------------------------------------------------------+
// | Name: Mysql - MySQL implement of Dao                                         |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu <kevin@cgtvgames.com>                                   |
// -------------------------------------------------------------------------------+
// | Issued: 2010.07.19                                                           |
// +------------------------------------------------------------------------------+

class Mysql extends Dao
{
	function connect($dsn=null)
	{
		if (!$dsn) $dsn = $this->dsn;
		$this->conn = mysql_pconnect($dsn['hostname'], $dsn['username'], $dsn['password']) or $this->_err("failed to connect to database");
		if ($dsn['dataase']) mysql_select_db($dsn['dataase']);
		return $this->conn;
	}

    function fetchIntoArray($query)
    {
      if (!isset($this->conn)) $this->connect();
      $pth=mysql_query($query) or $this->_err($query);
      $cnt=mysql_num_rows($pth);
      for($i=0;$i<$cnt;$i++){
          $row=mysql_fetch_assoc($pth);
		  foreach($row as $fname=>$val){$pthult["$fname"][$i]=$val;}
      }
      mysql_free_result($pth);
      return $pthult;
    }
    function fetchOneRow($query,$type = null)
    {
        if (!isset($this->conn)) $this->connect();
        $pth = mysql_query($query) or $this->_err($query);
        if ($type==ASSOC) {
            return array_change_key_case(mysql_fetch_assoc($pth), CASE_LOWER);
        }
        return mysql_fetch_row($pth);
    }

    function sql_prepare($query)
    {
        if (!isset($this->conn)) $this->connect();
        $pth = mysql_query($query) or $this->_err($query);
        return $pth;
    }
    function fetchByRow($hd, $mod=null)
    {
        if ($mod==ASSOC) {
            return array_change_key_case(mysql_fetch_assoc($pth), CASE_LOWER);
        }
        return mysql_fetch_row($hd);
    }

    function countRow($query)
    {
        if (!isset($this->conn)) $this->connect();
        $pth = mysql_query($query) or $this->_err($query);
        list ($cnt) = mysql_fetch_row($pth);
        return $cnt;
    }

    function updateDB($query)
    {
        if (!isset($this->conn)) $this->connect();
        mysql_query($query) or $this->_err($query);
        $cnt = mysql_affected_rows();
        return $cnt;
    }
    function insertDB($query)
    {
        if (!isset($this->conn)) $this->connect();
        mysql_query($query) or $this->_err($query);
        return mysql_insert_id();
    }
	function _err($err_msg=null)
	{
		$err_msg .= '<br />[#'.mysql_errno().']'.(DEBUG?htmlentities(mysql_error()):
			"Sorry, an internal error causes abortion of process.");
		trigger_error($err_msg, E_USER_ERROR);
	}
}
?>
