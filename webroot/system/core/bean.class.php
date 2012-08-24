<?php
// -------------------------------------------------------------------------------+
// | Name: Model - Base class and common mthods shared by application modules     |
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

// Root for application models.
// All application libraries should use namespace under Model
// e.g class Class1Class2Class3 extends Model {}
// the class file should be app/model/class1/class2/class3.class.php

class Model extends Core
{
	public $stream = array();

	protected $dependencies = array();	// libraries the model denpends on
	const HOOK_MODEL = 'model';
	const HOOK_LANGUAGE = 'client_language';	// hook in $GLOBALS for language dictionary

	const ENV_URL_PARAM_FRAG = 'UrlParamFrag';	// entrance in env for parameters via url frags

	public function __construct($conf)
	{
		// merge dependencies defined in parent classes
		$parent = get_parent_class($this);
		while (preg_match("/^Mo[A-Z]/", $parent)) {
			$vars = get_class_vars($parent);
			$this->dependencies = array_merge((array)$this->dependencies, (array)@$vars['dependencies']);
			$parent = get_parent_class($parent);
		}
		// then loading dependencies
		$this->conf = $conf;
		// loading libraries
		$this->load_dependencies();
		$this->initial();
	}

	/*** void boot(array $stream)
	 *	@description	boottrap for model which is mapped from url and usually called by controller
	 *	@input	$stream	parameters from url analysis
	 *	@outpt	flush out model output
	***/
	public function boot($stream)
	{
        if ($stream_verify=$this->component_locator($stream['model'], $stream['method'])) {
            $stream = array_merge($stream, $stream_verify);
            $this->component($stream);
        } else {
            $this->route($stream['offset']?substr($stream['url'],strlen($stream['offset'])):$stream['url']);
        }

	}
	// This method should be overrided with application's own route logic if it applied
	public function route($url)
	{
		$this->page_not_found($url);
	}
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
	/*** string load_component(string $model, string $method[, array $conf=null[,bool $output=false]])
	 *	@description	Loading given component by return content in string
	 *	@input	$model	model name
	 *			$metho	method name
	 *			$conf	attributes: array(key1=>val1,...)
	 *			bool	$output	output control, true for client side output,false for returns string
	 *	@return	content of component
	***/
	public function load_component($model, $method, $conf=null, $output=false)
	{
		// verify component existence
		if ($stream=$this->component_locator($model, $method)) {
			$stream['conf'] = $conf;
			return $this->component($stream, $output);
		}
		return $output?$this->page_not_found("$model::$method"):"<a title=\"$model::$method\" style=\"color:red;\">".$this->language_tag('UNKNOWN_COMPONENT')."</a>";
	}
	protected function initial(){}	// reserved for customization
	/** Handler Locator **/
	protected function handler()
	{
		if (substr($this->stream['method_file'], -8)!=='.inc.php') $this->stream['method_file'] .= '.inc.php';
		if (file_exists($this->stream['method_file'])) {
			include($this->stream['method_file']);
		}
		return $this->stream;
	}
	/*** string language_tag(string $tag)
	 *	@description find a translation for given tag in client language
	 *	@input	$tag	tag name
	 *	@return	translation of tag
	***/
	protected function language_tag($tag)
	{
		if (!$language=$this->cookie('language')) $language='en';
		if (!$dics=$this->globals(self::HOOK_LANGUAGE)) {
			$dic_file = APP_DIR.'/conf/languages/'.$language.'.ini';
			if (file_exists($dic_file)) {
				$dics = parse_ini_file($dic_file);
				$this->globals(self::HOOK_LANGUAGE, $dics);
			} else {
				$dics = array();
			}
		}
		return @$dics[$tag];
	}
	/*** verify model access via domain setting in configure file ***/
	private function domain_verify($model)
	{
		if (!isset($this->conf['domain'][$model])) return true; // no domain restriction
		$domains = preg_split("/,/", preg_replace("/\s/", "", strtolower($this->conf['domain'][$model])));
		return in_array($this->env('DOMAIN'), $domains);
	}
	/*** mix component(array $stream[, bool $output=true])
	 *	@description	instantiating a component by give stream
	 *	@input	array $stream	component definition with parameters
	 *			bool  $output	output control, output to client if true, or returns content in string.
	 *	@return	output to client if true, or returns content in string.
	***/
	public function component($stream, $output=true)
	{
		$error = $this->language_tag('ACCESS_DENIED');
        // Verify model visibility by configuration
        if (!$this->model_verify($stream['model'])) {
            $message = $error.($this->conf['global']['DEBUG']?('[model:'.$stream['model'].']'):null);
			if ($output) $this->output($message,'html');
			return "<a title=\"$message\" style=\"color:red;\">$error</a>";
        }
		if (!$this->action_verify($stream['model'], $stream['method'])) {
            $message = $error.($this->conf['global']['DEBUG']?('[model:'.$stream['model'].'::'.$stream['method'].']'):null);
			if ($output) $this->output($message,'html');
			return "<a title=\"$message\" style=\"color:red;\">$error</a>";
		}
        // set cache flag off first
        $stream['cache'] = isset($stream['cache'])?intval($stream['cache']):(isset($stream['conf']['cache'])?intval($stream['conf']['cache']):0);
        $handler_id = $this->env('DOMAIN').serialize($stream).serialize($_REQUEST);
        $stream['cache_handler'] = strlen($handler_id).bin2hex(md5($handler_id));
        // check cacke first
        $content = $this->cache($stream['cache_handler']);
        if (!$content) {
            // fresh loading
			if (isset($this->conf['global']['benchmark'])&&$this->conf['global']['benchmark']) {
				$watch_point = $stream['model'].'::'.$stream['method'];
				$this->benchmark($watch_point, true);
			}
			$app = $this->load_model($stream['model']);
			$orig_stream = $app->stream;
			$app->stream = $stream;
		//	$app = new $stream['model']($this->conf, $stream);
            $stream = $app->handler();
            $content = $app->load_view($stream['view_file'], @$stream['data'], @$stream['suffix']);
            // check cache contol
            if (intval($stream['cache'])>0) {
                // cache content
                $app->cache($stream['cache_handler'], $content, intval($stream['cache']));
            }
			$app->stream = $orig_stream;
			if (isset($watch_point)) {
				$this->benchmark($watch_point);
				if ($output) $this->benchmark_log();
			}
        }
		return  $output?$app->output($content, @$stream['format']):$content;
	}
	/*** mix component_param(mix $keys)
	 *	@description get component parameters via http,configure, url
	 *		Should be called from inside of component (method)
	***/
	protected function component_param($keys)
	{
		if (!is_array($keys)) $keys = array($keys);
		$vals = array();
		foreach ($keys as $key) $vals[$key]=null;
		if ($val=$this->request($keys[0])) {	// check post/get frist
			$vals[$keys[0]] = $val;
			for ($i=1; $i<count($keys); $i++) $vals[$keys[$i]] = $this->request($keys[$i]);
		} elseif ($val=@$this->stream['conf'][$keys[0]]) {	// check component configure
			$vals[$keys[0]] = $val;
			for ($i=1; $i<count($keys); $i++) $vals[$keys[$i]] = @$this->stream['conf'][$keys[$i]];
		} else {	// check url
			for ($i=0; $i<count($this->stream['param']); $i++) {
				if (in_array($this->stream['param'][$i], $keys)) $vals[$keys[$i]] = @$this->stream['param'][$i+1];
			}
		}
		return count($keys)==1?$vals[$keys[0]]:$vals;
	}
	/*** loading model and put it on hook
	 *	@brach	Model
	**/
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
	/*** mapping component to url 
	 *	@brach	Model
	**/
	public function component_url($model_name, $method, $ajax=true)
	{
		$class_name = $this->model_name($model_name, true); // force to class name format
		$path_name = $this->model_name($model_name, false);
		$url = isset($this->conf['route'][$class_name])?$this->conf['route'][$class_name]:('/'.$path_name);
		if ($ajax) $url .= ($url=='/'?null:'/').$this->conf['global']['ajax_frag'];
		return $url.($url=='/'?null:'/').$method;
	}

