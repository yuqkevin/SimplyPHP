<?php
// -------------------------------------------------------------------------------+
// | Name: Core - I/O class for both Controller and App model                     |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// -------------------------------------------------------------------------------+
// | Repository: https://github.com/yuqkevin/SimplyPHP/                           |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu                                                         |
// -------------------------------------------------------------------------------+
// | Checkout: 2011.01.19                                                         |
// -------------------------------------------------------------------------------+
// | PHP 5: >=5.2.1                                                               |
// -------------------------------------------------------------------------------+
//
Class Core
{
	protected $conf = null;
	protected $dependencies = null;  // array(LibClassName=>name)  invoke: this->lib->name
	const HOOK_LIB = 'library';
	const HOOK_DB = 'database';
	const HOOK_VAR = 'w3s_var';
	const DEFAULT_ENTRY = 'main';
	const W3S_SEQ = 'w3s_sequence';
	const REQUEST_SESSION = 'w3s_request';	// for input cache

	/*** mix env(string $name[, mix $val[, bool $session]])
	 *	@description:	Application Env Variable getter/setter. The variable has type of Reserved/Global/Session
	 *	@input	string $name	variable name
	 *			string $val		value for setter
	 *			bool   $session	scope control for customer variable: true if in session only, otherwise check by order:Reserved/Global/Session
	 *	@return	mix	returns value for given name
	 ***/
	public function env($name, $val=null, $session=false)
	{
		$env_name = "env:$name"; // using 'env:' to aviod name conflict with regular variable in hook
		if ($session) return $this->session($env_name, $val);	// for explicit session varaibles
		// otherwise, check hook in order:  W3S Reserve(readonly) -> $_SERVER(readonly) -> $GLOBALS -> session
		if ($name==='DOMAIN') {
			$r = preg_split("/\./", strtolower($_SERVER['HTTP_HOST']));
			if ($r[0]==='www') array_shift($r);
			return join('.', $r);
		} elseif ($name==='URL') {
			return $this->request('_URL');
		} elseif ($name==='PATH') {
			return $this->request('_PATH');
		} elseif (isset($_SERVER[$name])) {
			return $_SERVER[$name];
		}
		// check global and session
        $HOOK = self::HOOK_VAR;
		if (!isset($GLOBALS[$HOOK][$env_name])&&$this->session($env_name)) return $this->session($env_name, $val);		// find pre-defined in session only
		// use global hook as default
		return self::_hook_var(&$GLOBALS, $env_name, $val);
	}
	// session getter/setter
    public function session($name, $val=null)
    {
        if (!session_id()) session_start();
		if ($name==='id') return session_id();
        $HOOK = self::HOOK_VAR;
        if ($name=='clear') {
            unset($_SESSION[$HOOK]);
            return true;
        }
		return self::_hook_var(&$_SESSION, $name, $val);
    }
	private function _hook_var(&$scope, $name, $val)
	{
		$HOOK = self::HOOK_VAR;
        if (!isset($scope[$HOOK])) $scope[$HOOK]=array();
        if (!isset($val)) return isset($scope[$HOOK][$name])?$scope[$HOOK][$name]:null;
        if ($val=='clear') $val=null;
        if ($val=='reset') $val='';
        return $scope[$HOOK][$name]=$val;
	}
    /** Unique number generator **/
    public function sequence($offset=0, $schema=null)
    {
		if (!$schema) $schema = self::W3S_SEQ;
        if ($offset=='reset') return self::session($schema, 1);
        $seq = intval(self::session($schema)) + $offset;
        if ($offset) self::session($schema, $seq);
        return $seq;
    }

	/*** mix request(string $name[, string $method[,mix $init_val]])
	 *	@description	get request parameters. method: get,post,session,set. post>get if non method given. post>get>session if method is 'session'
	 *	@input	string $name	variable name (key), there are several reserved key name which in upper case and start with '_':
	 *							_POST/_GET: return all POST/GET array
	 *							_SESSION: return all request array which has session support fetaure
	 *							_DOMAIN/_URL/_PATH: return http_host, http full request, request without http_host
	 *			string $method	where the request coming from. 'post','get','session','set'.
	 *							'session' and 'set' are for request which has session support
	 *							if method is omitted, the check sequence is post->get->session in which the post has highest priority.
	 *	@return	mix	the request var
	***/
	public function request($name, $method=null, $init_val=null)
	{
		$method = strtolower($method);
		#if ($method&&!$name) {
		#	return $method=='get'?$_GET:self::_postvars($_POST);
		#}
		if ($name==='_POST') {
			return self::_postvars($_POST);
		} elseif ($name==='_GET') {
			if (isset($_GET['_ENTRY'])) unset($_GET['_ENTRY']);
			return $_GET;
		} elseif ($name==='_SESSION') {
			return self::session(self::REQUEST_SESSION);
		} elseif ($name==='_DOMAIN') {
            $r = preg_split("/\./", strtolower($_SERVER['HTTP_HOST']));
            if ($r[0]==='www') array_shift($r);
            return join('.', $r);
		} elseif ($name==='_URL') {
                return (@$_SERVER['HTTPS']?'https':'http').'://'.$_SERVER['HTTP_HOST'].(isset($_GET['_ENTRY'])?$_GET['_ENTRY']:'/');
        } elseif ($name==='_PATH') {
                return isset($_GET['_ENTRY'])?$_GET['_ENTRY']:'/';
		}
		
		if ($method=='get') return trim(@$_GET[$name]);
		if ($method=='post') return self::_postvars($_POST, $name);
		$val = isset($_POST[$name])?self::_postvars($_POST, $name):(isset($_GET[$name])?trim($_GET[$name]):null);
		if ($method=='session'||$method=='set') {
			$request = (array) self::session(self::REQUEST_SESSION);
			if ($method=='set') {
				// set session value
				$request[$name] = $init_val;
				$val = $init_val;
			} elseif ($val) {
				$request[$name] = $val;
			} elseif (!isset($request[$name])||!$request[$name]) {
				$request[$name] = $init_val;
				$val = $init_val;
			} else {
				$val = @$request[$name];
			}
			self::session(self::REQUEST_SESSION, $request);
		} elseif (!isset($val)&&isset($init_val)) {
			// initial for regular request (post/get)
			$val = $init_val;
		}
		return $val;
	}
	/*** mix cache(string $name[, mix $val[, int $expire]])
	 *	@description	getter/setter data cacher as configured
	 *	@input	$name	variable name (key)
	 *			$val	value of variable for setter
	 *			$ttl	lifetime in seconds (for setter), 0 for never expired
	 *	@return	1. For getter, value of variable in cache, or false if no cache found or expired
	 *			2. For setter, true if cache success, or false if failed to cache
	***/
	public function cache($name, $val=null, $ttl=0)
	{
		$engine = strtolower($this->conf['cache']['engine']);
		switch ($engine) {
			case 'memcache':
				$memcache = new Memcache();
				foreach ($this->conf['cache']['server'] as $server) {
					list($host, $port) = strpos($server,':')?preg_split("/:/", $server):array($server, 11211);
					$memcache->addServer($host, $port);
				}
				$compressed = $this->conf['cache']['compressed']?1:0;
				if (isset($val)) {
					return $memcache->set($name, $val, $compressed, time()+$ttl);
				}
				return $memcache->get($name);
			case 'xcache':
                if (isset($val)) {
                    return xcache_set($name, $val, $ttl);
                }
                return xcache_isset($name)?xcache_get($name):false;
			case 'apc':
				if (isset($val)) {
					return apc_store($name, $val, $ttl);
				}
				return apc_fetch($name);
			default: // local file 
				$pool = isset($this->conf['cache']['local'])&&$this->conf['cache']['local']?$this->conf['cache']['local']:(sys_get_temp_dir().'/cache');
				$file_name = preg_match("/^\w{32}$/", $name)?$name:md5($name);
				$div = $pool.'/'.$this->env('DOMAIN').'/'.$name[0];	// sub folder
				if (!is_dir($div)) {
					if (!mkdir($div, 0700, true)) $this->error("Failed to create cache pool $div. Please check your cache setting in configure file.");
				}
				if (isset($val)) {
					// write into cache in format expire_timestanmp:content
					$expire = sprintf("%010d", $ttl?(time()+$ttl):0);
					return (bool) file_put_contents($file_name, "$expire:$val");
				}
				// read cache
				if (file_exists($file_name)) {
					$content = file_get_contents($file_name);
					$expire = intval(substr($content, 0, 10));
					if (time()<=$expire||$expire===0) return substr($content, 11); // actively cached
					// expired
				}
				return false;
		}
	}
	/*** get dependency defined in class
	 *	@input	$class_name	optional, check if class_name is defined in dependency
	 *	@return mix	
	 *			bool false if class_name is given but not in dependency definition
	 *			string	name in hook if class_name is given and exits in dependency definition
	 *			array	return whole dependencies array
	***/
	public function get_dependencies($class_name=null)
	{
		if ($class_name) {
			if (isset($this->dependencies[$class_name])) return $this->dependencies[$class_name];	// defined as preloading
			if (in_array($class_name, array_keys($this->dependencies))) return substr($class_name, 3);	// defined as loading on fly
			return false;	// not in dependencies
		}
		return $this->dependencies;
	}
	/** Loading libraries in dependencies array and the hook name is given
	 * return void	library will be hooked
	**/
	protected function load_dependencies()
	{
		foreach ((array)$this->dependencies as $class => $name) {
			if ($name) self::load_lib($class, $name);
		}
	}
	/** load library
	 * @input string $class_name	Must in camel string
	 *		  string $name(optional) $this->lib->name, name=class_name if name is not given
	 *		  bool	 $force(optional) true: override old hook if it exists. false:give error if hook already occupied
	**/
	protected function load_lib($class_name, $name=null, $force=false)
	{
		// dependency checking
		// $zone = get_class($this);
		// if (!in_array($class_name, array_keys($this->dependencies))) $this->error("Error! the class $class_name is not defined in dependency array of $zone.");
		if (!$name) $name = substr($class_name, 3); //remove 'lib' header
		$libraries = $this->global_store(self::HOOK_LIB);
		$lib = null;
		if (isset($libraries[$class_name])) {
			$lib = $libraries[$class_name];
		} else {
			$lib = new $class_name($this->conf);
			if (method_exists($lib, 'get_error')&&($error=$lib->get_error())) {
				$this->error('Error Code:'.$error['error_code'].' '.$error['error']);
			}
			//if ($this->operator&&method_exists($lib, 'operator')) $lib->operator($this->operator);
			$libraries[$class_name] = $lib;
			$this->global_store(self::HOOK_LIB, $libraries);
		}
		if (!isset($this->lib)) $this->lib = new stdClass();
		if ($force||!isset($this->lib->$name)) {
			$this->lib->$name = $lib;
		} else {
			$this->error("Error! The library hook $name has been occupied already.");
		}
		return $lib;
	}
	/*** mapping component to url  **/
	public function component_url($model_name, $method, $ajax=true)
	{
		$model_name = ucfirst($model_name); // force to class name format
		$url = isset($this->conf['route'][$model_name])?$this->conf['route'][$model_name]:('/'.$model_name);
		if ($ajax) $url .= ($url=='/'?null:'/').$this->conf['global']['ajax_frag'];
		return $url.'/'.$method;
	}
	/** keep class name in format. e.g. uiHtml -> UiHtml **/
	public function class_name_format($str, $prefix=null)
	{
        $path = preg_split("/\//", strtolower(preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $str)));
        if (isset($prefix) && $path[0]!==$prefix) array_unshift($path, $prefix);
        return str_replace(' ','', ucwords(join(' ', $path)));
	}
    public function date_format($in, $format=null)
    {
        if (!$in) return null;
		if (!$format) $format = defined(get_class($this).'::DATE_FORMAT')?constant(get_class($this).'::DATE_FORMAT'):'Y-m-d';
        return date($format, strtotime($in));
    }
    public function time_format($str, $format='H:i:s')
    {
        $src = array('H','i','s','h','a');
        if (preg_match("/(\d+):(\d+):(\d+)/", $str, $p)) {
            $hour = sprintf("%02d", $p[1]);
            $min = sprintf("%02d", $p[2]);
            $sec = sprintf("%02d", $p[3]);
        } elseif (preg_match("/(\d+):(\d+)/", $str, $p)) {
            $hour = sprintf("%02d", $p[1]);
            $min = sprintf("%02d", $p[2]);
            $sec = '00';
        } elseif (preg_match("/(\d+)/", $str, $p)) {
            $hour = sprintf("%02d", $p[1]);
            $min = $sec = '00';
        } else {
            return null;
        }
        if ($hour<12 && preg_match("/pm/i", $str)) {
            $hour = sprintf("%02d", ($hour+12)%24);
        }
        $data = array($hour, $min, $sec, $hour>12?($hour%12):$hour, $hour>=12?'pm':'am');
        return str_replace($src, $data, $format);
    }
	/*** set logging for specific command ***/
	public function record($cmd, $zone='debug')
	{
		if ($cmd) {
        	ob_start();
			return;
		}
        $content = ob_get_contents();
        ob_end_clean();
		self::logging($content, $zone);
		return;
	}
	public function logging($message, $zone='debug')
	{
		$log_file = $this->conf['global']['log_dir']."/$zone.log";
		$message = sprintf("%s\t%s\n", date('Y-m-d H:i:s'), $message);
		error_log($message, 3, $log_file);
		return;
	}
	public function error($message='Internal Error.') {
        if ($this->conf['global']['DEBUG']) debug_print_backtrace();
		exit($message);
	}
	/** unserialize given string if it is unserialized**/
	public function unserialize($str)
	{
		$data = @unserialize($str);
		return ($data===false&&$str!=='b:0;')?$str:$data;
	}
    public function hasharray2array($lines, $fkey, $fval=null)
    {
        $array = array();
        foreach ((array)$lines as $line) {
            if (isset($fval)) {
                $array[$line[$fkey]] = $line[$fval];
            } else {
                $array[] = $line[$fkey];
            }
        }
        return $array;
    }

	public function hash2str($hash)
	{
		$str = null;
		if (!is_array($hash)) return null;
		foreach ($hash as $key=>$val) $str .= "$key=\"$val\" ";
		return trim($str);
	}
	public function str2hash($str)
	{
		$hash = array();
		if (preg_match_all("/([^=\"]+)=\"([^\"]+)\"/", $str, $match)) {
			for ($i=0; $i<count($match[0]); $i++) $hash[trim($match[1][$i])] = trim($match[2][$i]);
		}
		return $hash;
	}
	public function dir_scan($dir, $pattern='*', $deep=null, $level=0)
	{
		$files = array();
		$files[] = array('name'=>$dir,'level'=>$level,'type'=>'folder');
		$level++;
		foreach (glob($dir."/$pattern") as $file) {
			if (is_dir($file)) {
				if (!isset($deep)||$deep>$level) {
					$files = array_merge($files, self::dir_scan($file, $pattern, $deep, $level));
				}
			} else {
				$files[] = array('name'=>$file,'level'=>$level,'type'=>'file');
			}
		}
		return $files;
	}
    public function random($len, $type='mix')
    {
		$types = array('mix'=>'abcdefghijkmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ0123456789','num'=>'0123456789','alpha'=>'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $len = max(1, $len);
        $txt = isset($types[$type])?$types[$type]:$type;
        $l = strlen($txt);
        $str = null;
        for ($i = 0; $i < $len; $i ++) {
            $str .= substr($txt, mt_rand(0, $l - 1),1);
        }
        return $str;
    }
	/** tz format +/-nnnn **/
	public function tz_local2utc($datetime, $format)
	{
		return gmdate($format, strtotime($datetime));
	}
	public function tz_utc2local($datetime, $format)
	{
		return date($format, strtotime($datetime));
	}
	public function convert_tz($datetime, $tz_from, $tz_to)
	{
		$offset_local = date_offset_get(new DateTime);
		$utc = gmdate(strtotime($datetime)+floor($from_tz/100)*3600+($from_tz%100)*60);
	}
	public function get_lib($lib_full_name, $load_on_fly=true)
	{
        $libraries = $this->global_store(self::HOOK_LIB);
        if (isset($libraries[$lib_full_name])) return $libraries[$lib_full_name];
		return $load_on_fly?$this->load_lib($lib_full_name):null;
	}
	/*** get user info from particular access control library, default library is LibAclUser ***/
	public function get_operator($acl_lib='LibAclUser')
	{
		// if (!$this->get_dependencies($acl_lib)) return null;	// no user
		if ($user=$this->get_lib($acl_lib)) return $user->info();
		return null;
	}
	/*** set user info from particular access control library, default library is LibAclUser ***/
	public function set_operator($operator, $acl_lib='LibAclUser')
	{
		if ($user=$this->get_lib($acl_lib)) return $user->info($operator);
		return null;
	}
	/** Global Data Storage **/
	protected function global_store($name, $val=null)
	{
		$HOOK = 'W3S';
		if (!isset($val)) return @$GLOBALS[$HOOK][$name];
		if (!$val&&isset($GLOBALS[$HOOK][$name])) {
			unset($GLOBALS[$HOOK][$name]);
		} else {
			$GLOBALS[$HOOK][$name] = $val;
		}
	}
	/*** string bean_file(string $class_name) ***
	 *	@description	get file for given bean class (model or library)
	 *	@input	string $class_name
	 *	@return	string	$file_name with full path;
	***/
	protected function bean_file($class_name, $active=true)
	{
	    $path = preg_split("/\//", strtolower(preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $this->class_name_format($class_name))));
	    if ($path[0]==='lib') {
        	$class_file = 'lib.class.php';
	        array_shift($path);
    	} else {
        	$class_file = 'model.class.php';
    	}
    	return APP_DIR.'/'.($active?'beans':'resource/beans').'/'.join('/', $path).'/'.$class_file;
	}
    /*** Simple Xor encrypt/decrypt***
     *  Principle:
     *  A^0 = A
     *  A^A = 0
     *  B^A^A = B^0 = B
    ***/
    protected function xor_crypt($key, $input)
    {
        $str = null;
        for ($i=0; $i<strlen($input);) {
            for ($j=0; $j<strlen($key); $i++,$j++) {
				if ($i<strlen($input)) $str .= $input[$i]^$key[$j];
            }
        }
        return $str;
    }
	private function _postvars($post, $key=null)
	{
		$magic = get_magic_quotes_gpc();
		if ($key&&!isset($_POST[$key])) return null;
		foreach ($post as $k=>$v) {
			if ($key&&$k!==$key) continue;
			$post[$k] = is_array($v)?self::_postvars($v):trim($magic?stripslashes($v):$v);
		}
		return isset($key)?(isset($post[$key])?$post[$key]:null):$post;
	}
}

