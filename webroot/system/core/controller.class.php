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

function __autoload($class)
{
	$file_name = strtolower($class).'.class.php';
    $class_files = array(CORE_DIR.'/'.$file_name, CORE_DIR.'/db/'.$file_name, APP_DIR.'/model/'.$file_name);
	foreach ($class_files as $file) {
		if (file_exists($file)) {
			require_once($file);
			return true;
		}
	}
	throw new Exception("Unable to load $class.");
    return false;
}

Class Controller extends Core
{
	protected $conf = null;
    protected $stream = array();
	function __construct()
	{
		$this->configure();
		$this->initial();
	}
	function initial(){}	// reserved for customization
	function configure()
	{
		$confs = array('database', 'common', $this->request('_DOMAIN'));
		$conf_dir = APP_DIR."/conf";
    	foreach ($confs as $conf_file) {
			$file = "$conf_dir/$conf_file.php";
			if (file_exists($file)) include($file);
		}
		$this->conf = $conf;
	}
	function mapping()
	{
		$stream = array(
			'model'=>null,
			'method'=>'index',
			'param'=>null,
			'view'=>'index',
			'data'=>null,
			'suffix'=>null,
			'format'=>'html'
		);
        if ($entry=preg_replace(array("|^/+|","|/+$|"),array('',''), $this->request('_URL'))) {
            $r = split('/', $entry);
			$stream['model'] = array_shift($r);
            $stream['method'] = count($r)?array_shift($r):'index';
			$stream['view'] = strtolower($stream['model']).'/'.$stream['method'];
            if (count($r)) $stream['param'] = $r;
        }
		$this->stream = array_merge($stream, $this->stream); // this->stream can be overrided in initial()
		return $this->stream;
	}
    function boot()
    {
		$this->mapping();
		if (!$this->stream['model']) {
			if (!$this->conf['model']) {
				$this->output($this->load_view('welcome'));
			}
			$this->stream['model'] = $this->conf['model'];
		}
		try {
			$this->app = new $this->stream['model']($this->conf);
            $stream = $this->app->handler($this->stream);
            if ($content=$this->app->load_view($stream['view'], $stream['data'], $stream['suffix'])) {
                $this->output($content, $stream['format']);
            } else {
                $this->output($this->load_view('page_not_found',array('url'=>$this->request('_URL')),$stream['suffix']));
            }
		} catch (Exception $e) {
		    $this->output($this->load_view('page_not_found',array('url'=>$this->request('_URL')),$this->stream['suffix']));
		}
    }
}
