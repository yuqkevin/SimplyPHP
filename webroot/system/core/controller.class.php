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
require_once(CORE_DIR."/model.class.php");
Class Controller extends Core
{
	protected $conf = null;
    protected $stream = array();
	function __construct()
	{
		$this->conf = $this->configure();
		$this->initial();
	}
	function initial(){}	// reserved for customization
	function mapping()
	{
		$stream = array(
			'offset'=>null,
			'model'=>$this->conf['model']['default'],
			'method'=>'index',
			'param'=>null,
			'view'=>'index',
			'folder'=>APP_DIR."/model",
			'data'=>null,
			'suffix'=>null,
			'format'=>'html'
		);
		$url = $this->request('_URL');
		foreach ($this->conf['model'] as $offset=>$model) {
			if ($offset==$url||strpos($url, $offset.'/')===0) {
				$stream['offset'] = $offset;
				$stream['model'] = $model;
				$req = substr($url, strlen($offset));
				if (strlen($req)>1) {
					$r = split('/', substr($req,1));
					$stream['method'] = array_shift($r);
					$stream['param'] = $r;
				}
				$stream['folder'] .= '/'.strtolower($stream['model']);
				break;
			}
		}
		$stream['view'] = $stream['folder'].'/'.$stream['method'];
		$this->stream = array_merge($stream, $this->stream); // this->stream can be overrided in initial()
		return $this->stream;
	}
    function boot()
    {
		$this->mapping();
		if (!$this->stream['model']) {
			$this->output($this->load_view('welcome'));
		}
		try {
			$this->app = new $this->stream['model']($this->conf);
            $stream = $this->app->run($this->stream);
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
