<?php
/**
 * @description Group Create/Update
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
			$group_actions = array();
        } else {
            $this->stream['data']['token'] = $this->s_encrypt('update', $token_key);
            $this->stream['data']['group'] = $user->group('read', $id);
			$group_actions = $this->unserialize($this->stream['data']['group']['action']);
        }
        $this->stream['data']['timestamp'] = $timestamp;
        $this->stream['data']['nonce'] = $server_nonce;
        $this->stream['data']['act'] = $act;
		$models = array();
		foreach ($this->conf_model() as $class=>$file) {
			if (file_exists(dirname($file).'/access.ini')) {
				$map = $this->action_def($class);
				if (@$map['MODEL::']['protection']) $models[$class] = $this->action_def($class);
			}
		}
		$this->stream['data']['actions'] = $models;
		$this->stream['data']['group_actions'] = $group_actions;
        break;
    case 'update':
        $this->ajax();
        if ($error_code=$user->nonce_verify($user_nonce, $server_nonce, $timestamp)) {
            $this->stream['data']['message'] = $this->language_tag($error_code);
            return;
        }
        $param = array('name'=>1,'comments'=>null);
		foreach ($param as $key=>$flag) {
            $param[$key] = $this->request($key);
            if ($flag&&!$param[$key]) return $this->stream['data']['message']=$this->language_tag('DATA_INSUFFICIENT');
		}
		if ($actions=$this->request('actions')) {
			$param['action'] = serialize($actions);
		}
        $ok = $id?$user->group('modify', $id, $param):$user->group('create', 0, $param);

        if ($ok) {
            $this->stream['data']['success'] = 'close&reload';
            $this->stream['data']['target'] = '#main-column';
        } else {
            $this->stream['data']['message']=$this->language_tag('DATA_SUBMIT_FAILURE');
        }
        break;
}
