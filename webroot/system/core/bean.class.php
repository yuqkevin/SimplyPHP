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
//	const TAG_LIBRARY = 'library';
//	const TAG_MODEL = 'model';
//	const TAG_HANDLER = 'handler';
	public function __construct($conf, $stream=null)
	{
		$this->stream = $stream;
		$this->conf = isset($conf)?$conf:$this->configure();
		$class = get_class($this);
		// dsn in configure has higher priority than dsn in model class
		if (isset($this->conf['dsn'][$class])) $this->dsn=$this->conf['dsn'][$class];
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
	/** Component Instance via URL: Switch to url on server side **/
	protected function switch_to($url=null, $param=null, $output=true)
	{
		$stream_orig = $this->stream;
		if ($url) {
			if (!$this->stream=$this->mapping($url)) $this->page_not_found($url);
		}
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
	protected $status = array('lib'=>null,'error'=>null,'error_code'=>null); // for cross-library info trnasfer
	protected $conf = null;
	protected $tbl_ini = 'db';
	protected $operator = null;	// current user
	protected $dependencies = array();	// libraries the model denpends on
    protected function __construct($conf=null)
	{
		$this->conf = $conf;
		if ($dsn=$this->dsn_parse($this->tbl_ini)) $this->load_db($dsn, $this);
		$this->load_dependencies();
	}
	public function get_error($key=null)
	{
		if (!$this->status['error_code']) return null;
		$this->status['lib'] = get_class($this);
		return isset($key)?@$this->status[$key]:$this->status;
	}
	public function trigger($event_name)
	{
		$event = $this->get_lib('LibEvent');
		if (!$event) return $event;
		return $event->trigger($event_name, $this);
	}
	/** setter/getter current operator **/
	protected function operator($operator=null)
	{
		return $operator?$this->set_operator($operator): $this->get_operator();
	}
	protected function dsn_parse($tbl_ini)
	{
		if (!$tbl_ini) return null;
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
		$r[2] = $tbl_ini;
		return $this->dsn = join('.', $r);
	}
	protected function load_db($dsn, $hook)
	{
		if (!is_object($hook)) $hook = new stdClass();
		list($dsn_name, $schema, $tbl_ini) = preg_split("/[\.\/]/", $dsn);
		$s_hook = "$dsn_name.$schema";
        $libraries = $this->global_store(CORE::HOOK_DB);
		if (isset($libraries['db'][$s_hook])) {
			$hook->db = $libraries['db'][$s_hook];
		} elseif (isset($this->conf['database'][$dsn_name])) {
			$conf = $this->conf['database'][$dsn_name];
			if (!$conf['dbdriver']) return null;
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
		if ($tbl_ini) {
			if (strpos($tbl_ini, ':')!==false) {
				$r = preg_split("/:+/", $tbl_ini, 2);
				$tbl_ini = $r[1]?$r[1]:'db';
				$folder = dirname($this->bean_file($r[0]));
			} else {
				$folder = dirname($this->bean_file(get_class($this)));
			}
			$file = $folder."/database/$tbl_ini.tbl.ini";
			if (file_exists($file)) {
				$tables = array_change_key_case(parse_ini_file($file, true), CASE_LOWER);
        	    if (!isset($hook->tbl)) $hook->tbl = new stdClass();
				foreach ((array)$tables as $table=>$def) {
					if (!isset($hook->tbl->$table)) {
                		if (!@$def['schema']) $def['schema'] = $schema;
	                	$hook->tbl->$table=$hook->db->load_table($def);
					}
				}
			} elseif ($this->conf['global']['DEBUG']) {
				$lib = get_class($this);
				$this->error("Error! Can not locate the DSN: $dsn in library $lib.: $file");
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
