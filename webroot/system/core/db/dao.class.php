<?php
// -------------------------------------------------------------------------------+
// | Name: Dao - Abstract Class for DB Access                                     |
// +------------------------------------------------------------------------------+
// | Package: SimplyPHP Framework                                                 |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu <kevin@w3softwares.com>                                 |
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
    abstract protected function connect($dsn=null);
	abstract protected function _err($msg=null);
	function __construct($dsn)
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
		if (is_array($id)) {
			$pk_filter = $this->param2field($id, 'filter');
		} else {
			$pk_filter = "$pkey='$id'";
		}
        if (!$param) {
            return $this->fetchOneRow("select * from $table where $pk_filter", ASSOC);
        } elseif ($id && $param && !is_array($param)) {
            if ($param==='delete') {
                return $this->updateDB("delete $table where $pk_filter");
            }
            return null;   // invalid param
        }
        if ($id) {
            list($cnt) = $this->fetchOneRow("select count(*) from $table where $pk_filter");
            if ($cnt) {
				$set_str = $this->param2field($param, 'set');
                return $this->updateDB("update $table set $set_str where $pk_filter");
            }
			if (is_array($id)||$seq!=='fkey') return null;
            $param[$pkey] = $id;
        }
        if (!isset($param[$pkey])||!$param[$pkey]) {
			if ($seq==='fkey') {
				return null;
        	} elseif ($seq==='auto') {
            	$r = $this->fetchOneRow("select max($pkey) as PK from $table");
           		if (count($r)) {
                	$param[$pkey] = $r[0]+1;
            	} else {
                	$param[$pkey] = 1;
            	}
			} elseif ($seq) {
	            list($id) = $this->fetchOneRow("select $seq.nextval from dual");
    	        $param[$pkey] = $id;
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
        if (strtolower($fields)=='count(*)') {
            $r = $this->fetchOneRow($query);
            return intval(@$r[0]);
        }
		$lines = array();
		$hd = $this->sql_prepare($query);
		while ($r=$this->fetchByROw($hd, ASSOC)) $lines[] = $r;
		return count($lines)?$lines:null;
	}
	public function table_tree($table_def, $root, $deep=null, $fields='*', $level=1)
	{
		$table = $table_def['name'];
		$field_id = $table_def['pkey'];
		$field_parent = $table_def['parent'];
		$field_weight = $table_def['weight'];
		$query = "select $fields,$level as LEVEL from $table where $field_parent=$root order by $field_weight desc";
		$hd = $this->sql_prepare($query);
		$lines = array();
		if ($deep) $deep--;
		while ($node=$this->fetchByRow($hd, ASSOC)) {
			$lines[] = $node;
			if (!isset($deep)||$deep>0) {
				$lines = array_merge($lines, $this->table_tree($table_def, $node[$field_id], $deep, $fields, $level+1));
			}
		}
		return $lines;
	}
	/** Instancing table objects **/
	final function load_table($table_def)
	{
		return new TableObject($table_def, $this);
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

/*** Table Object ***
 *	Generic Database Table Operations
***/
Class TableObject
{
	protected $table = null;
	function __construct($table, $db)
	{
		$this->table = $table;
		$this->db = $db;
	}
	function read($id)
	{
		$info = null;
		if ($id) {
			$info = $this->db->table_proc($this->table, $id);
			$info = $this->prefix($info, 'off');
		}
		return $info;
	}
	function search($filter, $suffix=null, $fields='*')
	{
		$filter = $this->prefix($filter, 'on');
		if ($lines=$this->db->table_search($this->table['name'], $filter, $suffix, $fields)) {
			if (@$this->table['prefix']) {
				for ($i=0; $i<count($lines); $i++) $lines[$i] = $this->prefix($lines[$i],'off');
			}
		}
		return $lines;
	}
	function tree($root, $deep=null, $fields='*')
	{
		if (!(isset($this->table['parent'])&&isset($this->table['weight']))) return null;
		if ($lines=$this->db->table_tree($this->table, $root, $deep, $fields)) {
			if (@$this->table['prefix']) {
				for ($i=0; $i<count($lines); $i++) $lines[$i] = $this->prefix($lines[$i],'off');
			}
		}
		return $lines;
	}
	function create($param)
	{
		return is_array($param)&&count(array_keys($param))?$this->db->table_proc($this->table, 0, $param):null;
	}
	function delete($id)
	{
		return $id?$this->db->table_proc($this->table, $id, 'delete'):null;
	}
	function update($id, $param)
	{
		return $id?$this->db->table_proc($this->table, $id, $param):null;
	}
	function prefix($in, $onoff)
	{
		$prefix = strtolower(@$this->table['prefix']);
		if (!$prefix||!$in) return $in;
		$hash = array();
		foreach ($in as $key=>$val) {
			if ($onoff==='on') {
				$key = $prefix.'_'.$key;
			} elseif ($onoff==='off'&&strpos($key, $prefix)===0) {
				$key = substr($key, strlen($prefix)+1);
			}
			$hash[$key] = $val;
		}
		return $hash;
	}
}
