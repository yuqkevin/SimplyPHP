<?php
// -------------------------------------------------------------------------------+
// | Name: Model - Base class and common mthods shared by application modules     |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// -------------------------------------------------------------------------------+
// | Repository: https://github.com/yuqkevin/SimplyPHP/                           |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu                                                         |
// -------------------------------------------------------------------------------+
// | Checkout: 2011.01.19                                                         |
// -------------------------------------------------------------------------------+
//

// Root for application models.
// All application libraries should use namespace under Model
// e.g class Class1Class2Class3 extends Model {}
// the class file should be app/model/class1/class2/class3.class.php

class Model extends Web
{
	public $stream = array();

	protected $dependencies = array();	// libraries the model denpends on
	const HOOK_MODEL = 'model';

	public function __construct($conf, $stream=null)
	{
		// merge dependencies defined in parent classes
		$parent = get_parent_class($this);
		while (preg_match("/^Mo[A-Z]/", $parent)) {
			$vars = get_class_vars($parent);
			$this->dependencies = array_merge((array)$this->dependencies, (array)@$vars['dependencies']);
			$parent = get_parent_class($parent);
		}
		// then loading dependencies
		$this->stream = $stream;
		$this->conf = isset($conf)?$conf:$this->configure();
		// loading libraries
		$this->load_dependencies();
		$this->initial();
	}
	public function boot()
	{
		return $this->switch_to();
	}
	protected function initial(){}	// reserved for customization
	/** Handler Locator **/
	protected function handler()
	{
		if (!$this->domain_verify($this->stream['model'])) $this->error("Error: trying to access model {$this->stream['model']} in non-authorized domain.");
		$handler = $this->stream['method_file'].'.inc.php';
		$this->stream['url'] = isset($this->stream['url'])?$this->stream['url']:$this->request('_PATH');
		if (file_exists($handler)) {
			include($handler);
		}
		return $this->stream;
	}
	/*** verify model access via domain setting in configure file ***/
	private function domain_verify($model)
	{
		if (!isset($this->conf['domain'][$model])) return true; // no domain restriction
		$domains = preg_split("/,/", preg_replace("/\s/", "", strtolower($this->conf['domain'][$model])));
		return in_array($this->env('DOMAIN'), $domains);
	}
	/*** operator_verify([array $conditions])
	 *	@desc	check operator properties, returns operator info array if all conditions match, otherwise returns false
	 *	@input	$conditions	associative array, e.g. array('dna'=>1,'group'=>array('domain'=>3))
	 *	@return	bool false
	***/
	protected function operator_verify($conditions=null)
	{
		if (!$operator=$this->get_operator()) return false;
		if (!isset($operator['id'])) return false;
		if (!$conditions) return $operator;
		foreach ($conditions as $key=>$val) {
			if (is_array($val)) {
				foreach ($val as $k=>$v) if ($operator[$key][$k]!=$v) return false;
			} elseif ($operator[$key]!=$val) {
				return false;
			}
		}
		return $operator;
	}
	/** Mapping URL to Component **/
	protected function switch_to($url=null, $param=null, $output=true)
	{
		$stream_orig = $this->stream;
		if (!$url) $url = $this->stream['url'];
		if (!$this->stream=$this->mapping($url, true)) $this->page_not_found($url);
		$this->stream['conf'] = $param;
		// caching controle
		// disable component cache first, then enable in handler if needed.
		// to enable component cache, set $this->stream['cache'] with ttl in seconds. 0 for permanent caching
		$this->stream['cache'] = false;
		$handler_id = $this->env('DOMAIN').serialize($this->stream).serialize($_REQUEST);
		$this->stream['cache_handler'] = strlen($handler_id).bin2hex(md5($handler_id));
		// check cacke first
		$content = $this->cache($this->stream['cache_handler']);
		if ($content===false) {
			// no caching or expired
			$this->handler();
			$content = $this->load_view($this->stream['view'], $this->stream['data'], $this->stream['suffix']);
			// check cache contol
			if ($this->stream['cache']!==false) {
				// cache content
				$this->cache($this->stream['cache_handler'], $content, intval($this->stream['cache']));
			}
		}
		$format = $this->stream['format'];
		//if ($format=='html') $content = "<div class=\"w3s-component\" id=\"_w3s_component".$this->sequence(1)."\">$content</div>";
		$this->stream = $stream_orig;
		return $output?$this->output($content, $format):$content;
	}

