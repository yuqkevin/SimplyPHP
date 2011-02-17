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

class Model extends Core
{
	protected $dsn_name = null;
	protected $tables = null;
    function session_auth() {return true;}
    function action_auth()  {return true;}
	function __construct($conf=null)
	{
		$this->conf = $conf;
		if ($this->dsn_name) {
			$this->db = $this->load_db($this->conf['dsn'][$this->dsn_name]);
			if ($this->db&&is_array($this->tables)) $this->db->tables($this->tables);
		}
		$this->initial();
	}
	function initial(){}	// reserved for customization
	function handler($map)
	{
		$this->stream = $map;
		$base_dir = APP_DIR."/model/handler";
		$class = strtolower($this->stream['model']);
		$handler = "$base_dir/$class/{$this->stream['method']}.inc.php";
		if (!file_exists($handler)) $handler = "$base_dir/{$this->stream['method']}.inc.php";
		if (file_exists($handler)) {
			include($handler);
		} elseif (method_exists($this, $this->stream['method'])) {
			call_user_func(array($this, $this->stream['method']), $this->stream['param']);
		}
        return $this->stream;
	}
	function load_db($dsn,$name=null)
	{
		if (!$name) $name = 'db';
		$dbdriver = ucfirst(strtolower($dsn['dbdriver']));
		if ($dbdriver&&(!isset($this->$name)||!is_object($this->$name))) {
			$this->$name = new $dbdriver($dsn);
		}
		return $dbdriver?$this->$name:null;
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
	function hasharray_options($lines, $field_key, $field_val=null, $in=null)
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
