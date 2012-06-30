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
    $path = preg_split("/\//", strtolower(preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $class)));
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
        $this->initial();
    }

    public function boot()
    {
        $stream = $this->mapping($this->request('_PATH'));
        $this->stream = array_merge((array)$stream, $this->stream);  // overide with stream from initial() if it's applied
        if (!$this->stream) $this->page_not_found($this->request('_PATH'));
        // all model access via controller will needs verification
        if (!$this->model_verify($this->stream['model'])) {
            $message = "Unauthorized access.".($this->conf['global']['DEBUG']?'[model:'.$this->stream['model'].']':null);
            $this->page_not_found($message);
        }
        $class = preg_replace("/\W/",'',$this->stream['model']);
        $app = new $class($this->conf, $this->stream);
        $app->stream = $app->boot();
        if ($content=$app->load_view($app->stream['view'], $app->stream['data'], $app->stream['suffix'])) $this->output($content, $app->stream['format']);
        if (!$app->stream['ajax']) $this->page_not_found();
    }
    protected function initial(){}    // reserved for customization
}

$ctl = new Controller();
$ctl->boot();
