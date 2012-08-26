<?php
/**
 * @description Account Create/Update
 * New Account: same dna,domain as operator
***/
$user = $this->get_lib('LibAclUser');
$id = intval($this->request('id'));
$token_key = $this->key_gen().$id;
$act = $this->s_decrypt($this->request('token'), $token_key);
$timestamp = $this->request('timestamp');
$nonce = $this->request('nonce');
switch ($act) {
	case 'create':
	case 'edit':
		$operator = $this->user_info();
		if ($act==='create') {
			$this->stream['data']['token'] = $this->s_encrypt('update', $token_key);
		} else {
			$this->stream['data']['token'] = $this->s_encrypt('update', $token_key);
			$this->stream['data']['account'] = $user->account('read', $id);
		}
		$groups = $this->hasharray2array($user->group_search(array('dna'=>$operator['dna'])),'id','name');
		$this->stream['data']['group_options'] = $this->hash_options($groups, @$account['group']);
		$this->stream['data'] += $user->nonce('new');
		$this->stream['data']['act'] = $act;
		break;
	case 'update':
		$this->ajax();
		if (!$user->nonce('verify', $nonce, $timestamp)) {
			$this->stream['data']['message'] = $user->get_error('message');
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
