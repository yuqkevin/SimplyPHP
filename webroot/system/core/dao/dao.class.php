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
// | name     | Table name    e.g. auth_user, production.sports_car
// +---------+-------------------------------------------------------------------------------------------------------+
// | pkey     | Name of primary key field     
// +---------+-------------------------------------------------------------------------------------------------------+
// | prefix     | Prefix for all fields    (Optional), it's applied to all fields if defined here.
// +---------+-------------------------------------------------------------------------------------------------------+
// | seq     | Sequence for primary key    avfailable values:auto/null/sequence name. auto using maximum primary key +1.
// +---------+-------------------------------------------------------------------------------------------------------+
// | schema     | Schema of table and sequence if applied    (Optional)
// +---------+-------------------------------------------------------------------------------------------------------+
// | parent     | Name of field which is defined in tree structure table.     
// +---------+-------------------------------------------------------------------------------------------------------+
// | weight     | Weight number for rank in tree structre table    Bigger in weight then higher in rank.
// +---------+-------------------------------------------------------------------------------------------------------+

// Data Source Name (DSN) Array Definition
// +----------+---------------------------------------------------------------------+
// | Key      | Description
// +----------+---------------------------------------------------------------------+
// | hostname | Server Name    'localhost', domain name or any defined in hosts file
// +----------+---------------------------------------------------------------------+
// | username | User Name     
// +----------+---------------------------------------------------------------------+
// | password | Password
// +----------+---------------------------------------------------------------------+
// | database | Database or Schema Name     
// +----------+---------------------------------------------------------------------+
// | dbdriver | Name of Database Driver    mysql,oci,pgsql,...,etc.
// +----------+---------------------------------------------------------------------+

