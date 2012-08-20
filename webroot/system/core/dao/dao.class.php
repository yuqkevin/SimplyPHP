<?php
// -------------------------------------------------------------------------------+
// | Name: Dao - Core class for DB Access based on PDO                            |
// +------------------------------------------------------------------------------+
// | Package: SimplyPHP Framework                                                 |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu <kevin@w3softwares.com>                                 |
// -------------------------------------------------------------------------------+
// | Issued: 2011.05.06                                                           |
// +------------------------------------------------------------------------------+
//
// table_def array definition
// +---------+-------------------------------------------------------------------------------------------------------+
// | Item    | Description
// +---------+-------------------------------------------------------------------------------------------------------+
// | name	 | Table name	e.g. auth_user, production.sports_car
// +---------+-------------------------------------------------------------------------------------------------------+
// | pkey	 | Name of primary key field	 
// +---------+-------------------------------------------------------------------------------------------------------+
// | prefix	 | Prefix for all fields	(Optional), it's applied to all fields if defined here.
// +---------+-------------------------------------------------------------------------------------------------------+
// | seq	 | Sequence for primary key	avfailable values:auto/null/sequence name. auto using maximum primary key +1.
// +---------+-------------------------------------------------------------------------------------------------------+
// | schema	 | Schema of table and sequence if applied	(Optional)
// +---------+-------------------------------------------------------------------------------------------------------+
// | parent	 | Name of field which is defined in tree structure table.	 
// +---------+-------------------------------------------------------------------------------------------------------+
// | weight	 | Weight number for rank in tree structre table	Bigger in weight then higher in rank.
// +---------+-------------------------------------------------------------------------------------------------------+
// | order	 | (optional) Direction for weight, 'asc/desc', default is desc, 
// +---------+-------------------------------------------------------------------------------------------------------+
// | access  | (optional) table access feature: 'readonly.read/write', default is 'read/write'
// +---------+-------------------------------------------------------------------------------------------------------+

// Data Source Name (DSN) Array Definition
// +----------+---------------------------------------------------------------------+
// | Key	  | Description
// +----------+---------------------------------------------------------------------+
// | hostname | Server Name	'localhost', domain name or any defined in hosts file
// +----------+---------------------------------------------------------------------+
// | username | User Name	 
// +----------+---------------------------------------------------------------------+
// | password | Password
// +----------+---------------------------------------------------------------------+
// | database | Database or Schema Name	 
// +----------+---------------------------------------------------------------------+
// | dbdriver | Name of Database Driver	mysql,oci,pgsql,...,etc.
// +----------+---------------------------------------------------------------------+

class Dao
{
	public $dbh = null;
	public $transaction = 0; // 0:non-transaction, 1:in transaction, 2 or others: error happened in transaction
	public $error = null;	// error info for debugging
	protected $dsn = null;

	const	ACCESS_READONLY = 'readonly';	// read only table. table access feature in table_def

