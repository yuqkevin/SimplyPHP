<?php
$libUser = $this->get_lib('LibAclUser');
if (!($act=@$this->stream['param'][0])) return null;
$token = $this->s_decrypt($this->request('_token'), 0);
if ($token==='go') $this->ajax(); // submit form
switch ($act) {
	case 'login':
		if ($token=='go') {
			if ($libUser->sign_in($this->request('w3s-login'),$this->request('w3s-pass'))) {
				$this->stream['data']['success'] = 'reload';
			} else {
				$this->stream['data']['message'] = "Sorry, user id and password is wrong.";
			}
		} else {
			$this->stream['view'] .= '.login';
			if (!isset($this->stream['conf']['src_url'])) $this->stream['conf']['src_url'] = $this->env('PATH');
		}
		break;
	case 'logout':
		$libUser->sign_out();
		$this->ajax();
		$this->stream['data']['success'] = 'reload';
		break;
	case 'regist':
		break;
}
