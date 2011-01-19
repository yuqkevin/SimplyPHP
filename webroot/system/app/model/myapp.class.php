<?php
/** My first application module  **/
class Myapp extends App
{
    protected $mytables = array(
        'user'  => array('name'=>'app_user','pkey'=>'user_id', 'schema'=>null),
    );
	function initial()
	{
		$this->db = $this->load_db($this->conf['dsn']['default']);
	}
    function session_auth()
	{
	}
	function user_proc($userid, $param=null)
	{
		if (!$userid) return null;
		return $this->db->table_proc($this->mytables['user'], $userid, $param);
	}
	function user_search($filter, $suffix=null, $fields='*')
	{
		return $this->db->table_search($this->mytables['user'], $filter, $suffix, $fields='*');
	}
}