	/*** mix component_param(mix $keys)
	 *	@description get component parameters via http,configure, url
	 *		Should be called from inside of component (method)
	***/
	protected function component_param($keys)
	{
		if (!is_array($keys)) $keys = array($keys);
		$vals = array();
		foreach ($keys as $key) $vals[$key]=null;
		if ($val=$this->request($keys[0])) {	// check post/get frist
			$vals[$keys[0]] = $val;
			for ($i=1; $i<count($keys); $i++) $vals[$keys[$i]] = $this->request($keys[$i]);
		} elseif ($val=@$this->stream['conf'][$keys[0]]) {	// check component configure
			$vals[$keys[0]] = $val;
			for ($i=1; $i<count($keys); $i++) $vals[$keys[$i]] = @$this->stream['conf'][$keys[$i]];
		} else {	// check url
			for ($i=0; $i<count($this->stream['param']); $i++) {
				if (in_array($this->stream['param'][$i], $keys)) $vals[$keys[$i]] = @$this->stream['param'][$i+1];
			}
		}
		return count($keys)==1?$vals[$keys[0]]:$vals;
	}
	/*** Simple Xor encrypt/decrypt using session id based key, mainly for token***/
	protected function s_encrypt($plain_text, $key=null)
	{
		$key = md5($key.$this->session('id'));
		return bin2hex($this->xor_crypt($key, $plain_text));
	}
	protected function s_decrypt($enc_str, $key=null)
	{
		if (!strlen($enc_str)) return $enc_str;
		$key = md5($key.$this->session('id'));
		return $this->xor_crypt($key, pack('H*',$enc_str));
	}
	/** Set Stream to json format for ajax request **/
	protected function ajax()
	{
		$this->stream['ajax'] = true;
	    $this->stream['view'] = null;
    	$this->stream['format'] = 'json';
	    $this->stream['data'] = array('success'=>false,'message'=>null);
	}
}
// Root for application libraries.
// All application libraries should use namespace under Library
// e.g class Class1Class2Class3 extends Library {}
// the class file should be app/library/class1/class2/class3.class.php
class Library extends Core
{
	protected $conf = null;
	protected $operator = null;	// current user
	protected $dependencies = array();	// libraries the model denpends on
    public function __construct($conf=null)
	{
		// merge dependencies defined in parent classes
		$parent = get_parent_class($this);
		while (preg_match("/^Lib[A-Z]/", $parent)) {
			$vars = get_class_vars($parent);
			$this->dependencies = array_merge((array)$this->dependencies, (array)@$vars['dependencies']);
			$parent = get_parent_class($parent);
		}
		// then loading database, dependencies
		$this->conf = $conf;
		if ($dsn=$this->dsn_parse()) $this->load_db($dsn, $this);
		$this->load_dependencies();
	}
	public function get_error($key=null)
	{
		if (!$this->status['error_code']) return null;
		$this->status['class'] = get_class($this);
		return isset($key)?@$this->status[$key]:$this->status;
	}
	/*** mix trigger(String $event_name)
	 *	@description trigger an pre-defined event
	 *	@input	String $event_name	Event Name
	 *	@return int number of listners on the event
	***/
	public function trigger($event_name)
	{
		$event = $this->get_lib('LibEvent');
		if (!$event) return $event; // event bean not installed
		return $event->trigger($event_name, $this);
	}
	/*** mix add_listner(String $event_name[, array $scope=null[, int $notify=0[, String $handler]]])
	 *	@description	Add a listner to the event.
	 *	@input	String $event_name	Event Name
	 *			array $scope	Effective Scope
	 *			int $notify		Notice type
	 *			String $handler Callback handler
	 *	@return see LibEvent::add_listner()
	***/
	public function add_listner($event_name, $scope=null, $notify=0, $handler=null)
	{
        $scope = (array) $scope;
        foreach (array('domain','dna','user') as $key) if (!isset($scope[$key])) $scope[$key] = 0;
		$event = $this->get_lib('LibEvent');
		if (!$event) return $event; // event bean not installed
		$class_name = get_class($this);
		return $event->add_listner($event_name, $class_name, $scope, $notify, $handler);
	}
	/** setter/getter current operator **/
	protected function operator($operator=null)
	{
		return $operator?$this->set_operator($operator): $this->get_operator();
	}

