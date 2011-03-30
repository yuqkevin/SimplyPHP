<?php
// -------------------------------------------------------------------------------+
// | Name: Messenger - Library of message delivery functions                      |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// -------------------------------------------------------------------------------+
// | Repository: https://github.com/yuqkevin/SimplyPHP/                           |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu                                                         |
// -------------------------------------------------------------------------------+
// | Checkout: 2011.03.30                                                         |
// -------------------------------------------------------------------------------+
//

class Messenger
{
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

