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
	protected $status = array('class'=>null,'error'=>null,'error_code'=>null); // for cross-class info trnasfer
	protected $conf = null;
	protected $dependencies = null;  // array(LibClassName=>name)  invoke: this->lib->name
	const HOOK_LIB = 'library';
	const HOOK_MODEL = 'model';
	const HOOK_DB = 'database';
	const HOOK_VAR = 'w3s_var';	// hook name for env vars in both session and global
	const HOOK_GLOBAL = 'w3s_global';	// hook name in global store
	const HOOK_BENCHMARK = 'w3s_benchmark';	// global, array(key1=>array('start'=>time,'end'=>time),key2=>....)
	const W3S_SEQ = 'w3s_sequence';
	const REQUEST_SESSION = 'w3s_request';	// for input cache
	// user session hook
    const USER_SESSION_AUTH = 'W3S::USER_SA'; // user authenticate session
    const USER_SESSION_HOOK = 'W3S::USER_SH'; // store session which has same lifetime as user authenticated session

	const CACHE_CLEAR_TTL = -1;	// clear specific cache


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
		return self::_hook_var(&$GLOBALS, $env_name, $val, $HOOK);
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
		return self::_hook_var(&$_SESSION, $name, $val, $HOOK);
    }
	/** Global Data Storage **/
	protected function globals($name, $val=null)
	{
		$HOOK = self::HOOK_GLOBAL;
		return self::_hook_var(&$GLOBALS, $name, $val, $HOOK);
	}
	private function _hook_var(&$scope, $name, $val, $HOOK)
	{
        if (!isset($scope[$HOOK])) $scope[$HOOK]=array();
        if (!isset($val)) return isset($scope[$HOOK][$name])?$scope[$HOOK][$name]:null;
        if ($val=='clear') $val=null;
        if ($val=='reset') $val='';
        return $scope[$HOOK][$name]=$val;
	}
	/*** mix cookie(string $name[, string $val=null[, int $lifetime=null]])
	 *	@description getter/setter of cookie
	 *	@input	$name	cookie name
	 *			$val	value of cookie for setting, 'clear','' for clear cookie
	 *			$lieftime	0:for browser lifetime, 1:for permanant, others is lifetime in seconds
	 *	@return	value of cookie for read
	 *			true for setting ok, false for setting failure
	***/
	public function cookie($name, $val=null, $lifetime=0)
	{
		if (!isset($val)) return @$_COOKIE[$name];
		if (!$val||$val=='clear') {
			if (isset($_COOKIE[$name])) unset($_COOKIE[$name]);
			return true;
		}
		if (is_array($val)) $val = serialize($val);
		if ($lifetime==1) $lifetime = time()+315360000;	// 10 years
		return setcookie($name, $val, $lifetime);
	}
    /** Unique number generator **/
    public function sequence($offset=0, $schema=null)
    {
		if (!$schema) $schema = self::W3S_SEQ;
        if ($offset=='reset') return self::session($schema, 0);
        $seq = intval(self::session($schema)) + $offset;
        if ($offset) self::session($schema, $seq);
        return $seq;
    }

    /*** mix user_info([string $name])
     *  @description read user authentication session
     *  @input $name    property name
     *  @return mix whole user session if no property name given or property value by given name
    ***/
	public function user_info($name=null)
	{
		if (!$info=$this->session(self::USER_SESSION_AUTH)) return false;
		return $name?@$info[$name]:$info;
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
			case 'local': // local file
			default: // no defined
				$pool = isset($this->conf['cache']['local'])&&$this->conf['cache']['local']?$this->conf['cache']['local']:(sys_get_temp_dir().'/cache');
				$file_name = preg_match("/^\w{32}$/", $name)?$name:md5($name);
				$div = $pool.'/'.$this->env('DOMAIN').'/'.$name[0];	// sub folder
				if (!is_dir($div)) {
					if (!mkdir($div, 0700, true)) $this->error("Failed to create cache pool $div. Please check your cache setting in configure file.");
				}
				$file = $div.'/'.$file_name;
				if ($ttl==self::CACHE_CLEAR_TTL) {
					// clear cache
					return file_exists($file)?unlink($file):true;
				}
				if (isset($val)) {
					// write into cache in format expire_timestanmp:content
					if (is_array($val)) $val = serialize($val);
					$expire = sprintf("%010d", $ttl?(time()+$ttl):0);
					return (bool) file_put_contents($file, "$expire:$val");
				}
				// read cache
				if (file_exists($file)) {
					$content = file_get_contents($file);
					$expire = intval(substr($content, 0, 10));
					if (time()<=$expire||$expire===0) return $this->unserialize(substr($content, 11)); // actively cached
					// expired
				}
				return false;
		}
	}
	/*** int benchmark(string $point[, bool $start=false])
	 *	@description	setter/getter benchmark timestamp
	 *	@input	$point	pinter name, mostly like 'model::handler' for component benchmarking
	 *			$start	true for start a new benchmark watching, false to end benchmark and return end timestamp in ms.
	 @	@return	start timestamp in ms for starting, end timestamp in ms
	***/
	protected function benchmark($point, $start=false)
	{
		$benchmark = $this->globals(self::HOOK_BENCHMARK);
		$timestamp = microtime(true);
		if ($start) {
			$benchmark[$point] = array('start'=>$timestamp,'end'=>0);
			$this->globals(self::HOOK_BENCHMARK, $benchmark);
		} else {
			if (!isset($benchmark[$point])||!isset($benchmark[$point]['start'])) return 0;
			$benchmark[$point]['end'] = $timestamp;
			$this->globals(self::HOOK_BENCHMARK, $benchmark);
		}
		return $timestamp;
	}
	/*** void benchmark_log()
	 *	@description write benchmark data in globals into log file
	***/
	protected function benchmark_log()
	{
		$benchmark = (array)$this->globals(self::HOOK_BENCHMARK);
		$file_name = 'benchmark/'.$this->env('DOMAIN');
		$total = $max = 0;
		$big = null;
		foreach ($benchmark as $point=>$rec) {
			$live = $rec['end']-$rec['start'];
			$total += $live;
			if ($live>$max) {
				$max = $live;
				$big = $point;
			}
			$mesg = sprintf("%s\t%s,%s,%s", $point, $rec['start'], $rec['end'], $live);
			$this->logging($mesg, $file_name);
		}
		$mesg = sprintf("Total use %s ms in request %s\tBiggest point: %s use %s ms.", $total, $this->env('PATH'), $big, $max);
		$this->logging($mesg, $file_name);
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
			if ($name) $this->load_lib($class, $name);
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
		if (!in_array($class_name, array_keys($this->dependencies))) $this->error("Error! the class $class_name is not defined in dependency array of ".get_class($this));
		if (!$name) $name = substr($class_name, strlen(PREFIX_LIB)); //remove prefix header
		$libraries = $this->globals(self::HOOK_LIB);
		$lib = null;
		if (isset($libraries[$class_name])) {
			$lib = $libraries[$class_name];
		} else {
			$lib = new $class_name($this->conf);
			if (method_exists($lib, 'get_error')&&($error=$lib->get_error())) {
				$this->error('Error Code:'.$error['error_code'].' '.$error['error']);
			}
			$libraries[$class_name] = $lib;
			$this->globals(self::HOOK_LIB, $libraries);
		}
		if (!isset($this->lib)) $this->lib = new stdClass();
		if ($force||!isset($this->lib->$name)) {
			$this->lib->$name = $lib;
		} else {
			$this->error("Error! The library hook $name has been occupied already.");
		}
		return $lib;
	}
	/*** loading model and put it on hook **/
	protected function load_model($model_name, $stream=null)
	{
        $models = $this->globals(self::HOOK_MODEL);
        $model = null;
        if (isset($models[$model_name])) {
            $model = $models[$model_name];
        } else {
            $model = new $model_name($this->conf, $stream);
            $models[$model_name] = $model;
            $this->globals(self::HOOK_MODEL, $models);
        }
		if ($stream) $model->stream = $stream;
		return $model;
	}
	/*** mapping component to url  **/
	public function component_url($model_name, $method, $ajax=true)
	{
		$class_name = $this->model_name($model_name, true); // force to class name format
		$path_name = $this->model_name($model_name, false);
		$url = isset($this->conf['route'][$class_name])?$this->conf['route'][$class_name]:('/'.$path_name);
		if ($ajax) $url .= ($url=='/'?null:'/').$this->conf['global']['ajax_frag'];
		return $url.($url=='/'?null:'/').$method;
	}

	/*** array conf_model()
	 *	@description get listing of accessable models based on configure file
	 *	@input none
	 *	@return	list of model name=>file
	***/
	public function conf_model()
	{
		$defs = array_merge($this->conf['access']['model'], array_keys((array)$this->conf['route']));
		$models = array();
		foreach ($defs as $def) {
			if (substr($def, -1)=='*') {
				$def = substr($def, 0, -1);
				$dir = dirname($this->bean_file($def));
				$limit = '\/model\.class\.php$';
				$files = $this->recursive_scandir($dir, $limit);
				foreach ($files as $file) {
					if ($offset=substr(dirname($file), strlen($dir)+1)) {
						$class_name = $def.str_replace(' ','', ucwords(join(' ',preg_split("/\//", $offset))));
					} else {
						$class_name = $def;
					}
					$models[$class_name] = $file;
				}
			} else {
				$models[$def] = $this->bean_file($def);
			}
		}
		return $models;
	}

	/*** array recursive_scandir(string $root[, string $file_name=null])
	 *	@description recursively scan directory with file name in regular express (optional)
	 *	@input	$root	start directory name
	 *			$file_name	file name match in regular express
	 *	@return	matched $files in array
	***/
	protected function recursive_scandir($root, $file_name=null)
	{
		$files = array();
		foreach (glob("$root/*") as $node) {
			if (is_dir($node)) {
				if (!$file_name) $files[] = $node;	// folder
				$files = array_merge($files, $this->recursive_scandir($node, $file_name));
			} else {
				if (!$file_name||($file_name&&preg_match("/$file_name/", $node))) $files[] = $node;
			}
		}
		return $files;
	}
    /*** array action_def(string $model)
     *  @description read and parse access.ini into array for given model
     *  @input $model   modelName
     *  @return array parsed from ini file
     *          null if access file does not exist, which means the model is public
     *          false if model does not exist which means wrong model name
    ***/
    public function action_def($model)
    {
        $model_file = $this->bean_file($model);
        if (!$model_file) return false;
        $access_file = dirname($model_file)."/access.ini";
        if (!file_exists($access_file)) return null;
        return parse_ini_file($access_file, true);
    }
	
	/*** bool action_verify(string $model, string $handler[, string $action='*'])
	 *	@description  verify given action for current user
	 *	@input $model	model name in camelcase
	 *		   $handler handler name
	 *		   $action  action name
	 *	@return	true if ok, false if denied
	***/
	public function action_verify($model, $handler, $action='*')
	{
        if (!$map=$this->action_def($model)) return $map!==false;   // true for pulic model, false for wrong model given
		if (!isset($map['MODEL::']['protection'])||!$map['MODEL::']['protection']) return true;	// public model
        if ($map['MODEL::']['protection']!=='full'&&!isset($map['HANDLER::'][$handler])) return true;   // public access component
		// all handler are protected or the handler has been defined as protected
        if (!$group=$this->user_info('group')) return false;    // no logged in or idle user
		if (!$actions=$group['action']) return false;	// no defined action for the group
		foreach (array("$model::$handler::$action","$model::$handler::*","$model::*::*","*::*::*") as $pattern) {
			if (in_array($pattern, $actions)) return true;
		}
		return false;
    }

	/*** String model_name(String $model_name[, bool $class=true])
	 *	@description	Generating standard class name or url path portion based on given model name
	 *	@input	String $model_name		model name
	 *			bool $class				ture for full standard class name, false for url portion
	 *	@return	String	Standard class name or url path portion
	***/
	public function model_name($model_name, $class=true)
	{
		$name = $this->class_name_format($model_name, PREFIX_MODEL);
		return $class?$name:substr($name, strlen(PREFIX_MODEL));
	}
	/** keep class name in correct format. e.g. myClass -> MyClass, and add correct preifx such as Lib if it's given **/
	public function class_name_format($str, $prefix=null)
	{
		$prefix = strtolower($prefix);
        $path = preg_split("/\//", strtolower(preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $str)));
        if (isset($prefix) && $path[0]!==$prefix) array_unshift($path, ucfirst($prefix));
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
	/*** logging message into log file ***/
	public function logging($message, $zone='debug')
	{
        $log_dir = isset($this->conf['global']['log_dir'])?$this->conf['global']['log_dir']:"/var/tmp/log";
		$log_file = "$log_dir/$zone.log";
		$dir = dirname($log_file);
		if (!is_dir($dir)) mkdir($dir, 0700, true);
		$message = sprintf("%s\t%s\n", date('Y-m-d H:i:s'), $message);
		error_log($message, 3, $log_file);
		return;
	}
	/*** void error(string $err[, bool $traceback=false])
	 *	@description	Developer-Oriented error handler: logging error in details and exit
	 *	@input	$err	Error message in string or array
	 *			$traceback	logging debug backtrace if true, or just error message only
	 *	@return	void
	***/
	public function error($err, $traceback=false)
	{
        $errlog_dir = isset($this->conf['global']['log_dir'])?$this->conf['global']['log_dir']:"/var/tmp/log";
		if (!is_dir($errlog_dir)) mkdir($errlog_dir, 0700, true);
		$log_file = $errlog_dir.'/error.log';
		$timestamp = date('Y-m-d H:i:s');
        $err_msg = sprintf("%s\t%s\t%s\n%s\n", 
			$timestamp, $_SERVER['REMOTE_ADDR'], is_array($err)?serialize($err):$err, $traceback?print_r(debug_backtrace(), true):null);
        error_log($err_msg, 3, $log_file);
		exit("Internal Error at $timestamp.");
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
				if (is_array($fval)) {
					$array[$line[$fkey]] = array();
					foreach ($fval as $f) $array[$line[$fkey]][$f] = $line[$f];
				} else {
                	$array[$line[$fkey]] = $line[$fval];
				}
            } else {
                $array[] = $line[$fkey];
            }
        }
        return $array;
    }
	/*** string hash2str(array $hash)
	 *	@description convert hash to string in format key1="val1" key2="val2" ...
	 *	@input $hash	array(key1=>val1,key2=>val2, ...)
	 *	@return	String	key1="val1" key2="val2" ...
	***/
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
	/*** mix get_lib(String $lib_full_name[, bool $load_on_fly=true])
	 *	@description get or load given library
	 *	@input	String $lib_full_name	Formated library class name in camelcase
	 *			bool $load_on_fly		false: only get from pre-loaded library listing, true:load if not preloaded.
	 *	@return	object if load successfully, 
	 *			null: if load_on_fly is false and lib does not pre-loaded.
	 *			false: load_on_fly is true but lib file does not exist.
	***/
	public function get_lib($lib_full_name, $load_on_fly=true)
	{
        $libraries = $this->globals(self::HOOK_LIB);
        if (isset($libraries[$lib_full_name])) return $libraries[$lib_full_name];
		if (!$load_on_fly) $this->error("Library '$lib_full_name' has not been loaded yet.");;
		$lib_file = $this->bean_file($lib_full_name);
		return file_exists($lib_file)?$this->load_lib($lib_full_name):$this->error("Library '$lib_full_name' Not Found.");
	}

	/*** string bean_file(string $class_name) ***
	 *	@description	get file for given bean class (model or library)
	 *	@input	string $class_name
	 *	@return	string	$file_name with full path;
	***/
	protected function bean_file($class_name, $active=true)
	{
	    $path = preg_split("/\//", preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", ucfirst($class_name)));
		$path[0] = ucfirst($path[0]);
	    if ($path[0]===PREFIX_LIB) {
        	$class_file = 'lib.class.php';
	        array_shift($path);
    	} elseif ($path[0]===PREFIX_MODEL) {
        	$class_file = 'model.class.php';
	        array_shift($path);
    	} else {
			// invalid class name
			$this->status['error_code'] = 'INVALID_CLASSNAME';
			$this->status['error'] = 'Invalid class name';
			return false;
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
			if (is_array($v)) {
				foreach ($v as $i=>$sv) $post[$k][$i] = trim($magic?stripslashes($sv):$sv);
			} else {
				$post[$k] = trim($magic?stripslashes($v):$v);
			}
		}
		return isset($key)?(isset($post[$key])?$post[$key]:null):$post;
	}
}