/** Web: MVC Layer base class for both Controller and Model **/
class Web extends Core
{
	const COMPONENT_CALL = 'component'; // url phase of ajax call for component
	public function configure()
	{
		$conf_dir = APP_DIR."/conf";
		$conf = array();
		foreach (array('main',self::request('_DOMAIN')) as $conf_file) {
			$file = "$conf_dir/$conf_file.ini";
			if (file_exists($file)) {
				$ini_rows = array_change_key_case(parse_ini_file($file, true), CASE_LOWER);
				foreach ($ini_rows as $div=>$param) {
					foreach ($param as $key=>$val) {
						if (strpos($key, '.')!==false) {
							$r = preg_split("/\./", $key, 2);
							$sub = $r[0];
							$k = $r[1];
							if (isset($conf[$div][$sub][$k])&&is_array($val)) {
								$conf[$div][$sub][$k] = array_merge($conf[$div][$sub][$k], $val);
							} else {
								$conf[$div][$sub][$k] = $val;
							}
						} else {
							if (isset($conf[$div][$key])&&is_array($val)) {
								$conf[$div][$key] = array_merge($conf[$div][$key], $val);
							} else {
								$conf[$div][$key] = $val;
							}
						}
					}
				}
			}
		}
		$conf = array_change_key_case($conf,CASE_LOWER);
		if (!$conf['global']['TIMEZONE']) {
			// get system timezone
			$conf['global']['TIMEZONE'] = function_exists('date_default_timezone_get')?date_default_timezone_get():'UTC';
		}
		// setting application timezone
		if (function_exists('date_default_timezone_set')) date_default_timezone_set($conf['global']['TIMEZONE']);
		if (!isset($conf['global']['ajax_frag'])) $conf['global']['ajax_frag'] = self::COMPONENT_CALL;
		if (!isset($conf['global']['index'])) $conf['global']['index'] = Core::DEFAULT_ENTRY;
		if (!isset($conf['route'])) $conf['route'] = array();
		if (!isset($conf['domain'])) $conf['domain'] = array();
		if (!isset($conf['access'])) $conf['access'] = array();
		//$conf['access']=array_change_key_case($conf['access'],CASE_LOWER);
		//$conf['route']=array_change_key_case($conf['route'],CASE_LOWER);
		//$conf['domain']=array_change_key_case($conf['domain'], CASE_LOWER);
		if (!isset($conf['access']['model'])) $conf['access']['model'] = array();
		//for ($i=0; $i<count($conf['access']['model']); $i++) $conf['access']['model'][$i] = strtolower($conf['access']['model'][$i]);
		if (!defined('DEBUG')&&isset($conf['global']['DEBUG'])) define('DEBUG', $conf['global']['DEBUG']); // set global constant: DEGUB
		return $this->conf = $conf;
	}
    /** convert url to array without empty head or ending **/
    protected function url2array($url)
    {
        if ($url=='/') return array();
        if ($url[0]=='/') $url = substr($url, 1);
        if (substr($url, -1)=='/') $url = substr($url, 0, -1);
        return preg_split("|/|", $url);
    }
	/** URL=>stream **
	 *	@input	string $url	url
	 *			bool $method_check	force to check method file, give error if method file doesn't exist and method_check is set to true
	 *	@output	array stream
	**/
	public function mapping($url, $method_check=false)
	{
		$stream = array(
			'offset'=>null,		// matched value of pre-configure model = offset pair, it must be /somthing or null
			'url'=>$url,		// url without offset
			'model'=>null,
			'method'=>null,
			'param'=>null,		// linear array, param from url. e.g. /user/login
			'conf'=>null,		// component conf/initial data in hash. e.g. array('key1'=>val1,'key2'=>val2,...)
			'model_url'=>null,	// stake url for model call
			'comp_url'=>null,	// stake url for component call. usually like: /model/ajax_frag/method
			'model_file'=>null,
			'method_file'=>null,  // file without suffix (e.g .inc.php or .tpl.php)
			'view'=>null,		  // mostly it's same as mothod_file in ajax, but folder /model/ may be change to /view/ later in no-ajax mode, and file name may be expanded as well.
			'data'=>null,
			'suffix'=>null,
			'ajax'=>false,		// deside view's folder. view stay with handler if true, otherwise in view folder.
			'format'=>'html'
		);
		$r = $this->url2array($url);
		$url = '/'.join('/', $r);
		$stream['url'] = $url;
		$default=array_search('/', $this->conf['route']);
		$index = @$this->conf['global']['index']?$this->conf['global']['index']:Core::DEFAULT_ENTRY;
		// mapping model & method via url
		$ajax_offset = strpos($url,'/'.$this->conf['global']['ajax_frag'].'/');
		if ($ajax_offset!==false) {
			// check if it's a valid ajax request url
			$offset = substr($url, 0, max(1,$ajax_offset));
			if ($model=array_search($offset, $this->conf['route'])) {
				// match offset found in configure: offset/ajax/...
				$stream['ajax'] = true;
				$stream['model'] = $model;
				$stream['comp_url'] = ($offset=='/'?null:$offset).'/'.$this->conf['global']['ajax_frag']; // method will be added later
				$url = substr($url, strlen($offset.'/'.$this->conf['global']['ajax_frag']));
				$r = $this->url2array($url);
			} elseif ($r[1]==$this->conf['global']['ajax_frag']) {
				// /model/ajax/method ...
				$stream['ajax'] = true;
                $stream['model'] = array_shift($r);
				$stream['comp_url'] = '/'.$stream['model'].'/'.array_shift($r); // method will be added later
			}
		}

		if (!$stream['ajax']) {
			// check offset if match on model configure setting
			$offset = $url;
			$model = null;
			while ($offset) {
				if ($model=array_search($offset, $this->conf['route']))	{
					// check if url is in /UserModel/method ...
					if ($offset=='/'&&isset($r[0])&&preg_match("/[A-Z]/", $r[0])) $model=null; // ignore default model, using pattern /UserModel/method ...
					break;
				}
				if ($offset=='/') break;
				$offset = dirname($offset);
			}
			if (!$model) {
				// no matched offset
                if ($url=='/') {
                    if (!$default) {
                        $this->error("No default model defined in configuration.");
                        return null;
                    }
                    // using default model, default method
                    $r[0] = $default;
                }
                // url format: /model/method...
				$stream['model'] = $r[0];
				$stream['comp_url'] = $r[0].'/'.$this->conf['global']['ajax_frag']; // method will be added later
				array_shift($r);
			} else {
				$stream['model'] = $model;
				$stream['comp_url'] = ($offset=='/'?null:$offset).'/'.$this->conf['global']['ajax_frag']; // method will be added later
				$url = substr($url, strlen($offset));
				$r = $this->url2array($url);
			}
		}
		// verify model
		if (!$this->model_locator($stream['model'])) {
			$this->error("Not found model file. {$stream['model']}");
			return null;
		}
		// locate method
		if (!isset($r[0])||!$r[0]) $r[0] = $index; // using default if no method given in url
		list($stream['model_file'], $stream['method_file'], $stream['view']) = $this->model_locator($stream['model'], $r[0]);
		if (!((bool)$stream['method_file']||(bool)$stream['view'])&&$r[0]!=$index) {
			// un-recognized method, using default method
			array_unshift($r, $index);
			list($stream['model_file'], $stream['method_file'], $stream['view']) = $this->model_locator($stream['model'], $r[0]);
		}
		$stream['method'] = array_shift($r);

		if ($method_check&&!((bool)$stream['method_file']||(bool)$stream['view'])) {
			$this->error("Invalid method. {$stream['method']}");
			return null;
		}
		$stream['comp_url'] .= '/'.$stream['method'];
		$stream['param'] = count($r)?$r:null;
		if ($stream['ajax']) $stream['view'] = $stream['method_file']; // using component view instead of page view for ajax request
		return $stream;
	}
	public function model_locator($model, $method=null)
	{
		$model_file = $this->bean_file($model);
		if (!file_exists($model_file)) return null;
		if (!isset($method)) return $model_file;
		$method_file = $view_file = null;
		$suffix_inc = '.inc.php';
		$suffix_tpl = '.tpl.php';
		$file = dirname($model_file).'/handler/'. $method;
		if (file_exists($file.$suffix_inc)||file_exists($file.$suffix_tpl)) $method_file = $file;
		$file = dirname($model_file).'/view/'. $method;
		if (file_exists($file.$suffix_tpl)) $view_file = $file;
		return array($model_file, $method_file, $view_file);
	}
	public function model_verify($model_name)
	{
		$model_name = ucfirst($model_name);	// format model_name
		if (in_array($model_name, $this->conf['access']['model'])) return true;
		if (isset($this->conf['route'][$model_name])) return true;
		if (isset($this->conf['domain'][$model_name])) {
			$domains = preg_split("/[,\s]+/", strtolower($this->conf['domain'][$model_name]));
			if (in_array($this->env('DOMAIN'), $domains)) return true;
		}
		// check wildcard match
		$path = preg_split("/\//", preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $model_name));
		$class_str = null;
		foreach ($path as $frag) {
			$class_str .= $frag;
			if (in_array("$class_str*", $this->conf['access']['model'])) return true;
		}
		return false;
	}
    public function load_view($view_name, $bind=null, $ext=null)
    {
		$template = "$view_name.tpl.php";
        if (!$view_name||!file_exists($template)) return $bind;

        if (is_array($bind) && array_keys($bind)!==range(0, count($bind)-1)) {
            foreach ($bind as $key=>$val) {
                $$key = $val;
            }
        }
        ob_start();
        include $template;
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
	public function redirect($url, $code=307)
	{
		if (is_array($url)) {
			if (isset($url['url'])) {
				$message = @$url['message'];
				$target_url = $url['url'];
				if (@$url['method']=='alert') {
					$alert = $message?"alert('$message');":null;
					echo <<<EOT
<script type="text/javascript">
$alert
window.location.href='{$url['url']}';
</script>
EOT;
				} else {
					$delay = isset($url['delay'])?$url['delay']:($message?3:0);
					echo <<<EOT
<html>
<meta http-equiv="refresh" content="$delay; url=$target_url" />
<body>$message</body>
</html>
EOT;
				}
				exit;
			}
			$url = '/';	// invalid parameter, redirect to home
		}
		if ($url==='/') {
			header("location:{$this->stream['offset']}", true, $code);
		} elseif ($url[0]==='/') {
			header("location:$url", TRUE, $code);
		} else {
			header("location:{$this->stream['offset']}/$url", true, $code);
		}
		exit;
	}
	public function page_not_found($message=null)
	{
		$url = $this->env('URL');
		echo <<<EOT
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL $url was not found on this server.</p>
<hr>
<address>$message</address>
</body></html>
EOT;
		exit;
	}

    /** export
     * output to client site with specific format
    */
    public function output($data, $format=null, $name=null)
    {
        $types = array(
            'html'=>'text/html',
			'plain'=>'text/plain',
            'css'=>'text/css',
            'js'=>'text/javascript',
            'xml'=>'text/xml',
            'excel'=>'application/vnd.ms-excel',
            'word'=>'application/vnd.ms-word',
            'pdf'=>'application/pdf',
            'csv'=>'application/octet-stream',
            'jpg'=>'image/jpeg',
			'bin'=>'application/octet-stream'
        );
        $file_exts = array('excel'=>'xls','pdf'=>'pdf','csv'=>'csv');
        header("Pragma: public");  //fix IE cache issue with PHP
        header("Expires: 0");   // no cache
        if ($format||$name) {
            $content_type = isset($types[$format])?$types[$format]:($name?'application/octet-stream':'text/plain');
            header("Content-Type: $content_type");
            if (isset($file_exts[$format])||$name) {
				// binary file
				if (!$name) $name = 'download';
				$file_name = isset($file_exts[$format])? "$name.{$file_exts[$format]}":$name;
		        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		        header("Cache-Control: private",false);
                header("Content-Disposition: attachment; filename='$name'");
		        header("Content-Transfer-Encoding: binary");
        		header('Content-Length: '.strlen($data));
            }
            if ($format==='json') {
                $info = json_encode($data);
            } else {
                $info = is_array($data)?serialize($data):$data;
            }
            echo $info;
        } else {
            echo $data;
        }
		if (ob_get_contents()) ob_flush();
        exit;
    }
	public function error($message='Internal Error.') {
        if ($this->conf['global']['DEBUG']) debug_print_backtrace();
		exit($message);
	}
    /** simple get/post method
     * @input string $url    remote url post to
     *        array  $data   associative array of post data
     * @return mix response
    **/
    protected function http_request($method, $url, $data=array())
    {
        $method=strtoupper($method);
        $data = http_build_query($data);
        if ($method=='GET') {
            $url.='?'.$data;
            return file_get_contents($url);
        }
        if ($method=='POST') {
            $header = "Content-type: application/x-www-form-urlencoded\r\n"."Content-Length: ".strlen($data)."\r\n";
            $m = 'rb';
        } else {
            $header = "Accept-language: en\r\n";
            $m = 'r';
        }
        $context_options = array (
            'http' => array (
                'method' => $method,
                'header'=> $header,
                'content' => $data
            )
        );

        $context = stream_context_create($context_options);
        $fp = fopen($url, $m, false, $context);
        if (!$fp) return false;
        $response = @stream_get_contents($fp);
        fclose($fp);
        return $response;
    }
    /** Upload file with specific type
     * @input
     *  userfile    $__FILES entry
     *  target      target file path, or folder if multiple upload
     *  filter 		null:allow all types , or array('jpg','gif','...')
     *  callback    call back function invoked for each valid upload file, used for post process such as resize
     * @return
     *  String/Array    target file name or callback return
     *  false       invalid upload, maybe an attack
     *  null        valid upload but failed for move or user callback
	 * @security	do not allow upload file with '.' prefix, or force it to be visible by remove prefix '.'.
    **/
    public function upload_file($userfile, $target, $filter=array('jpg','gif','png','swf','bmp'), $callback=null)
    {
		if (!count(array_keys($_FILES))) return null;
		if (!$target) return false;
		$name = null; // use upload file name as default
		if (substr($target,-1)!=='/') {
			$name = basename($target);
			$target = dirname($target);
		} else {
			$target = substr($target, 0, -1);
		}
		if (!is_dir($target)) {
			if (!mkdir($target, 0777, true)) return false;
		}
        if (is_array($_FILES[$userfile]["error"])) {
            // batch upload
            $results = array();
            foreach ($_FILES[$userfile]["error"] as $key => $error) {
                $result = false;
                if (!$_FILES[$userfile]["name"][$key]) continue;
				$r = preg_split("/\./", $_FILES[$userfile]['name'][$key]);
				$ext = array_pop($r);
				$name = join('.', $r);	// batch upload do not allow specific target name, use upload file's name
				if ($filter) {
					if (!in_array(strtolower($ext), $filter)) continue;
				}
                if ($error==UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES[$userfile]["tmp_name"][$key];
					if (!$name) {
						$itarget .= $ext;
					} else {
						$name = preg_replace("/^\./","_", $name);
                    	$itarget = "$target/$name.$ext";
					}
                    if ($callback && $res=call_user_func($callback, $tmp_name, $itarget)) {
                        $name = basename($res);
                        $result = true;
                    } elseif (!$callback) {
                        if (move_uploaded_file($tmp_name, $itarget)) {
                            $result = true;
                        }
                    }
                }
                $results[$name] = $result;
            }
            return $results;
        }
        if (!is_uploaded_file($_FILES[$userfile]['tmp_name'])) {
            // error
            return false;
        }
        if (!$_FILES[$userfile]['name']) return false;  // invliad tag name
		$r = preg_split("/\./", $_FILES[$userfile]['name']);
		$ext = array_pop($r);
		if (!$name&&count($r)) $name=join('.', $r);
		if ($filter&&$filter!=='*') {
			if (!in_array(strtolower($ext), $filter)) return false;
		}
        $tmp_name = $_FILES[$userfile]['tmp_name'];
		if (!$name) {
			$target .= '/'.$ext;
		} else {
			$name = preg_replace("/^\./","_", $name);
			$target .= "/$name.$ext";
		}
        if ($callback) {
            return call_user_func($callback, $tmp_name, $target);
        } elseif (!move_uploaded_file($tmp_name, $target)) {
            return null;
        }
        return $target;
    }
	public function template_ext()
	{
		return defined('EXT')?EXT:null;
	}
}
