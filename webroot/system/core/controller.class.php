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
    $path = preg_split("/\//", preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $class));
	$path[0] = ucfirst($path[0]);
    if ($path[0]===Core::PREFIX_LIB) {
        $file = null;
        $class_file = 'lib.class.php';
        array_shift($path);
    } elseif ($path[0]===Core::PREFIX_MODEL) {
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
Class Controller extends Web
{
    protected $conf = null;
    protected $stream = array();

    public function __construct(){
        $this->conf = $this->configure();
    }

    public function boot()
    {
		$model = new Model($this->conf);
		$model->switch_to();
    }
}

$ctl = new Controller();
$ctl->boot();
