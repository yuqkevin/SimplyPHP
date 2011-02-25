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
    protected $map = array('model'=>null,'method'=>'index','param'=>null,'view'=>'index','data'=>null,'format'=>'html','suffix'=>null);
	function __construct()
	{
		$this->configure();
		$this->initial();
	}
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
	function initial(){}	// reserved for customization
	function mapping()
	{
        if ($entry=preg_replace(array("|^/+|","|/+$|"),array('',''), $this->request('_ENTRY'))) {
            $r = split('/', $entry);
			$this->map['model'] = $this->map['model']?$this->map['model']:array_shift($r);
            $this->map['method'] = count($r)?array_shift($r):'index';
			$this->map['view'] = strtolower($this->map['model']).'/'.$this->map['method'];
            if (count($r)) $this->map['param'] = $r;
        }
		return $this->map;
	}
    function boot()
    {
		$this->mapping();
		if (!$this->map['model']) {
			if (!$this->conf['model']) {
				$this->output($this->load_view('welcome'));
			}
			$this->map['model'] = $this->conf['model'];
		}
		try {
			$this->app = new $this->map['model']($this->conf);
            $stream = $this->app->handler($this->map);
            if ($content=$this->app->load_view($stream['view'], $stream['data'], $stram['suffix'])) {
                $this->output($content, $stream['format']);
            } else {
                $this->output($this->load_view('page_not_found',array('url'=>'/'.$this->request('_ENTRY')),$stram['suffix']));
            }
		} catch (Exception $e) {
		    $this->output($this->load_view('page_not_found',array('url'=>'/'.$this->request('_ENTRY')),$stram['suffix']));
		}
    }
}