	public function __construct($dsn)
	{
		$this->dsn = $dsn;
	}
	public function connect($dsn=null)
	{
		$pure_connet_flag = (bool) $dsn;
		if (!$dsn) $dsn = $this->dsn;
		if ($pure_connet_flag||!$this->dbh) {
			$dsn_str = "{$dsn['dbdriver']}:dbname={$dsn['database']};host={$dsn['hostname']};";
			try {
				$dbh = new PDO($dsn_str, $dsn['username'], $dsn['password']);
				$dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
				if ($pure_connet_flag) return $dbh;
				$this->dbh = $dbh;
			} catch (Exception $e) {
    			exit("Database connection failure: ".$e->getMessage()."\n");
			}
		}
		return $this->dbh;
	}
	public function table_read($table_def, $id, $fields='*')
	{
		if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
		$prefix = strtolower(@$table_def['prefix']);
		$fields = $this->field_convert($fields, $prefix);
		if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
		$sth = $this->dbh->prepare("select $fields from $table_name where {$table_def['pkey']}=?");
        $sth->execute(array($id));
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        $sth->closeCursor();
        return $this->prefix($row, $prefix, 'off');
	}
	public function table_update($table_def, $id, $param)
	{
		if (isset($table_def['access'])&&strtolower($table_def['access'])===self::ACCESS_READONLY) return $this->error_handler('ERROR_READONLY_TABLE');
		$this->error = null;
		if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
		if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
		$prefix = strtolower(@$table_def['prefix']);
		$param = $this->prefix($param, $prefix, 'on');

		list($set_str, $binding) = $this->param_scan($param, 'set');
		$query = "update $table_name set $set_str where ";
		if (is_array($id)) {
			list($filter_str2, $binding2) = $this->param_scan($id, 'filter');
			$query .= $filter_str2;
		} else {
			$query .= "{$table_def['pkey']}=?";
			$binding2 = array($id);
		}
		$sth = $this->dbh->prepare($query);
		if (!$sth) {
			return $this->error_handler($this->dbh->errorInfo());
		}
		$ok = $sth->execute(array_merge($binding,$binding2));
		if ($ok===false) {
			return $this->error_handler($sth->errorInfo());
		}
		return $ok;
	}
	public function table_delete($table_def, $id)
	{
		if (isset($table_def['access'])&&strtolower($table_def['access'])===self::ACCESS_READONLY) return $this->error_handler('ERROR_READONLY_TABLE');
		$this->error = null;
		if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
		if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
		$prefix = strtolower(@$table_def['prefix']);
		$query = "delete from $table_name where ";
		if (is_array($id)) {
			$id = $this->prefix($id, $prefix, 'on');
			list($filter_str, $binding) = $this->param_scan($id, 'filter');
			$query .= $filter_str;
		} else {
			$query .= "{$table_def['pkey']}=?";
			$binding = array($id);
		}
		$sth = $this->dbh->prepare($query);
		if (!$sth) {
			return $this->error_handler($this->dbh->errorInfo());
		}
		$cnt = $sth->execute($binding);
		if ($cnt===false) {
			return $this->error_handler($sth->errorInfo());
		}
		return $cnt;
	}
	public function table_insert($table_def, $param)
	{
		if (isset($table_def['access'])&&strtolower($table_def['access'])===self::ACCESS_READONLY) return $this->error_handler('ERROR_READONLY_TABLE');
		if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
		$prefix = strtolower(@$table_def['prefix']);
		if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
		$pkey = $table_def['pkey'];
		$param = $this->prefix($param, $prefix, 'on');
        if (!isset($param[$pkey])||!$param[$pkey]) {
			if (isset($table_def['seq'])&&($seq=$table_def['seq'])) {
				if (isset($table_def['schema'])) $seq = $table_def['schema'].".$seq";
                if ($seq==='fkey') {
                    return null;
                } elseif ($seq==='auto') {
                    $r = $this->dbh->query("select max($pkey) from $table_name")->fetch(PDO::FETCH_NUM);
                    if (isset($r[0])) {
                        $param[$pkey] = $r[0]+1;
                    } else {
                        $param[$pkey] = 1;
                    }
                } elseif ($seq) {
                    $r = $this->dbh->query("select $seq.nextval from dual")->fetch(PDO::FETCH_NUM);
                    $param[$pkey] = intval(@$r[0]);
                }
			}
        }
		$binding = array();
		list($ins_pair, $binding) = $this->param_scan($param, 'insert');
		$sth = $this->dbh->prepare("insert into $table_name ({$ins_pair['fields']}) values ({$ins_pair['values']})");
		if (!$sth) {
			return $this->error_handler($this->dbh->errorInfo());
		}
		$ok = $sth->execute($binding);
		if ($ok===false) {
			return $this->error_handler($sth->errorInfo());
		}
		if (isset($param[$pkey])&&$param[$pkey]) return $param[$pkey];
		return $this->dbh->lastInsertId($pkey);
	}
	public function table_search($table_def, $filter, $suffix=null, $fields='*', $offset=0, $limit=null)
	{
		if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
		if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
		$prefix = strtolower(@$table_def['prefix']);
		$fields = $this->field_convert($fields, $prefix);
		$binding = array();
        $query = "select $fields from $table_name";
        if ($filter) {
			$filter = $this->prefix($filter, $prefix, 'on');
			list($filter_str, $binding) = $this->param_scan($filter, 'filter');
            $query .= " where $filter_str";
        }
        $query .= ' '.$this->suffix($suffix, $prefix);
		$attr = $offset?array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL):array();
		$sth = $this->dbh->prepare($query, $attr);
		if (!$sth) {
			return $this->error_handler($this->dbh->errorInfo());
		}
		$ok = $sth->execute($binding);
		if ($ok===false) {
			$this->error_handler($sth->errorInfo());
			$sth->closeCursor();
			return false;
		}
		if (preg_match("/^(count|max|min)\(([\w\*]+)\)$/", strtolower($fields))) {
			$row = $sth->fetch(PDO::FETCH_NUM);
			$sth->closeCursor();
            return @$row[0];
        }
        $lines = array();
		$cnt = 0;
		if ($offset) {
			$sth->fetch(PDO::FETCH_ASSOC,PDO::FETCH_ORI_REL, $offset-1);
		}
        while ($r=$sth->fetch(PDO::FETCH_ASSOC)) {
			$lines[] = $this->prefix($r, $prefix, 'off');
			if (isset($limit)&&++$cnt>=$limit) {
				$sth->closeCursor();
				break;
			}
		}
        return count($lines)?$lines:null;
	}
    /** array table_tree(array $table_def, int $root[, string $fields='*'[,array $filter=null]])
     *  @description    Retrieve whole tree with given root (which is include in tree as well) without recursive method
     *  @input  $table_def  table definition in tbl.ini, parent, weight and root must be defined in this case
     *          $root   id of the tree root
     *          $fields the fields set in output
     *  @return tree nodes in array which is ordered by parent and weight
    **/
	public function table_single_tree($table_def, $root, $fields='*', $filter=null)
	{
        $prefix = strtolower(@$table_def['prefix']);
		$fields = $this->field_convert($fields, $prefix);
		$filter[$table_def['root']] = $root;
		$suffix = array('orderby'=>array($table_def['parent']=>null,$table_def['weight']=>isset($table_def['order'])?$table_def['order']:'desc'));
		$lines = $this->table_search($table_def, $filter, $suffix, $fields);
		if (!$lines) return null;
		$nodes = array(array_shift($lines));
		$next = null;
		$cnt_k = count($lines);
		for ($k=0; $k<$cnt_k; $k++) {
			$line = $lines[$k];
			$cnt = count($nodes);
			for ($i=0; $i<$cnt; $i++) {
				if ($nodes[$i]['id']==$line['parent']) {
					if ($next=@$nodes[$i+1]) {
						while ($next['parent']==$line['parent']) {
							$i++;
							$next = @$nodes[$i+1];
						}
					}
					$nodes[$i+1] = $line;
				} elseif ($next) {
					$tmp = $nodes[$i];
					$nodes[$i] = $next;
					$next = $tmp;
				}
			}
		}
		if ($next) $nodes[] = $next;
        return $nodes;
	}
	/** array table_tree(array $table_def, int $root[, int $deep=null[, string $fields='*'[,array $filter=null[, int $level]]]])
	 *	@description	Retrieve any part of tree by given node (which as root and excluded in tree) with recursive method 
	 *	@input	$table_def	table definition in tbl.ini, parent and weight must be defined in this case
	 *			$root	id of the tree root
	 *			$deep null for whole tree, others: the level under root will be retrieved (start from 1);
	 *			$fields	the fields set in output
	 *			$level	start number in first level
	 *	@return	tree nodes in array which is ordered by parent and weight
	**/
    public function table_tree($table_def, $root, $deep=null, $fields='*', $filter=null, $level=1)
    {
		if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
		if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
		$prefix = strtolower(@$table_def['prefix']);
		$fields = $this->field_convert($fields, $prefix);
        $field_id = $table_def['pkey'];
        $field_parent = $table_def['parent'];
        $orderby = $table_def['weight'].' '.(isset($table_def['order'])?$table_def['order']:'desc');
		$pfilter = $this->prefix($filter, $prefix, 'on');
		if (is_array($pfilter)) {
			$pfilter[$field_parent] = $root;
		} else {
			$pfilter = array($field_parent=>$root);
		}
		list($filter_str, $binding) = $this->param_scan($pfilter, 'filter');
		$sth = $this->dbh->prepare("select $fields,$level as LEVEL from $table_name where $filter_str order by $orderby");
		if (!$sth) {
			return $this->error_handler($this->dbh->errorInfo());
		}
		$ok = $sth->execute($binding);
		if ($ok===false) {
			$this->error_handler($sth->errorInfo());
			$sth->closeCursor();
			return false;
		}
        $lines = array();
        if (isset($deep)) $deep--;
        while ($node=$sth->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = $this->prefix($node, $prefix, 'off');
            if (!isset($deep)||$deep>0) {
                $lines = array_merge($lines, $this->table_tree($table_def, $node[$field_id], $deep, $fields, $filter, $level+1));
            }
        }
        return $lines;
    }

	/*** execute a query with question mark place holders and returns number of affected rows ***/
	public function query($query, $param=null)
	{
		if (!$this->dbh) $this->connect();
        $sth = $this->dbh->prepare($query);
		if (!$sth) {
			return $this->error_handler($this->dbh->errorInfo());
			return false;
		}
		$ok = $sth->execute($param);
		if ($ok===false) {
			$this->error_handler($sth->errorInfo());
			$sth->closeCursor();
		}
		return $ok;
	}
	/*** prepare query for row by row reading, used with fetch() togather, usually for large number rows reading ***/
	public function prepare($query, $param=null)
	{
        if (!$this->dbh) $this->connect();
        $sth = $this->dbh->prepare($query);
        if (!$sth) {
            return $this->error_handler($this->dbh->errorInfo());
            return false;
        }
        $ok = $sth->execute($param);
        if ($ok===false) {
            $this->error_handler($sth->errorInfo());
            $sth->closeCursor();
        }
		return $sth;
	}
	/*** fetch one row by given PDOstatement object ***/
	public function fetch($sth)
	{
		return $sth->fetch();
	}
	public function procedure($query, $param)
	{
		if (!$this->dbh) $this->connect();
        $sth = $this->dbh->prepare($query);
		if (!$sth) {
			return $this->error_handler($this->dbh->errorInfo());
		}
        foreach ($param as $key=>$init_val) $sth->bindParam(":$key",&$param[$key]);
        $ok = $sth->execute();
		if ($ok===false) {
			return $this->error_handler($sth->errorInfo());
		}
        return count(array_keys($param))===1?array_shift(array_values($param)):$param;
	}
	public function transaction_begin()
	{
		if (!$this->dbh) $this->connect();
		$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		$this->transaction = intval($this->dbh->beginTransaction()); // beginning of transaction
	}
	public function transaction_end()
	{
		if (!$this->dbh||!$this->transaction) return null;
		$ok = null;
		if ($this->transaction===1) {
			$ok = $this->dbh->commit();
		} else {
			$this->dbh->rollBack();
		}
		$this->transaction = 0; //end of transaction
		return $ok?$ok:$this->error_handler();
	}
	/*** error handle
	 *	@description	database error user handler, print error info and exit if it's not transaction operation, or set transation error count and return false
	 *	@input array $err	optional, the error info returned from pdo
	 *	@output	true if not error happened, false if error happened and exit scripts if error happened and no transaction setting
	***/
	public function error_handler($err=null)
	{
		$errlog = isset($this->dsn['errlog'])&&$this->dsn['errlog']?$this->dsn['errlog']:"/var/tmp/db-error.log";
		if ($err) {
			$this->error = $err;
			$err_msg = sprintf("%s\t%s\t%s\n%s\n", date('Y-m-d H:i:s'), 
				isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'Unknown', is_array($err)?serialize($err):$err, print_r(error_get_last(), true));
			error_log($err_msg, 3, $errlog);
		}
		if ($this->error&&!$this->transaction) {
			exit("Sorry, an unexpected error happened and failed to process database update. \nPlease contact your system administrator or try again later.");
		} elseif ($this->error) {
			$this->transaction *= 2; // set error if in transaction
			return false;
		}
		return true;
	}


	/*** Suffix processor:
	 *	array(
			'orderby'=>array(key1=>dirction1,key2=>direction2,...),
			'groupby'=>array(key1,key22,...),
			'limit'=>'start lenght'
		)
	***/
	protected function suffix($suffix, $prefix=null)
	{
		if (!$suffix||is_string($suffix)) return $suffix;
		$suffix_str = null;
		if (isset($suffix['groupby'])&&($groupby=$suffix['groupby'])) {
			$suffix_str .= " group by ";
			if (is_array($groupby)) {
                $groups = array();
                foreach ($groupby as $key) $groups[] = $this->prefix($key, $prefix, 'on');
                $groupby = join(",", $orders);
            } elseif (strpos($groupby, $prefix)!==0) {
                $groupby = $prefix.$groupby;
            }
            $suffix_str .= $groupby;

		}
		if (isset($suffix['orderby'])&&($orderby=$suffix['orderby'])) {
			$suffix_str .= " order by ";
			if (is_array($orderby)) {
				$orders = array();
				foreach ($orderby as $key=>$dir) $orders[] = $this->prefix($key, $prefix, 'on')." $dir";
				$orderby = join(",", $orders);
			} else {
				$orderby = $this->prefix($orderby, $prefix, 'on');
			}
			$suffix_str .= $orderby;
		}
		if (isset($suffix['limit'])&&($limit=$suffix['limit'])) {
			$suffix_str .= " limit $limit";
		}
		return $suffix_str;
	}

	/*** string field_convert(mix $fields[, string $prefix=null])
	 *	@description	convert fields into real table fields for data retrieving
	 *	@input	$fields	string for real fields (e.g. * or field1,field2,...), array for prefix dropped field array
	 *			$prefix	field prefix, null for non prefix
	 *	@return	real table fields in SQL query. e.g * or field1,field2
	***/
	protected function field_convert($fields, $prefix=null)
	{
		if (!is_array($fields)) return $fields;
		if ($prefix) {
			// has prefix, convert one by one
			for ($i=0; $i<count($fields); $i++) {
				$fields[$i] = $this->prefix($fields[$i], $prefix, 'on');
			}
		}
		return join(',', $fields);
	}
	/**
	$param sample: 
		array(
			'manager'=>'Bob',    // manager='Bob'
			'group'=>array('marketing','sales','calling'), //group in ('marketing','sales','calling')
			'age::>'=>20,  // age>20   also support operator: >=, <, <=, <>
			'reg_date::>'=>'func::date_sub(now(),INTERVAL 1 DAY)', // reg_date>date_sub(now(),INTERVAL 1 DAY)
			'::or::'=>array('level'=>3,'age'=>40), // (level=3 or age=40)
            'level::not in'=>array(3,5),  // level not in (3,5)
            'dep::like'=>array('%tsd%','5dd%'),  // (dep like '%tsd%' or dep like '5dd%')
			'level::[.]'=>array(3,5),  // level between 3 and 5
			'level::<.]'=>array(3,5),  // level> 3 and level<=5
			'level::[.>'=>array(3,5),  // level>= 3 and level<5
			'level::<.>'=>array(3,5),  // level> 3 and level<5
			'reg_date::func'=>'sysdate()', // reg_date=sysdate()
		);
	* @return array($sql_str, $bind_array)
	**/
    protected function param_scan($param, $type)
    {
		$pair = $fields = $binding = array();
		$pair = array();
		foreach ($param as $key=>$val) {
			if (preg_match("/^\w+$/", $key)) {
				if (is_array($val)) {
					$pair[] = sprintf("%s in (?%s)", $key, str_repeat(',?',count($val)-1));
					foreach ($val as $v) $binding[] = $v;
				} else {
					$fields[] = $key;
					$pair[] = "$key=?";
					$binding[] = $val;
				}
			} elseif ($type==='filter'&&is_array($val)&&preg_match("/^::or::$/i", $key)) {
				$p = array();
				foreach ($val as $k=>$v) {
					if (preg_match("/^(\w+)::func$/", $k, $px)) {
						$p[] = "$px[1]=$v";
					} else {
						$binding[] = $v;
						$p[] = "$k=?";
					}
				}
				$pair[] = '('.join(' or ', $p).')';
			} elseif (preg_match("/^(\w+)::(.*)$/", $key, $p)) {
				$op = strtolower($p[2]);
				$field = strtolower($p[1]);
				if ($type==='filter'&&is_array($val)&&in_array($op,array('in','not in','or'))) {
					if (in_array($op, array('in','not in'))) {
						$pair[] = sprintf("%s %s (?%s)", $field, $op, str_repeat(',?',count($val)-1));
						foreach ($val as $v) $binding[] = $v;
					} else {
						$pr = array();
						foreach ($val as $k=>$v) {
							$pr[] = $field.(!is_numeric($k)&&in_array($k,array('>','>=','<','<=','<>'))?$k:'=').'?';
							$binding[] = $v;
						}
						$pair[] = '('.join(' or ', $pr). ')';
					}
				} elseif ($type==='filter'&&in_array($op, array('>','>=','<','<=','<>','='))) {
					if (strpos($val, 'func::')===0) {
						// "reg_date::>"=>"func::date_sub(now(),INTERVAL 1 DAY)"
						$pair[] = "$field $op ".substr($val,6); // !!! dangerous, on developer's own risk.
					} else {
						$pair[] = "$field $op ?";
						$binding[] = $val;
					}
				} elseif ($type==='filter'&&$op=='like') {
					if (is_array($val)) {
						$likes = array();
						foreach ($val as $v) {
							$likes[] = "$feild like ?";
							$binding[] = $v;
						}
						$pair[] = '('.join(' or ', $likes).')';
					} else {
                    	$pair[] = "$field like ?";
                    	$binding[] = $val;
					}
				} elseif ($type==='filter'&&is_array($val)&&count($val)==2&&in_array($op, array('[.]','<.]','[.>','<.>'))) {
					switch ($op) {
						case '[.]':
							$pair[] = "$field between ? and ?";
							break;
						case '<.]':
							$pair[] = "$field>? and $field<=?";
							break;
						case '[.>':
							$pair[] = "$field>=? and $field<?";
							break;
						case '<.>':
							$pair[] = "$field>? and $field<?";
							break;
					}
                    $binding[] = $val[0];
					$binding[] = $val[1];
				} elseif (strtolower($op)==='func') {
					$fields[] = $field;
					$pair[] = "$field=$val";
				} else {
					$fields[] = $field;
					$pair[] = "$field=$op"; // !!! dangerous, on developer's own risk.
				}
			}
		}
		if ($type=='insert') {
			return array(array('fields'=>join(',', $fields),'values'=>sprintf("?%s", str_repeat(',?',count($fields)-1))), $binding);
		}
		$jointer = $type=='set'?',':' and ';
		return array(join($jointer, $pair), $binding);
    }

	/*** mix prefix(mix $fields, string $prefix, string $onoff)
	 *	@description	put prefix on or remove prefix from the field(s)
	 *	@input	$fields	filed names in hash array (key=>val) for multiple fields or string for single field
	 *			$prefix	prefix in string
	 *			$onoff	'on' put prefix on to field, 'off' remove prefix from field
	 *	@return	$fields with prefix options
	***/
	public function prefix($fields, $prefix, $onoff)
	{
		if (!$prefix) return $fields;	// no prefix applied
		if (!is_array($fields)) {
			// single field
			if ($onoff==='on'&&strpos($fields, $prefix)!==0) return $prefix.$fields;
			if ($onoff==='off'&&strpos($fields, $prefix)===0) return substr($fields, strlen($prefix));
			return $fields;
		}
		$hash = array();
		foreach ($fields as $key=>$val) {
			if (is_array($val)&&$key[0]==':') {  // special operator like '::or::'
				$hash[$key] = $this->prefix($val, $prefix, $onoff);
				continue;
			}
			if ($onoff==='on') {
				if (strpos($key, $prefix)!==0) $key = $prefix.$key;
			} elseif ($onoff==='off'&&strpos($key, $prefix)===0) {
				$key = substr($key, strlen($prefix));
			}
			$hash[$key] = $val;
		}
		return $hash;
	}

	public function load_table($table_def, $hook_name=null)
	{
		$prefix = strtolower(@$table_def['prefix']);
		if (substr($prefix, -1)!=='_') $prefix .= '_';
		if (isset($table_def['pkey'])&&strpos($table_def['pkey'], $prefix)===0) $table_def['key']=substr($table_def['pkey'], strlen($prefix));
    	return  new TableObject($table_def, $this);
	}
}
/*** Table Object ***
 *	Generic Database Table Operations
***/
Class TableObject
{
	protected $table = null;

	public function __construct($table, $db)
	{
		$this->table = $table;
		$this->db = $db;
	}
	public function db()
	{
		return $this->db;
	}
	public function error()
	{
		return $this->db->error;
	}
	public function read($id)
	{
		$info = null;
		if ($id) {
			$info = $this->db->table_read($this->table, $id);
		}
		return $info;
	}
	public function search($filter, $suffix=null, $fields='*', $offset=0, $limit=null)
	{
		return $this->db->table_search($this->table, $filter, $suffix, $fields, $offset, $limit);
	}
	public function count($filter)
	{
		$fields = 'count(*) as cnt';
		$r = $this->db->table_search($this->table, $filter, null, $fields);
		return intval(@$r[0]['cnt']);
	}
	public function tree($root, $deep=null, $fields='*', $filter=null)
	{
		if (!(isset($this->table['parent'])&&isset($this->table['weight']))) return null;
		if (isset($this->table['root'])&&!$deep) return $this->db->table_single_tree($this->table, $root, $fields, $filter);
		return $this->db->table_tree($this->table, $root, $deep, $fields, $filter);
	}
	public function create($param)
	{
		return is_array($param)&&count(array_keys($param))?$this->db->table_insert($this->table, $param):null;
	}
	public function truncate()
	{
		return $this->db->query("truncate table {$this->table['name']}");
	}
	public function delete($id)
	{
		return $id?$this->db->table_delete($this->table, $id):null;
	}
	public function update($id, $param)
	{
		return $id?$this->db->table_update($this->table, $id, $param):null;
	}
	/*** Instance An Active Record with given record ***/
	public function load($id)
    {
        $row = $this->db->table_read($this->table, $id);
		return new ActivRecord($this, $row);
    }
	/*** Instance An Active Record with empty record ***/
    public function new_record($param=null)
    {
		return  new ActivRecord($this, $param);
    }

}

/*** Active Record ***
 *	Implement Object Relational Mapping (ORM)
***/
Class ActivRecord
{
	private $tbl_obj = null;
	private $attr = array();
	public function __construct($tbl_obj, $attr)
	{
		$this->tbl_obj = $tbl_obj;
		$this->attr = $attr;
	}
	/*** attr getter/setter ***/
	public function attr($name=null, $val=null)
	{
		if (!isset($name)) return $this->attr; // get whole record
		if ($name&&isset($val)) {
			$this->attr[$name] = $val;
			return $this; // return self, so setter can be chained
		}
		return isset($this->attr[$name])?$this->attr[$name]:null;
	}
	public function save()
	{
		$primary = @$this->attrr[$this->tbl_obj->table['key']];
		unset($this->attrr[$this->tbl_obj->table['key']]);
		return $primary?$this->tbl_obj->update($primary, $this->attr):$this->tbl_obj->create($this->attr);
	}
	public function remove()
	{
		$primary = @$this->attrr[$this->tbl_obj->table['key']];
		return $primary?$this->tbl_obj->delete($primary):false;
	}
}
