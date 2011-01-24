<?php
// -------------------------------------------------------------------------------+
// | Name: App - Base class and common mthods shared by application modules       |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu <kevin@cgtvgames.com>                                   |
// -------------------------------------------------------------------------------+
// | Release: 2011.01.18                                                          |
// -------------------------------------------------------------------------------+
//

class Model extends Core
{
    public function session_auth() {return true;}
    public function action_auth()  {return true;}
	function __construct($conf=null)
	{
		$this->conf = $conf;
		parent::__construct();
	}
	function initial(){}	// reserved for customization
	function handler($method, $_sp_STACK)
	{
		$this->stream = array('view'=>$method,'data'=>null,'format'=>'html','param'=>$_sp_STACK);
		$base_dir = APP_DIR."/model/handler";
		$class = strtolower(get_class($this));
		$handler = "$base_dir/$class/$method.inc.php";
		if (!file_exists($handler)) $handler = "$base_dir/$method.inc.php";
		if (file_exists($handler)) include($handler);
		if (method_exists($this, $method)) call_user_func(array($this, $method), $this->stream['param']);
        $content = $this->load_view($this->stream['view'], $this->stream['data']);
        if ($content) $this->output($content, $this->stream['format']);
	}
	function load_db($dsn,$name=null)
	{
		if (!$name) $name = 'db';
		$dbdriver = ucfirst(strtolower($dsn['dbdriver']));
		if (!file_exists(CORE_DIR.'/db/'.strtolower($dbdriver).'.class.php')) return null;
		if (!isset($this->$name)||!is_object($this->$name)) {
			$this->$name = new $dbdriver($dsn);
		}
		return $this->$name;
	}
    function param($key=null)
    {
        return isset($key)?@$this->param_page[$key]:$this->param_page;
    }

	function logout()
	{
		$this->clear();
		$this->output($this->load_view('logout'));
	}

    function clear()
    {
		$this->mysession('clear');
		unset($_COOKIE);
        return true;
    }
	function hasharray2array($lines, $fkey, $fval=null)
	{
		$array = array();
		foreach ($lines as $line) {
			if (isset($fval)) {
				$array[$line[$fkey]] = $line[$fval];
			} else {
				$array[] = $line[$fkey];
			}
		}
		return $array;
	}
	function hasharray_options($lines, $in=null, $field_key, $field_val=null)
	{
		$result = null;
		foreach ($lines as $line) {
			$result .= sprintf("<option value=\"%s\" %s>%s</option>\n",
				htmlspecialchars($line[$field_key]), $line[$field_key]==$in?'selected':null, htmlspecialchars($line[isset($field_val)?$field_val:$field_key]));
		}
		return $result;
	}
    function hash_options($status_list, $status = null)
    {
        $result = null;
        foreach ($status_list as $val => $name) {
           $result .= sprintf("<option value=\"%s\" %s>%s</option>\n",
                      $val, $val==$status ? 'selected': null, htmlspecialchars($name));
        }
        return $result; 
    }
	function array_options($array, $in=null)
	{
        $opt = null;
        foreach ($array as $item) {
           $opt .= sprintf("<option value=\"%s\" %s>%s</option>", htmlspecialchars($item),$item==$in ? 'selected':null, htmlspecialchars($item));
        }
        return $opt;
    }
	// external phpmailer package is required under model folder
    function sendmail($from, $to, $subject, $content, $param=array())
    {
		$default = array(
			'content_type'=>'text/html',
			'charset'=>'utf-8',
			'relay'=>null
		);
        $mail = new phpmailer();
        $mail->IsSMTP();
        //$mail->Port = 25;
		if (preg_match("/^(.*)<(.*)>$/", $from, $p)) {
			$mail->From = trim($p[2]);
			$mail->FromName = $p[1];
		} else {
			$mail->From = $from;
		}
        $mail->Timeout = 30;
        $mail->AddAddress($to);
		if (isset($param['css'])&&is_array($param['css'])) {
	        foreach ($param['css'] as $cc) {
    	       $mail->AddAddress($cc);
       		}
		}
        $mail->ContentType = isset($param['content_type'])?$param['content_type']:$default['content_type'];
        $mail->CharSet = isset($param['charset'])?$param['charset']:$default['charset'];
		if (isset($param['header'])) $mail->addCustomHeader($param['header']);
		if (isset($param['linesize'])) $mail->WordWrap = $param['linesize'];
        $mail->Subject = $subject;
        $mail->Body = $content;
		//$mail->SMTPDebug = true;
		$result = array();
		if (isset($param['relay'])) {
			$mxes = array($param['relay']);
		} else {
            list ($box, $host) = preg_split("/@/", $to);
			if (isset($this->mxes[$host])) {
				$mxes = $this->mxes[$host];
			} elseif (!getmxrr($host, $mxes)) {
                $mxes = array($host);
            }
			$this->mxes[$host] = $mxes;
		}
        $ok = false;
        for ($i=0; $i<count($mxes); $i++) {
            $mail->Host = $mxes[$i];
            if ($mail->Send()) {
                $ok = true;
                break;
            }
        }
        return $ok;
    }
}
