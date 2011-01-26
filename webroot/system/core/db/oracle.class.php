<?php
// -------------------------------------------------------------------------------+
// | Name: Oracle - Oracle implement of Dao                                       |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// -------------------------------------------------------------------------------+
// | Repository: https://github.com/yuqkevin/SimplyPHP/                           |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu                                                         |
// -------------------------------------------------------------------------------+
// | Checkout: 2011.01.19                                                         |
// +------------------------------------------------------------------------------+

class Oracle extends Dao
{
	function connect($dsn=null)
	{
		if (!$dsn) $dsn = $this->dsn;
		$this->conn = oci_connect($dsn['username'], $dsn['password'], $dsn['hostname']) or $this->_err();
		return $this->conn;
	}

    function sql_prepare($query)
    {
        if (!isset($this->conn)) $this->connect();
        $pth = OCIParse($this->conn,$query) or $this->_err($this->conn);
        OCIExecute($pth) or $this->_err();
        return $pth;
    }

    function fetchByRow($pth, $type=NUM)
    {
        if (!isset($this->conn)) $this->connect();
        if ($type==NUM) {
            OCIFetchInto($pth, $row, OCI_NUM);
        } elseif ($type==ASSOC) {
            OCIFetchInto($pth, $row, OCI_ASSOC);
			if (is_array($row)) $row=array_change_key_case($row, CASE_LOWER);
        } elseif ($type==LOB) {
            OCIFetchInto($pth, $row, OCI_RETURN_LOBS);
        } else {
            OCIFetchInto($pth, $row);
        }
        return $row; 
    }

    function fetchOneRow($query, $type=NUM)
    {
        if (!isset($this->conn)) $this->connect();
        $pth = OCIParse($this->conn, $query) or $this->_err($this->conn);
        OCIExecute($pth) or $this->_err($pth);
        if ($type==NUM) {
            OCIFetchInto($pth, $row, OCI_NUM);
        } elseif ($type==ASSOC) {
            OCIFetchInto($pth, $row, OCI_ASSOC);
			if (is_array($row)) $row=array_change_key_case($row, CASE_LOWER);
        } elseif ($type==LOB) {
            OCIFetchInto($pth, $row, OCI_RETURN_LOBS);
        } else {
            OCIFetchInto($pth, $row);
        }
        return $row;
    }

    function countRow($query)
    {
        if (!isset($this->conn)) $this->connect();
        $pth = OCIParse($this->conn, $query) or $this->_err($this->conn);
        OCIExecute($pth) or $this->_err($pth);
        OCIFetchinto($pth,$row);
        if (isset($row[0])) {
            return $row[0];
        }
        return NULL; 
    }

    function updateDB($query)
    {
        if (!isset($this->conn)) $this->connect();
        $pth = OCIParse($this->conn,$query) or $this->_err($this->conn);
        OCIExecute($pth) or $this->_err($pth);
        $cnt=OCIRowCount($pth);
        OCIFreeStatement($pth) or $this->_err();
        return $cnt;
    }
	function insertDB($query)
	{
		return $this->updateDB($query);
	}

	function alt_session($name, $val)
	{
		return $this->updateDB("alter session set $name='$val'");
	}
	function procedure($query, $param=array('status'=>2))
	{
        if (!isset($this->conn)) $this->connect();
        $pth = OCIParse($this->conn,$query) or $this->_err($this->conn);
        foreach ($param as $key=>$max_val) OCIBindByName($pth,":$key",&$param[$key]);
        $hd = OCIExecute($pth)  or $this->_err($pth);
        OCIFreeStatement($pth) or $this->_err($hd);
        return count(array_keys($param))===1?array_shift(array_values($param)):$param;
	}
    public function table_search($table, $filter=null, $suffix=null, $fields='*')
    {
        if (is_array($table)) {
            $table_info = $table;
            $table = $table_info['name'];
            if (isset($table_info['schema'])) $table = $table_info['schema'].".$table";
        }
        $query = "select $fields from $table";
        if ($filter) {
            $query .= " where ".$this->param2field($filter, 'filter');
        }
        $query .= ' '.$suffix;
		$query = $this->limit_rows($query);
        if (strtolower($fields)=='count(*)') {
            $r = $this->fetchOneRow($query);
            return intval(@$r[0]);
        }
        $lines = array();
        $hd = $this->sql_prepare($query);
        while ($r=$this->fetchByROw($hd, ASSOC)) $lines[] = $r;
        return count($lines)?$lines:null;
    }

    /** limit_rows
     *  Equivalent of mysql limit clase
     *  @input string       old sql
     *  @output string      new sql (with additional field 'rnum'
    **/
    function limit_rows($sql)
    {
		if (preg_match("/limit (\d+),\s*(\d+)/msi", $sql, $p)) {
			$start = $p[1];
			$offset = $p[2];
			$sql = str_replace($p[0], '', $sql);
		} elseif (preg_match("/limit (\d+)/msi", $sql, $p)) {
			$start = 0;
			$offset = $p[1];
			$sql = str_replace($p[0], '', $sql);
		} else {
			return $sql;
		}
        $limit = $start + $offset;
        $newsql = "SELECT * FROM (select inner_query.*, rownum rnum FROM ($sql) inner_query WHERE rownum<=$limit)";
        if ($start>0) {
            $newsql .= " WHERE rnum>$start";
        }
        return $newsql;
    }

	function _err($handle=null)
	{
		$e = isset($handle)?oci_error($handle):oci_error();
		$err_msg = DEBUG?htmlentities(print_r($e, true)): "Sorry, an internal error causes abortion of process. <br />[#{$e['code']}]";
		debug_print_backtrace();
		trigger_error($err_msg, E_USER_ERROR);
	}

}
