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
			'view'=>null,
			'folder'=>APP_DIR."/model",
			'data'=>null,
			'suffix'=>null,
			'format'=>'html'
		);
		$url = $this->request('_URL');
		foreach ($this->conf['model'] as $offset=>$model) {
			if (preg_match("|^$offset/?(.*)|i", $url, $p)) {
				$stream['offset'] = $offset;
				$stream['model'] = $model;
				if ($p[1]) {
					$stream['param'] = preg_split("|/|", $p[1]);
					$stream['method'] = array_shift($stream['param']);
				}
				$stream['folder'] .= '/'.strtolower($model);
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