class Dao
{
    public $dbh = null;
    public $transaction = 0; // 0:non-transaction, 1:in transaction, 2 or others: error happened in transaction
    public $error = null;    // error info for debugging
    protected $dsn = null;
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
            $dbh = new PDO($dsn_str, $dsn['username'], $dsn['password']);
            $dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
            if ($pure_connet_flag) return $dbh;
            $this->dbh = $dbh;
        }
        return $this->dbh;
    }
    public function table_read($table_def, $id, $fields='*')
    {
        if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
        if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
        $sth = $this->dbh->prepare("select $fields from $table_name where {$table_def['pkey']}=?");
        $sth->execute(array($id));
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        $sth->closeCursor();
        return $row;
    }
    public function table_update($table_def, $id, $param)
    {
        $this->error = null;
        if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
        if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";

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
            $this->error = $this->dbh->errorInfo();
            $this->transaction *= 2; // set error if in transaction
            return false;
        }
        $ok = $sth->execute(array_merge($binding,$binding2));
        if ($ok===false) {
            $this->error = $sth->errorInfo();
            $this->transaction *= 2; // set error if in transaction
        }
        return $ok;
    }
    public function table_delete($table_def, $id)
    {
        $this->error = null;
        if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
        if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
        $query = "delete from $table_name where ";
        if (is_array($id)) {
            list($filter_str, $binding) = $this->param_scan($id, 'filter');
            $query .= $filter_str;
        } else {
            $query .= "{$table_def['pkey']}=?";
            $binding = array($id);
        }
        $sth = $this->dbh->prepare($query);
        if (!$sth) {
            $this->error = $this->dbh->errorInfo();
            $this->transaction *= 2; // set error if in transaction
            return false;
        }
        $cnt = $sth->execute($binding);
        if ($cnt===false) {
            $this->error = $sth->errorInfo();
            $this->transaction *= 2; // set error if in transaction
        }
        return $cnt;
    }
    public function table_insert($table_def, $param)
    {
        if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
        if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
        $pkey = $table_def['pkey'];
        if (!isset($param[$pkey])||!$param[$pkey]) {
            if ($seq=@$table_def['seq']) {
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
            $this->error = $this->dbh->errorInfo();
            $this->transaction *= 2; // set error if in transaction
            return false;
        }
        $ok = $sth->execute($binding);
        if ($ok===false) {
            $this->error = $sth->errorInfo();
            $this->transaction *= 2; // set error flag if in transaction
            return false;
        }
        if (isset($param[$pkey])&&$param[$pkey]) return $param[$pkey];
        return $this->dbh->lastInsertId($pkey);
    }
    public function table_search($table_def, $filter, $suffix=null, $fields='*', $offset=0, $limit=null)
    {
        if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
        if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
        $binding = array();

        $query = "select $fields from $table_name";
        if ($filter) {
            list($filter_str, $binding) = $this->param_scan($filter, 'filter');
            $query .= " where $filter_str";
        }
        $query .= ' '.$suffix;
        $attr = $offset?array(PDO::ATTR_CURSOR=>PDO::CURSOR_SCROLL):array();
        $sth = $this->dbh->prepare($query, $attr);
        if (!$sth) {
            $this->error = $this->dbh->errorInfo();
            return false;
        }
        $ok = $sth->execute($binding);
        if ($ok===false) {
            $this->error = $sth->errorInfo();
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
            $lines[] = $r;
            if (isset($limit)&&++$cnt>=$limit) {
                $sth->closeCursor();
                break;
            }
        }
        return count($lines)?$lines:null;
    }
    /** Retrieve tree under given root (root excluded)**
     * deep: null whole tree, others: the level under root will be retrieved (start from 1);
    **/
    public function table_tree($table_def, $root, $deep=null, $fields='*', $filter=null, $level=1)
    {
        if (!$this->dbh) $this->connect();
        $table_name = $table_def['name'];
        if (isset($table_def['schema'])) $table_name = $table_def['schema'].".$table_name";
        $field_id = $table_def['pkey'];
        $field_parent = $table_def['parent'];
        $field_weight = $table_def['weight'];
        if (is_array($filter)) {
            $filter[$field_parent] = $root;
        } else {
            $filter = array($field_parent=>$root);
        }
        list($filter_str, $binding) = $this->param_scan($filter, 'filter');
        $sth = $this->dbh->prepare("select $fields,$level as LEVEL from $table_name where $filter_str order by $field_weight desc");
        if (!$sth) {
            $this->error = $this->dbh->errorInfo();
            return false;
        }
        $ok = $sth->execute($binding);
        if ($ok===false) {
            $this->error = $sth->errorInfo();
            return false;
        }
        $lines = array();
        if (isset($deep)) $deep--;
        while ($node=$sth->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = $node;
            if (!isset($deep)||$deep>0) {
                $lines = array_merge($lines, $this->table_tree($table_def, $node[$field_id], $deep, $fields, $filter, $level+1));
            }
        }
        return $lines;
    }
    public function query($query, $param=null)
    {
        if (!$this->dbh) $this->connect();
        $sth = $this->dbh->prepare($query);
        if (!$sth) {
            $this->error = $this->dbh->errorInfo();
            return false;
        }
        $ok = $sth->execute($param);
        if ($ok===false) {
            $this->error = $sth->errorInfo();
        }
        return $ok;
    }
    public function procedure($query, $param)
    {
        if (!$this->dbh) $this->connect();
        $sth = $this->dbh->prepare($query);
        if (!$sth) {
            $this->error = $this->dbh->errorInfo();
            return false;
        }
        foreach ($param as $key=>$init_val) $sth->bindParam(":$key",&$param[$key]);
        $ok = $sth->execute();
        if ($ok===false) {
            $this->error = $sth->errorInfo();
            return false;
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
        return $ok;
    }

    public function load_table($table_def)
    {
        return  new TableObject($table_def, $this);
    }

    /**
    $param sample: 
        array(
            'manager'=>'Bob',    // manager='Bob'
            'group'=>array('marketing','sales','calling'), //group in ('marketing','sales','calling')
            'age::>'=>20,  // age>20   also support operator: >=, <, <=, <>
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
                } elseif ($type==='filter'&&in_array($op, array('>','>=','<','<=','<>'))) {
                    $pair[] = "$field $op ?";
                    $binding[] = $val;
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
}

/*** Table Object ***
 *    Generic Database Table Operations
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
            $info = $this->prefix($info, 'off');
        }
        return $info;
    }
    public function search($filter, $suffix=null, $fields='*', $offset=0, $limit=null)
    {
        $filter = $this->prefix($filter, 'on');
        if ($lines=$this->db->table_search($this->table, $filter, $suffix, $fields, $offset, $limit)) {
            if (is_array($lines)) {
                if (@$this->table['prefix']) {
                    for ($i=0; $i<count($lines); $i++) $lines[$i] = $this->prefix($lines[$i],'off');
                }
            }
        }
        return $lines;
    }
    public function count($filter)
    {
        $filter = $this->prefix($filter, 'on');
        $fields = 'count(*) as cnt';
        $r = $this->db->table_search($this->table, $filter, null, $fields);
        return intval(@$r[0]['cnt']);
    }
    public function tree($root, $deep=null, $fields='*', $filter=null)
    {
        if (!(isset($this->table['parent'])&&isset($this->table['weight']))) return null;
        if (is_array($filter)) $filter = $this->prefix($filter, 'on');
        if ($lines=$this->db->table_tree($this->table, $root, $deep, $fields, $filter)) {
            if (@$this->table['prefix']) {
                for ($i=0; $i<count($lines); $i++) $lines[$i] = $this->prefix($lines[$i],'off');
            }
        }
        return $lines;
    }
    public function create($param)
    {
        $param = $this->prefix($param, 'on');
        return is_array($param)&&count(array_keys($param))?$this->db->table_insert($this->table, $param):null;
    }
    public function truncate()
    {
        return $this->db->query("truncate table {$this->table['name']}");
    }
    public function delete($id)
    {
        if (is_array($id)) {
            $id = $this->prefix($id, 'on');
        }
        return $id?$this->db->table_delete($this->table, $id):null;
    }
    public function update($id, $param)
    {
        $param = $this->prefix($param, 'on');
        return $id?$this->db->table_update($this->table, $id, $param):null;
    }
    public function prefix($in, $onoff)
    {
        $prefix = strtolower(@$this->table['prefix']);
        if (!$prefix||!$in) return $in;
        if (substr($prefix, -1)==='_') $prefix = substr($prefix,0, -1);
        $hash = array();
        foreach ((array)$in as $key=>$val) {
            if (is_array($val)&&$key[0]==':') {  // special operator like '::or::'
                $hash[$key] = $this->prefix($val, $onoff);
                continue;
            }
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
