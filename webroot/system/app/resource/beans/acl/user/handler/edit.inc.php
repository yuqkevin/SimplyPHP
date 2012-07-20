<?php
/**
 * @description Account Create/Update
 * New Account: same dna,domain as operator
***/
$user = $this->get_lib('LibAclUser');
$id = intval($this->request('id'));
$token_key = $this->token_key().$id;
$act = $this->s_decrypt($this->request('token'), $token_key);
if (!$timestamp=$this->request('timestamp')) $timestamp = time();

$user_nonce = $this->request('nonce');
$server_nonce = md5($token_key.$timestamp);
switch ($act) {
	case 'create':
	case 'edit':
		$operator = $this->user_info();
		$ui = $this->load_lib('LibUiHtml');
		if ($act==='create') {
			$this->stream['data']['token'] = $this->s_encrypt('update', $token_key);
		} else {
			$this->stream['data']['token'] = $this->s_encrypt('update', $token_key);
			$this->stream['data']['account'] = $user->account('read', $id);
		}
		$groups = $this->hasharray2array($user->group_search(array('dna'=>$operator['dna'])),'id','name');
		$this->stream['data']['group_options'] = $ui->hash_options($groups, @$account['group']);
		$this->stream['data']['timestamp'] = $timestamp;
		$this->stream['data']['nonce'] = $server_nonce;
		$this->stream['data']['act'] = $act;
		break;
	case 'update':
		$this->ajax();
		if ($error_code=$user->nonce_verify($user_nonce, $server_nonce, $timestamp)) {
			$this->stream['data']['message'] = $this->language_tag($error_code);
			return;
		}
		$param = array('nickname'=>1,'comments'=>null);
		foreach ($param as $key=>$flag) {
			$param[$key] = $this->request($key);
			if ($flag&&!$param[$key]) return $this->stream['data']['message']=$this->language_tag('DATA_INSUFFICIENT');
		}
		if ($id) {
			$ok = $user->account('modify', $id, $param);
		} else {
			if (!$param['login']=$this->request('login')) return $this->stream['data']['message']=$this->language_tag('DATA_INSUFFICIENT');
			$ok = $user->account('create', 0, $param);
		}
		if ($ok) {
			$this->stream['data']['success'] = 'close&reload';
			$this->stream['data']['target'] = '#main-column';
		} else {
			$this->stream['data']['message']=$this->language_tag('DATA_SUBMIT_FAILURE');
		}
		break;
}
