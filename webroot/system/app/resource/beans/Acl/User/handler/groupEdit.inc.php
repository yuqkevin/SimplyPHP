<?php
/**
 * @description Group Create/Update
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
			$group_actions = array();
        } else {
            $this->stream['data']['token'] = $this->s_encrypt('update', $token_key);
            $this->stream['data']['group'] = $user->group('read', $id);
			$group_actions = $this->unserialize($this->stream['data']['group']['action']);
        }
        $this->stream['data'] += $user->nonce('new');
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
        if (!$user->nonce('verify', $nonce, $timestamp)) {
            $this->stream['data']['message'] = $user->get_error('message');
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