	/*** string component_id([int $seq=null[,string $method=null[, string $model=null])
	 *	@description generate component DOM id based on stream for given sequence
	 *	@input	$seq	sequence to aviod duplicated id
	 *	@return	html DOM id
	***/
	public function component_id($seq=null, $method=null, $model=null)
	{
		if (!$method) $method = $this->stream['method'];
		if (!$model) $model = $this->stream['model'];
		if (!isset($seq)) $seq = $this->sequence(0);
		return $model.'-'.$method.($seq?('-'.$seq):null);
	}
	/*** void access_denied([$message=null])
	 *	@description  message printer for deny of component/action access, mostly this is called in handler
	 *	@input	$message	the message print on client side, debug info will be given if message is omitted.
	***/
	protected function access_denied($message=null)
	{
		$this->output($message?$message:"Error!!! Access Denied. [{$this->stream['model']}::{$this->stream['method']}]");
	}
	/*** array conf_model()
	 *	@description get listing of accessable models based on configure file
	 *	@input none
	 *	@return	list of model name=>file
	 *	@brach	Model
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

	/*** string key_gen([int $length=8[,string $salt=null]])
	 *	@description generate key for token by given salt (optional), default salt is salt in conf file
	 *	@input	$int	length of key in character between 4 ~ 32, default is 8 chars
	 *			$salt	salt for ken generating
	 *	@return	key in string
	***/
	protected function key_gen($length=8, $salt=null)
	{
		if (!$salt) $salt = $this->conf['global']['salt'];
		$length = max(4, min(32, $length));
		return substr(md5($this->session('id').$salt), 0, $length);
	}
	/*** Simple Xor encrypt/decrypt using session id based key, mainly for token***/
	protected function s_encrypt($plain_text, $key=null)
	{
		$key = md5($key.$this->session('id'));
		return bin2hex($this->xor_crypt($key, $plain_text));
	}
	protected function s_decrypt($enc_str, $key=null)
	{
		if (!strlen($enc_str)) return $enc_str;
		$key = md5($key.$this->session('id'));
		return $this->xor_crypt($key, pack('H*',$enc_str));
	}
	/** Set Stream to json format for ajax request **/
	protected function ajax($name=null, $val=null)
	{
		$this->stream['ajax'] = true;
	    $this->stream['view_file'] = null;
    	$this->stream['format'] = 'json';
		if (!isset($this->stream['data'])) {
			// initial
	    	$this->stream['data'] = array('success'=>false,'message'=>null);
		}
		if (isset($name)&&isset($val)) return $this->stream['data'][$name] = $val;
		if (!isset($val)&&is_array($name)) {
			$this->stream['data'] = array_merge($this->stream['data'], $name);
		}
	}
	/*** array component_list($class_name)
	 *	@description	get list of component for given model
	 *	@input	bool $class_name 	model class_name 
	 *	@return	array 	component array like array(array('model1'=>array('method1'=>'desc','method2'=>'desc',..,),...,'modelN'=>...)
	***/
	protected function component_list($class_name)
	{
		$bean_root=$root=APP_DIR.'/resource/beans';
		$root = dirname($this->bean_file($class_name, false));	// the root of given bean
		if (!file_exists("$root/model.class.php")) return array(); // no model exists.
		$components = array();
		$model = $this->class_name_format($class_name);
		foreach (glob("$root/handler/*.inc.php") as $file) {
			$method = array_shift(preg_split("/\./", basename($file)));
			$desc = preg_match("/\*\s+@description\s([^\n]+)/msi", file_get_contents($file), $p)?trim($p[1]):'';
			$components["$model::$method"] = $desc;
		}
		return $components;
	}
	protected function bean_list($active=true)
	{
        $bean_root = APP_DIR.'/'.($active?'beans':'resource/beans');
        $bean_folders = $this->_bean_dir_scan($bean_root);
		$beans = array();
		foreach ($bean_folders as $bean_folder) {
			$name = str_replace(' ','', ucwords(join(' ', preg_split("/\//", substr($bean_folder, strlen($bean_root))))));
			$desc = file_exists("$bean_folder/README")?file_get_contents("$bean_folder/README"):'';
			$beans[$name] = $desc;
		}
		return $beans;
	}
	private function _bean_dir_scan($bean_root, $recursive=true)
	{
		$beans = array();
		foreach (glob("$bean_root/*", GLOB_ONLYDIR) as $dir) {
			if ($dir=='.'||$dir=='..') continue;
			if (count(glob("$dir/*.class.php")))  {
				$beans[] = $dir;
				if ($recursive) $beans = array_merge($beans, $this->_bean_dir_scan($dir, $recursive));
			}
		}
		return $beans;
	}
    protected function hasharray_options($lines, $field_key, $field_val=null, $in=null)
    {
        $result = null;
        foreach ((array)$lines as $line) {
            $result .= sprintf("<option value=\"%s\" %s>%s</option>\n",
                htmlspecialchars($line[$field_key]), strcmp($line[$field_key],$in)===0?'selected':null, htmlspecialchars($line[isset($field_val)?$field_val:$field_key]));
        }
        return $result;
    }
    protected function hash_options($status_list, $status = null)
    {
        $result = null;
        foreach ($status_list as $val => $name) {
           $result .= sprintf("<option value=\"%s\" %s>%s</option>\n",
                      $val, strcmp($val,$status)===0? 'selected': null, htmlspecialchars($name));
        }
        return $result;
    }
    protected function array_options($array, $in=null)
    {
        $opt = null;
        foreach ($array as $item) {
           $opt .= sprintf("<option value=\"%s\" %s>%s</option>", htmlspecialchars($item),strcmp($item,$in)===0? 'selected':null, htmlspecialchars($item));
        }
        return $opt;
    }
    public function group_options($lines, $field_key, $field_val, $field_category, $in=null)
    {
        $grp_options = null;
        $cate_flag = 0;
        foreach ($lines as $line) {
            if ($line[$field_category]) {
                if ($cate_flag) $grp_options .= "</optgroup>\n";
                $cate_flag = $line[$field_category];
                $grp_options .= sprintf("<optgroup label=\"%s\">", htmlspecialchars($line[$field_val]));
            } else {
                $grp_options .= sprintf("<option value=\"%s\" %s>%s</option>", $line[$field_key], strcmp($line[$field_key],$in)===0?'selected':null, htmlspecialchars($line[$field_category]));
            }
        }
        if ($cate_flag) $grp_options .= "</optgroup>\n";
        return $grp_options;
    }
}
// Root for application libraries.
// All application libraries should use namespace under Library
// e.g class Class1Class2Class3 extends Library {}
// the class file should be app/library/class1/class2/class3.class.php
class Library extends Core
{
	protected $conf = null;
	protected $operator = null;	// current user
	protected $dependencies = array();	// libraries the model denpends on
    public function __construct($conf=null)
	{
		// merge dependencies defined in parent classes
		$parent = get_parent_class($this);
		while (preg_match("/^Lib[A-Z]/", $parent)) {
			$vars = get_class_vars($parent);
			$this->dependencies = array_merge((array)$this->dependencies, (array)@$vars['dependencies']);
			$parent = get_parent_class($parent);
		}
		// then loading database, dependencies
		$this->conf = $conf;
		if ($dsn=$this->dsn_parse()) $this->load_db($dsn, $this);
		$this->load_dependencies();
	}
	public function get_error($key=null)
	{
		if (!$this->status['error_code']) return null;
		$this->status['class'] = get_class($this);
		return isset($key)?@$this->status[$key]:$this->status;
	}

