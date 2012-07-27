<?php
/**
 * @description User Account Management tool: listing all accounts or delete specific account under dna
***/
$user = $this->get_lib('LibAclUser');
$session_id = $this->session('id');
$token_key = $this->key_gen();
$user_info = $this->user_info();
if (!$token=$this->request('token')) {
    // listing
	$lines = $user->account_search(array('dna'=>$user_info['dna']));
	$class_name = get_class($this);
	$edit_url =  $this->component_url($class_name, 'edit');
	for ($i=0; $i<count($lines); $i++) {
		$id = $lines[$i]['id'];
		$lines[$i]['del_handler'] = $this->stream['comp_url']."?id=$id&token=".$this->s_encrypt('delete', $token_key.$id);
		$lines[$i]['edit_handler'] = $edit_url."?id=$id&token=".$this->s_encrypt('edit', $token_key.$id);
	}
	$this->stream['data']['accounts'] = $lines;
	$this->stream['data']['operator'] = $user_info;
	$this->stream['data']['create_url'] = $edit_url."?id=0&token=".$this->s_encrypt('create', $token_key.'0');
    return;
}
$this->ajax();
$id = intval($this->request('id'));
$token_key .= $id;
if ($id&&$this->s_decrypt($token, $token_key)=='delete') {
	if (($account=$user->account('read', $id))&&$id!=$account['dna']) {
		// account exits and is not primary account
		if ($user->account('delete', $id)) {
			$this->stream['data']['success'] = 'reload';
			$this->stream['data']['message'] = "OK, account has been deleted permanantly.";
			$this->stream['data']['target'] = '#main-column';
		} else {
			$this->stream['data']['message'] = "Sorry, the account can not be delete at this moment.\nPlease try again later.";
		}
	} else {
		$this->stream['data']['message'] = "Sorry, you have no suffecient privileges to delete this account.\nPlease contact your administrator.";
	}
}