/** Web: MVC Layer base class for both Controller and Model **/
class Web extends Core
{
	const COMPONENT_CALL = 'component'; // url phase of ajax call for component
	/*** mix component_locator(string $model[,string $method[,bool $ajax=false]])
	 *	@description	check component if exists and fulfill stream by given model, method and ajax option.
	 *	@input	$model	model name
	 *			$method	method name (optional)
	 *			$ajax	ajax option
	 *	@return	string model file name if just model name given
	 *			array('model'=>model,'method'=>method,'ajax'=>ajax,'model_file'=>model_file, 'method_file'=>method_file, 'view_file'=>view_file)
	 *			false if no valid file found
	***/
	public function component_locator($model, $method=null, $ajax=true)
	{
		$model_name = $this->model_name($model);
		$model_file = $this->bean_file($model_name);
		if (!file_exists($model_file)) {
			$this->status['error_code'] = 'FILE_DOES_NOT_EXIST';
			$this->status['error'] = "The model file does not exist: $model_file.";
			return false;
		}
		if (!isset($method)) return $model_file;
		$suffix_inc = '.inc.php';
		$suffix_tpl = '.tpl.php';
		$file = dirname($model_file).'/handler/'.$method;
		// method file
		$method_file = $file.$suffix_inc;
		if (!file_exists($method_file)) $method_file = null;
		// view file
		if (!$ajax) $file = dirname($model_file).'/view/'. $method;
		$view_file = $file.$suffix_tpl;
		if (!file_exists($view_file)) $view_file = null;
		if (!($method_file||$view_file)) return null;	// invalid component
		return array(
			'model'=>$model,
			'method'=>$method,
			'ajax'=>$ajax,
			'comp_url'=>$this->component_url($model, $method, true),
			'model_file'=>$model_file, 
			'method_file'=>$method_file, 
			'view_file'=>$view_file
		);
	}

