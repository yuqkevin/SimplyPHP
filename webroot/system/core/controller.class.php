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
    return false;
}

Class Controller extends Core
{
    var $map = array('model'=>'welcome','method'=>'index','param'=>null,'view'=>'index','format'=>'html');
	function __construct($conf=null)
	{
		$this->conf = $conf;
		$this->initial();
	}
	function initial(){}	// reserved for customization
	function mapping()
	{
        if ($entry=$this->request('_ENTRY')) {
            $r = split('/', $entry);
            $this->map['method'] = array_shift($r);
			$this->map['view'] = strtolower($this->map['model']).'/'.$this->map['method'];
            if (count($r)) $this->map['param'] = $r;
        }
		return $this->map;
	}
    function boot()
    {
		$this->mapping();
		$this->app = new $this->map['model']($this->conf);
        $stream = $this->app->handler($this->map);
        if ($content=$this->load_view($stream['view'], $stream['data'])) {
            $this->output($content, $stream['format']);
        } else {
            $this->output($this->load_view('page_not_found',array('url'=>'/'.$this->request('_ENTRY'))));
        }
    }
}