	/*** string dsn_parse()
	 *	@description	get dsn string in standard format (e.g. default.schema) based on dsn configure
	 *	@return		string entry_name.schema
	***/
	protected function dsn_parse()
	{
		$class = get_class($this);
		$folder = preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $class);
		$dsn = null;
        if (isset($this->conf['dsn'][$class])) {
			$dsn = $this->conf['dsn'][$class];
		} else {
			$path = preg_split("/\//", $folder);
			while (count($path)) {
				array_pop($path);
				$name = join('',$path).'*';
				if (isset($this->conf['dsn'][$name])) {
					$dsn = $this->conf['dsn'][$name];
					break;
				}
			}
		}
		if (!$dsn) $dsn = 'default.'.$this->conf['database']['default']['database'];
		$r = preg_split("/\./", $dsn);
		if (!isset($r[1])||!$r[1]) $r[1] = $this->conf['database'][$r[0]]['database'];
		return $r[1]?$this->dsn = join('.', $r):null;
	}
	protected function load_db($dsn, $hook)
	{
		// load db driver first
		if (!is_object($hook)) $hook = new stdClass();
		list($dsn_name, $schema) = preg_split("/[\.\/]/", $dsn);
		$s_hook = "$dsn_name.$schema";
        $libraries = $this->global_store(CORE::HOOK_DB);
		if (isset($libraries['db'][$s_hook])) {
			$hook->db = $libraries['db'][$s_hook];
		} elseif (isset($this->conf['database'][$dsn_name])) {
			$conf = $this->conf['database'][$dsn_name];
			if (!$conf['dbdriver']) return null;	// no driver given
			$folder = dirname(__FILE__).'/dao';
			$dbdriver = ucfirst(strtolower($conf['dbdriver']));
			$driver_file = $folder.strtolower("/$dbdriver.class.php");
			if (!file_exists($driver_file)) {
				$driver_file = $folder."/dao.class.php"; // using default (generic) db class
				$dbdriver = 'Dao';
			}
			include_once($driver_file);
			$libraries['db'][$s_hook] = new $dbdriver($conf);
			$this->global_store(CORE::HOOK_DB, $libraries);
			$hook->db = $libraries['db'][$s_hook];
		} else {
			$this->error("Error! Invalid DSN: $conf.");
		}
		// hook table objects defined in current class and parent classes
		$folder = dirname($this->bean_file(get_class($this)));
		$ini_files = array();
		while (strpos($folder, APP_DIR.'/beans/')===0) {
			$tbl_ini_file = $folder."/database/db.tbl.ini";
			if (file_exists($tbl_ini_file)) $ini_files[] = $tbl_ini_file;
			$folder = dirname($folder);
		}
		foreach ($ini_files as $ini_file) {
        	if (!isset($hook->tbl)) $hook->tbl = new stdClass();
			$tables = array_change_key_case(parse_ini_file($ini_file, true), CASE_LOWER);
			foreach ((array)$tables as $table=>$def) {
				if (!isset($hook->tbl->$table)) {
            		if (!@$def['schema']) $def['schema'] = $schema;
	            	$hook->tbl->$table=$hook->db->load_table($def);
				}
			}
		}
		return $hook;
	}
    /** Verify dna on a table entry		for table which has dna field
     * @input   string  $tbl table object name
     *          int  $id entry_id
     * @return  bool/array true/entry_record: ok, false: invalid owner
    ***/
    protected function dna_verify($tbl=null, $id=0)
    {
		$operator = $this->get_operator();
        if (!$dna=@$operator['dna']) {
            $this->status['error_code'] = 'NO_DNA';
            $this->status['error'] = "DNA required to access table: $tbl.";
            return false;
        }
        if (!$id||!$tbl) return true;	// for create entry or action other than action without dna filter (e.g modify/delete)
        if (is_object($this->tbl->$tbl)) {
            $entry = $this->tbl->$tbl->read($id);
            if ($operator['dna']==LibAclUser::DNA_SYS||$entry['dna']==$dna) return $entry?$entry:true; // force to return true if record does not exit
            $this->status['error_code'] = 'INVALID_DNA';
            $this->status['error'] = "The operator's dna does not match $tbl's dna.";
            return false;
        }
        $this->status['error_code'] = 'NO_TABLE';
        $this->status['error'] = "The table $tbl does not exist.";
        return false;
    }
}