	/*** bool model_verify(string $model_name)
	 *	@description verify a model with config in sections access,route and domain, only registered model can be verified
	 *	@input $model_name	model name in camelcase
	 *	@return	true if verified, or false for failure of verification
	***/
	public function model_verify($model_name)
	{
		// format model_name
		$model_name = $this->model_name($model_name);
		// check access section in config
		if (in_array($model_name, $this->conf['access']['model'])) return true;
		// check wildcard match in access section
		$path = preg_split("/\//", preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $model_name));
		$class_str = null;
		foreach ($path as $frag) {
			$class_str .= $frag;
			if (in_array("$class_str*", $this->conf['access']['model'])) return true;
		}
		// then check route table in config
		if (isset($this->conf['route'][$model_name])) return true;
		// then check domain dedicated models in config
		if (isset($this->conf['domain'][$model_name])) {
			$domains = preg_split("/[,\s]+/", strtolower($this->conf['domain'][$model_name]));
			if (in_array($this->env('DOMAIN'), $domains)) return true;
		}
		return false;
	}
    public function load_view($view, $bind=null, $ext=null)
    {
		if (substr($view, -8)!='.tpl.php') $view .= ".tpl.php";
        if (!file_exists($view)) return $bind;
        if (is_array($bind) && array_keys($bind)!==range(0, count($bind)-1)) {
			extract($bind);
        }
        ob_start();
        include $view;
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
			if ($this->stream['offset']) $url = $this->stream['offset'];
			header("location:$url", true, $code);
		} elseif ($url[0]==='/') {
			header("location:$url", TRUE, $code);
		} else {
			if ($this->stream['offset']) $url = "{$this->stream['offset']}/$url";
			header("location:$url", true, $code);
		}
		exit;
	}
	public function page_not_found($message=null)
	{
		$url = $this->env('URL');
		$not_found = $this->language_tag('NOT_FOUND');
		$page_not_found = $this->language_tag('PAGE_NOT_FOUND');
		echo <<<EOT
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 $not_found</title>
</head><body>
<h1>Not Found</h1>
<p>$page_not_found</p>
<hr>
<address>$url</address>
<p>$message</p>
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
        if (is_array($_FILES[$userfile]["error"])) {
            // batch upload
			$name = null;
			if (!is_dir($target)) {
            	if (!mkdir($target, 0777, true)) return false;
			}

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
		if ($filter&&$filter!=='*') {
			if (!in_array(strtolower($ext), $filter)) return false;
		}
        $tmp_name = $_FILES[$userfile]['tmp_name'];

        if ($callback) {
            return call_user_func($callback, $tmp_name, $target);
        } else {
			$target = is_dir($target)?"$target/{$_FILES[$userfile]['name']}":$target;
			if (!move_uploaded_file($tmp_name, $target)) return null;
        }
        return $target;
    }
	public function template_ext()
	{
		return defined('EXT')?EXT:null;
	}
}
