<?php
// -------------------------------------------------------------------------------+
// | Name: Controller                                                             |
// +------------------------------------------------------------------------------+
// | Package: SimplyPHP Framework                                                 |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu <kevin@w3softwares.com>                                 |
// -------------------------------------------------------------------------------+
// | Release: 2011.01.18                                                          |
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
    return false;
}

Class Controller extends Core
{
	var $map = array('model'=>'welcome','method'=>'index','param'=>null);
	function __construct($conf=null)
	{
		$this->conf = $conf;
		parent::__construct();
	}
	function initial(){}	// reserved for customization
	function mapping()
	{
        if ($entry=$this->request('_ENTRY')) {
            $r = split('/', $entry);
            $this->map['method'] = array_shift($r);
            if (count($r)) $this->map['param'] = $r;
        }
		return $this->map;
	}
    function boot()
    {
		$this->mapping();
		$this->app = new $this->map['model']($this->conf);
        $this->app->handler($this->map['method'], $this->map['param']);
        $this->output($this->load_view('page_not_found',array('url'=>'/'.$this->request('_ENTRY'))));
    }
}