	/*** string dsn_parse()
	 *	@description	get dsn string in standard format (e.g. default.schema) based on dsn configure
	 *	@return		string entry_name.schema
	***/
	protected function dsn_parse()
	{
		$class = get_class($this);
		$folder = preg_replace("/([a-z0-9])([A-Z])/", "\\1/\\2", $class);
		$dsn = null;
        if (isset($this->conf['dsn'][$class])) {
			$dsn = $this->conf['dsn'][$class];
		} else {
			$path = preg_split("/\//", $folder);
			while (count($path)) {
				array_pop($path);
				$name = join('',$path).'*';
				if (isset($this->conf['dsn'][$name])) {
					$dsn = $this->conf['dsn'][$name];
					break;
				}
			}
		}
		if (!$dsn) $dsn = 'default.'.$this->conf['database']['default']['database'];
		$r = preg_split("/\./", $dsn);
		if (!isset($r[1])||!$r[1]) $r[1] = $this->conf['database'][$r[0]]['database'];
		return $r[1]?$this->dsn = join('.', $r):null;
	}
	protected function load_db($dsn, $hook)
	{
		// load db driver first
		if (!is_object($hook)) $hook = new stdClass();
		list($dsn_name, $schema) = preg_split("/[\.\/]/", $dsn);
		$s_hook = "$dsn_name.$schema";
        $libraries = $this->globals(CORE::HOOK_DB);
		if (isset($libraries['db'][$s_hook])) {
			$hook->db = $libraries['db'][$s_hook];
		} elseif (isset($this->conf['database'][$dsn_name])) {
			$conf = $this->conf['database'][$dsn_name];
			if (!$conf['dbdriver']) return null;	// no driver given
			$folder = dirname(__FILE__).'/dao';
			$dbdriver = ucfirst(strtolower($conf['dbdriver']));
			$driver_file = $folder.strtolower("/$dbdriver.class.php");
			if (!file_exists($driver_file)) {
				$driver_file = $folder."/dao.class.php"; // using default (generic) db class
				$dbdriver = 'Dao';
			}
			include_once($driver_file);
			$libraries['db'][$s_hook] = new $dbdriver($conf);
			$this->globals(CORE::HOOK_DB, $libraries);
			$hook->db = $libraries['db'][$s_hook];
		} else {
			$this->error("Error! Invalid DSN: $conf.");
		}
		// hook table objects defined in current class and parent classes
		$folder = dirname($this->bean_file(get_class($this)));
		$ini_files = array();
		while (strpos($folder, APP_DIR.'/beans/')===0) {
			$tbl_ini_file = $folder."/database/db.tbl.ini";
			if (file_exists($tbl_ini_file)) $ini_files[] = $tbl_ini_file;
			$folder = dirname($folder);
		}
		foreach ($ini_files as $ini_file) {
        	if (!isset($hook->tbl)) $hook->tbl = new stdClass();
			$tables = array_change_key_case(parse_ini_file($ini_file, true), CASE_LOWER);
			foreach ((array)$tables as $table=>$def) {
				if (!isset($hook->tbl->$table)) {
            		if (!@$def['schema']) $def['schema'] = $schema;
	            	$hook->tbl->$table=$hook->db->load_table($def);
				}
			}
		}
		return $hook;
	}
}
