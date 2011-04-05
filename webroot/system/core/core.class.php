<?php
// -------------------------------------------------------------------------------+
// | Name: Core - I/O class for both Controller and App model                     |
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

Class Core
{
	protected $w3s_zones = array('core'=>CORE_DIR,'application'=>APP_DIR);
	const W3S_SEQ = 'w3s_sequence';
	public function request($name, $method=null)
	{
		$method = strtolower($method);
		if ($method&&!$name) {
			return $method=='get'?$_GET:$this->_postvars($_POST);
		}
		if ($method!='get') {
			$val = $this->_postvars($_POST, $name);
			if (isset($val)) return $val;
			if ($name==='_DOMAIN') {
				$r = preg_split("/\./", strtolower($_SERVER['HTTP_HOST']));
				if ($r[0]==='www') array_shift($r);
				return join('.', $r);
			} elseif ($name==='_URL') {
				return isset($_GET['_ENTRY'])?$_GET['_ENTRY']:'/';
			}
		}
		return isset($_GET[$name])?trim($_GET[$name]):null;
	}
	public function configure()
	{
		$confs = array('database', 'common', $this->request('_DOMAIN'));
		$conf_dir = APP_DIR."/conf";
    	foreach ($confs as $conf_file) {
			$file = "$conf_dir/$conf_file.php";
			if (file_exists($file)) include($file);
		}
		return $conf;
	}
	private function _postvars($post, $key=null)
	{
		$magic = get_magic_quotes_gpc();
		if ($key&&!isset($_POST[$key])) return null;
		foreach ($post as $k=>$v) {
			if ($key&&$k!==$key) continue;
			$post[$k] = is_array($v)?$this->_postvars($v):trim($magic?stripslashes($v):$v);
		}
		return isset($key)?(isset($post[$key])?$post[$key]:null):$post;
	}
    public function load_view($view_name, $bind=null, $ext=null)
    {
		$temp_base = APP_DIR."/view";
		return $this->load_template($temp_base, $view_name, $bind, $ext);
	}
	public function load_template($temp_base, $view_name, $bind=null, $ext=null)
	{
        if (!$view_name) return $bind;
        $templates = array($temp_base."/$view_name.tpl.php");
		if (isset($ext)||($ext=$this->template_ext())) {
			array_unshift($templates, $temp_base."/$view_name.tpl.php".$ext);
		}
		$template = null;
		foreach ($templates as $temp) {
	        if (file_exists($temp)) {
				$template = $temp;
				break;
			}
		}
		if (!$template) return null;
        if (is_array($bind) && array_keys($bind)!==range(0, count($bind)-1)) {
            foreach ($bind as $key=>$val) {
                $$key = $val;
            }
        }
        ob_start();
        include $template;
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
	public function redirect($url, $code=307)
	{
		header("location:$url", TRUE, $code);
		exit;
	}
    public function file_download($filename, $body)
    {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private",false);
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$filename\";");
        header("Content-Transfer-Encoding: binary");
        header('Content-Length: '.strlen($body));
        echo $body;
        exit;
    }

    /** export
     * output to client site with specific format
    */
    public function output($data, $format=null)
    {
        $types = array(
            'html'=>'text/html',
            'css'=>'text/css',
            'js'=>'text/javascript',
            'xml'=>'text/xml',
            'excel'=>'application/vnd.ms-excel',
            'pdf'=>'application/pdf',
            'csv'=>'application/octet-stream',
            'jpg'=>'image/jpeg'
        );
        $file_exts = array('excel'=>'xls','pdf'=>'pdf','csv'=>'csv');
        header("Pragma: public");  //fix IE cache issue with PHP
        header("Expires: 0");   // no cache
        if ($format) {
            $content_type = isset($types[$format])?$types[$format]:'text/plain';
            header("Content-Type: $content_type");
            if (isset($file_exts[$format])) {
                header("Content-Disposition: attachment; filename='downloaded.{$file_exts[$format]}'");
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
	public function template_ext()
	{
		return defined('EXT')?EXT:null;
	}
    public function mysession($name, $val=null)
    {
        if (!session_id()) session_start();
        if ($name=='clear') {
            $_SESSION = array();
            session_destroy();
            return true;
        }
        if (!isset($val)) return @$_SESSION[$name];
        return $_SESSION[$name] = $val;
    }
    public function logging($info, $log_file=null)
    {
        if (!$log_file) $log_file = APP_DIR. '/logs/'.strtolower(get_class($this)).'.log';
        $log_info = sprintf("%s %s\n",date('Y-m-d H:i:s'),$info);
        if ($log_file==='PRINT') {
            echo $log_info;
            return;
        }
        if (file_exists($log_file)){
            $fp = fopen($log_file,"a");
        } else {
            $fp = fopen($log_file,"w");
        }
        fputs($fp,$log_info);
        fclose($fp);
    }
	/** Global Data Storage **/
	protected function global_store($name, $val=null)
	{
		$HOOK = 'W3S';
		if (!isset($val)) return @$_GLOBAL[$HOOK][$name];
		if (!$val&&isset($_GLOBAL[$HOOK][$name])) {
			unset($_GLOBAL[$HOOK][$name]);
		} else {
			$_GLOBAL[$HOOK][$name] = $val;
		}
	}
    /** Unique number generator **/
    protected function sequence($offset=0, $schema=null)
    {
		if (!$schema) $schema = self::W3S_SEQ;
        if ($offset=='reset') return $this->mysession($schema, 1);
        $seq = intval($this->mysession($schema)) + $offset;
        if ($offset) $this->mysession($schema, $seq);
        return $seq;
    }
	/** Set Stream to json format for ajax request **/
	protected function ajax()
	{
	    $this->stream['view'] = null;
    	$this->stream['format'] = 'json';
	    $this->stream['data'] = array('success'=>false,'meesage'=>null);
	}
}
