<?php
// -------------------------------------------------------------------------------+
// | Name: Dao - Abstract Class for DB Access                                     |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu <kevin@cgtvgames.com>                                   |
// -------------------------------------------------------------------------------+
// | Issued: 2010.07.19                                                           |
// +------------------------------------------------------------------------------+
//
define('NUM', 1);
define('ASSOC', 2);
define('LOB', 4);
abstract class Dao
{
    abstract function fetchOneRow($query,$type=null);
    abstract function sql_prepare($query);
    abstract function fetchByRow($hd, $mod=null);
    abstract function updateDB($query);
    abstract function insertDB($query);
    abstract protected function connect($dsn);
	abstract protected function _err($msg);
	public function __construct($dsn)
	{
		$this->dsn = $dsn;
		$this->connect();
	}
    /** table_proc( integer id, array param)
     *  table maitenance/read function.
     *  input:  id: key value, or 0 if insert
     *          param:  update/insert name-value pairs, or null if read
     *  @auth:  Kevin Q. Yu <kevin@cgtvgames.com>
    **/
    final function table_proc($table_info, $id, $param=null)
    {
        $table = $table_info['name'];
		if (isset($table_info['schema'])) $table = $table_info['schema'].".$table";
        $pkey = $table_info['pkey'];
        $seq = @$table_info['seq'];
		if (isset($table_info['schema'])) $seq = $table_info['schema'].".$seq";
        if (!$param) {
            return $this->fetchOneRow("select * from $table where $pkey='$id'", ASSOC);
        } elseif ($id && $param && !is_array($param)) {
            if ($param==='delete') {
                return $this->updateDB("delete $table where $pkey='$id'");
            }
            return null;   // invalid param
        }
        if ($id) {
            list($cnt) = $this->fetchOneRow("select count(*) from $table where $pkey='$id'");
            if ($cnt) {
				$set_str = $this->param2field($param, 'set');
                return $this->updateDB("update $table set $set_str where $pkey='$id'");
            }
            $param[$pkey] = $id;
            $seq = null;
        }
        if (!isset($param[$pkey])) {
			if ($table_info['seq']!=='auto') {
	            list($id) = $this->fetchOneRow("select $seq.nextval from dual");
    	        $param[$pkey] = $id;
        	} else {
            	$r = $this->fetchOneRow("select max($pkey) as PK from $table");
           		if (count($r)) {
                	$param[$pkey] = $r[0]+1;
            	} else {
                	$param[$pkey] = 1;
            	}
			}
        }
		$pair = $this->param2field($param, 'insert');
        $result = $this->insertDB("insert into $table ({$pair['fields']}) values ({$pair['values']})");
        return $result&&isset($param[$pkey])?$param[$pkey]:$result;
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
		$lines = array();
		$hd = $this->sql_prepare($query);
		while ($r=$this->fetchByROw($hd, ASSOC)) $lines[] = $r;
		return count($lines)?$lines:null;
	}
	final function param2field($param, $type)
	{
		$result = null;
		switch ($type) {
			case 'filter':
			case 'set':
				$p = array();
				foreach ($param as $key=>$val) {
					if (is_array($val) && $type=='filter') {
						for ($i=0; $i<count($val); $i++) $val[$i] = "'".$this->sql_escape_string($val[$i])."'";
						$p[] = sprintf("$key in (%s)", join(',', $val));
					} elseif (0&&preg_match("/^f::([^;]+)$/", $val, $p)) {  // dangous for sql inject attack
						$p[] = "$key=$p[1]";
					} else {
						$p[] = sprintf("$key=%s", isset($val)?("'".$this->sql_escape_string($val)."'"):'null');
					}
				}
				$result = join($type=='set'?',':' and ', $p);
				break;
			case 'insert':
				$k = $v = array();
				foreach ($param as $key=>$val) {
					$k[] = $key;
					if (0&&preg_match("/^f::([^;]+)$/", $val, $p)) {	// dangous for sql inject attack
						$v[] = $p[1];
					} else {
						$v[] = isset($val)?("'".$this->sql_escape_string($val)."'"):'null';
					}
				}
				$result = array('fields'=>join(',', $k),'values'=>join(',', $v));
				break;
		}
		return $result;
	}
	public function sql_escape_string($str)
	{
		return str_replace("'", "''", $str);
	}
}
?>
