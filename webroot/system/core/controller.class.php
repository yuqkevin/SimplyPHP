<?php
// -------------------------------------------------------------------------------+
// | Name: Controller                                                             |
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
define('PREFIX_MODEL', 'Mo');  // user model prefix
define('PREFIX_LIB', 'Lib');   // user library prefix
define('GLOBAL_ENV_HOOK','SimplyPhpEnv');	// hook for global variable shared by Cntroller,Model and Library

function __autoload($class)
{
    $path = preg_split("/\//", preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $class));
	$path[0] = ucfirst($path[0]);
    if ($path[0]===PREFIX_LIB) {
        $file = null;
        $class_file = 'lib.class.php';
        array_shift($path);
    } elseif ($path[0]===PREFIX_MODEL) {
        $class_file = 'model.class.php';
        array_shift($path);
    } else {
    	exit("Class Loading Error!!! Invalid class name $class.");
	}
    $file = APP_DIR.'/beans/'.join('/', $path).'/'.$class_file;
    if (file_exists($file)) {
        require_once($file);
    } else {
    	exit("Class Loading Error!!! Unable to load $class: file $file.");
	}
}
require_once(dirname(__FILE__).'/core.class.php');
require_once(dirname(__FILE__).'/bean.class.php');
Class Controller
{
    protected $conf = null;
    protected $stream = array();

	const DEFAULT_ENTRY = 'main';
	/*** boottrap for client request ***/
    public function boot()
    {
        $this->conf = $this->configure();
		$this->env_initial();
		$stream = $this->mapping();
		$model = new $stream['model']($this->conf);
		$model->boot($stream);
    }

	/*** array configure()
	 *	@description	parse configure ini files into array
	 *	@return	configure parameters in associative array
	***/
	protected function configure()
	{
		$domain = preg_replace("/^www\./", "", strtolower($_SERVER['HTTP_HOST']));
		$conf_dir = APP_DIR."/conf";
		$conf = array();
		foreach (array('main', $domain) as $conf_file) {
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
		$conf = array_change_key_case($conf, CASE_LOWER);
		if (!$conf['global']['TIMEZONE']) {
			// get system timezone
			$conf['global']['TIMEZONE'] = function_exists('date_default_timezone_get')?date_default_timezone_get():'UTC';
		}
		// setting application timezone
		if (function_exists('date_default_timezone_set')) date_default_timezone_set($conf['global']['TIMEZONE']);
		if (!isset($conf['global']['index'])) $conf['global']['index'] = self::DEFAULT_ENTRY;
		if (!isset($conf['route'])) {
			$conf['route'] = array();
		} else {
			// overide route table with newer entrances
			$conf['route'] = array_unique(array_reverse($conf['route']));
		}
		if (!isset($conf['domain'])) $conf['domain'] = array();
		if (!isset($conf['access'])) $conf['access'] = array();
		if (!isset($conf['access']['model'])) $conf['access']['model'] = array();
		if (!defined('DEBUG')&&isset($conf['global']['DEBUG'])) define('DEBUG', $conf['global']['DEBUG']); // set global constant: DEGUB
		return $conf;
	}

	/*** void env_initial()
	 *	@description	initializing global environmnet variables
	***/
	protected function env_initial()
	{
		$this->env('DOMAIN', preg_replace("/^www\./", '', strtolower($_SERVER['HTTP_HOST'])));
		$this->env('URL', (@$_SERVER['HTTPS']?'https':'http').'://'.strtolower($_SERVER['HTTP_HOST']).(isset($_GET['_ENTRY'])?$_GET['_ENTRY']:'/'));
		$this->env('PATH', isset($_GET['_ENTRY'])?$_GET['_ENTRY']:'/');
		$this->env('REQUEST', $_SERVER['REQUEST_URI']);
	}

	/** mix mapping([string $url=null[, bool $method_check=false]])
	 *	@description Parsing URL into hash stream
	 *	@input	string $url	url, using current reqest url if omitted
	 *			bool $method_check	force to check method file, give error if method file doesn't exist and method_check is set to true
	 *	@output	array stream with defined parameters
	**/
	protected function mapping()
	{
		$stream = array(
			'offset'=>null,		// matched value of pre-configure model = offset pair, it must be /somthing or null
			'url'=>null,		// url without offset & query
			'request'=>$_SERVER['REQUEST_URI'],	// original url from client
			'model'=>null,		// model formal name: e.g. MoMyModel
			'method'=>null,
			'param'=>null,		// linear array, param from url. e.g. /user/login
			'conf'=>null,		// component conf/initial data in hash. e.g. array('key1'=>val1,'key2'=>val2,...)
			'model_file'=>null,
			'method_file'=>null,  // handler inc file
			'view_file'=>null,	  // view_file tpl file under handler (ajax call) or under view (page call)
			'comp_url'=>null,	// stake url for component call. usually like: /model/ajax_frag/method
			'data'=>null,	// binding data work with view
			'suffix'=>null,
			'ajax'=>false,		// deside view's folder. view stay with handler if true, otherwise in view folder.
			'format'=>'html'
		);
		$url = isset($_GET['_ENTRY'])?$_GET['_ENTRY']:'/';
		
		$r = $this->url2array($url);
		$url = '/'.join('/', $r);
		$stream['url'] = $url;

		// step 1: locate model
		// check configuration route setting first
		$model = $offset = null;
		$frags = array();
		while (true) {
			if ($model=array_search($url, $this->conf['route'])) {
				$offset = $url=='/'?null:$url;
				break;
			}
			if ($url==='/') break;
			array_unshift($frags, basename($url));
			$url = dirname($url);
		}
		if ($offset&&$model) {
			$stream['offset'] = $offset;
			$stream['model'] = $model;
		} else {
			if (count($frags)>=2) {
				if (is_dir(SRC_DIR.'/beans/'.preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $frags[0]))) {
					// case 1: Model/methodName pattern
					$stream['model'] = PREFIX_MODEL.array_shift($frags);
				}
			}
			if (!isset($stream['model'])&&$model) {
				// case 2: using default '/' model
				$stream['model'] = $model;
			} elseif (!isset($stream['model'])) {
				exit("Error!!! No matched model found for {$stream['url']}.\n");
			}
		}
		// step 2. locate handler
		if (count($frags)&&$this->conf['global']['ajax_frag']==$frags[0]) {
			$stream['ajax'] = true;
			array_shift($frags);
		}
		$stream['method'] = count($frags)?array_shift($frags):self::DEFAULT_ENTRY;
		$stream['param'] = $frags;
		return $stream;
	}
    /** convert url to array without empty head or ending **/
    protected function url2array($url)
    {
        if ($url=='/') return array();
        if ($url[0]=='/') $url = substr($url, 1);
        if (substr($url, -1)=='/') $url = substr($url, 0, -1);
        return preg_split("|/|", $url);
    }

	/*** mix env(string $name[, mix $val=null])
	 *	@description	envirnment varibale getter/setter
	 *	@input	$name	variable name as key in env associative array
	 *			$val	value for variable setter
	 *	@return	value of given variable name
	***/
	protected function env($name, $val=null)
	{
		$HOOK = GLOBAL_ENV_HOOK;
		if (!isset($val)) return @$GLOBALS[$HOOK][$name];
		return @$GLOBALS[$HOOK][$name] = $val;
	}
}

$ctl = new Controller();
$ctl->boot();
