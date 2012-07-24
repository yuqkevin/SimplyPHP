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
	const HOOK_LANGUAGE = 'client_language';	// hook in $GLOBALS for language dictionary

	public function __construct($conf)
	{
		// merge dependencies defined in parent classes
		$parent = get_parent_class($this);
		while (preg_match("/^Mo[A-Z]/", $parent)) {
			$vars = get_class_vars($parent);
			$this->dependencies = array_merge((array)$this->dependencies, (array)@$vars['dependencies']);
			$parent = get_parent_class($parent);
		}
		// then loading dependencies
		$this->conf = isset($conf)?$conf:$this->configure();
		// loading libraries
		$this->load_dependencies();
		$this->initial();
	}
	/*** mix switch_to([string $url=null[, array $attr=null[,bool $output=null]]])
	 *	@decription		loading corresponding component for given url
	 *	@input	string	$url	using current url if url is omitted
	 *			array	$attr	give parameters array('conf'=>array(key1=>val1,...),'param'=>array(v1,v2,...))
	 *			bool	$output	output control, true for client side output,false for returns string
	 *	@return	output to client if $output is true, or string if $output is false
	***/
	public function switch_to($url=null, $attr=null, $output=true)
	{
        if (!$url) $url = $this->env('PATH');
		// verify URL
        if (!$stream=$this->mapping($url, true)) {
            if ($output) $this->page_not_found($url);
            return "<a title=\"$url\" style=\"color:red;\">".$this->language_tag('UNKNOWN_REQUEST')."</a>";
        }
		if (is_array($attr['conf'])) $stream['conf']=array_merge((array)$stream['conf'], $attr['conf']);
		if (is_array($attr['param'])) $stream['param']=array_merge((array)$stream['param'], $attr['param']);
		return $this->component($stream, $output);
	}
	/*** string load_component(string $model, string $method[, array $conf=null[,bool $output=false]])
	 *	@description	Loading given component by return content in string
	 *	@input	$model	model name
	 *			$metho	method name
	 *			$conf	attributes: array(key1=>val1,...)
	 *			bool	$output	output control, true for client side output,false for returns string
	 *	@return	content of component
	***/
	public function load_component($model, $method, $conf=null, $output=false)
	{
		// verify component existence
		if ($stream=$this->component_locator($model, $method)) {
			$stream['conf'] = $conf;
			return $this->component($stream, $output);
		}
		return $output?$this->page_not_found("$model::$method"):"<a title=\"$model::$method\" style=\"color:red;\">".$this->language_tag('UNKNOWN_COMPONENT')."</a>";
	}
	protected function initial(){}	// reserved for customization
	/** Handler Locator **/
	protected function handler()
	{
		if (substr($this->stream['method_file'], -8)!=='.inc.php') $this->stream['method_file'] .= '.inc.php';
		if (file_exists($this->stream['method_file'])) {
			include($this->stream['method_file']);
		}
		return $this->stream;
	}
	/*** string language_tag(string $tag)
	 *	@description find a translation for given tag in client language
	 *	@input	$tag	tag name
	 *	@return	translation of tag
	***/
	protected function language_tag($tag)
	{
		if (!$language=$this->cookie('language')) $language='en';
		if (!$dics=$this->globals(self::HOOK_LANGUAGE)) {
			$dic_file = APP_DIR.'/conf/languages/'.$language.'.ini';
			if (file_exists($dic_file)) {
				$dics = parse_ini_file($dic_file);
				$this->globals(self::HOOK_LANGUAGE, $dics);
			} else {
				$dics = array();
			}
		}
		return @$dics[$tag];
	}
	/*** verify model access via domain setting in configure file ***/
	private function domain_verify($model)
	{
		if (!isset($this->conf['domain'][$model])) return true; // no domain restriction
		$domains = preg_split("/,/", preg_replace("/\s/", "", strtolower($this->conf['domain'][$model])));
		return in_array($this->env('DOMAIN'), $domains);
	}
	/*** mix component(array $stream[, bool $output=true])
	 *	@description	instantiating a component by give stream
	 *	@input	array $stream	component definition with parameters
	 *			bool  $output	output control, output to client if true, or returns content in string.
	 *	@return	output to client if true, or returns content in string.
	***/
	protected function component($stream, $output=true)
	{
		$error = $this->language_tag('ACCESS_DENIED');
        // Verify model visibility by configuration
        if (!$this->model_verify($stream['model'])) {
            $message = $error.($this->conf['global']['DEBUG']?('[model:'.$stream['model'].']'):null);
			if ($output) $this->output($message,'html');
			return "<a title=\"$message\" style=\"color:red;\">$error</a>";
        }
		if (!$this->action_verify($stream['model'], $stream['method'])) {
            $message = $error.($this->conf['global']['DEBUG']?('[model:'.$stream['model'].'::'.$stream['method'].']'):null);
			if ($output) $this->output($message,'html');
			return "<a title=\"$message\" style=\"color:red;\">$error</a>";
		}
        // set cache flag off first
        $stream['cache'] = isset($stream['cache'])?intval($stream['cache']):(isset($stream['conf']['cache'])?intval($stream['conf']['cache']):0);
        $handler_id = $this->env('DOMAIN').serialize($stream).serialize($_REQUEST);
        $stream['cache_handler'] = strlen($handler_id).bin2hex(md5($handler_id));
        // check cacke first
        $content = $this->cache($stream['cache_handler']);
        if (!$content) {
            // fresh loading
			if (isset($this->conf['global']['benchmark'])&&$this->conf['global']['benchmark']) {
				$watch_point = $stream['model'].'::'.$stream['method'];
				$this->benchmark($watch_point, true);
			}
			$app = $this->load_model($stream['model']);
			$orig_stream = $app->stream;
			$app->stream = $stream;
		//	$app = new $stream['model']($this->conf, $stream);
            $stream = $app->handler();
            $content = $app->load_view($stream['view_file'], @$stream['data'], @$stream['suffix']);
            // check cache contol
            if (intval($stream['cache'])>0) {
                // cache content
                $app->cache($stream['cache_handler'], $content, intval($stream['cache']));
            }
			$app->stream = $orig_stream;
			if (isset($watch_point)) {
				$this->benchmark($watch_point);
				if ($output) $this->benchmark_log();
			}
        }
		return  $output?$app->output($content, @$stream['format']):$content;
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

	/*** string token_key([int $length=8[,string $salt=null]])
	 *	@description generate key for token by given salt (optional), default salt is salt in conf file
	 *	@input	$int	length of key in character between 4 ~ 32, default is 8 chars
	 *			$salt	salt for ken generating
	 *	@return	key in string
	***/
	protected function token_key($length=8, $salt=null)
	{
		if (!$salt) $salt = $this->conf['global']['salt'];
		$length = max(4, min(32, $length));
		return substr(md5($this->session('id')).$salt, 0, $length);
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
	    $this->stream['view_file'] = null;
    	$this->stream['format'] = 'json';
	    $this->stream['data'] = array('success'=>false,'message'=>null);
	}
	/*** array component_list($class_name)
	 *	@description	get list of component for given model
	 *	@input	bool $class_name 	model class_name 
	 *	@return	array 	component array like array(array('model1'=>array('method1'=>'desc','method2'=>'desc',..,),...,'modelN'=>...)
	***/
	protected function component_list($class_name)
	{
		$bean_root=$root=APP_DIR.'/resource/beans';
		$root = dirname($this->bean_file($class_name, false));	// the root of given bean
		if (!file_exists("$root/model.class.php")) return array(); // no model exists.
		$components = array();
		$model = $this->class_name_format($class_name);
		foreach (glob("$root/handler/*.inc.php") as $file) {
			$method = array_shift(preg_split("/\./", basename($file)));
			$desc = preg_match("/\*\s+@description\s([^\n]+)/msi", file_get_contents($file), $p)?trim($p[1]):'';
			$components["$model::$method"] = $desc;
		}
		return $components;
	}
	protected function bean_list($active=true)
	{
        $bean_root = APP_DIR.'/'.($active?'beans':'resource/beans');
        $bean_folders = $this->_bean_dir_scan($bean_root);
		$beans = array();
		foreach ($bean_folders as $bean_folder) {
			$name = str_replace(' ','', ucwords(join(' ', preg_split("/\//", substr($bean_folder, strlen($bean_root))))));
			$desc = file_exists("$bean_folder/README")?file_get_contents("$bean_folder/README"):'';
			$beans[$name] = $desc;
		}
		return $beans;
	}
	private function _bean_dir_scan($bean_root, $recursive=true)
	{
		$beans = array();
		foreach (glob("$bean_root/*", GLOB_ONLYDIR) as $dir) {
			if ($dir=='.'||$dir=='..') continue;
			if (count(glob("$dir/*.class.php")))  {
				$beans[] = $dir;
				if ($recursive) $beans = array_merge($beans, $this->_bean_dir_scan($dir, $recursive));
			}
		}
		return $beans;
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
        $libraries = $this->globals(CORE::HOOK_DB);
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
			$this->globals(CORE::HOOK_DB, $libraries);
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
}
