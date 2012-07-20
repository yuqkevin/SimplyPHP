<?php
/**
 * @description User Account Management tool: listing all accounts or delete specific account under dna
***/
$user = $this->get_lib('LibAclUser');
$session_id = $this->session('id');
$token_key = $this->token_key();
$user_info = $this->user_info();
if (!$token=$this->request('token')) {
    // listing
	$lines = $user->group_search(array('dna'=>$user_info['dna']));
	$class_name = get_class($this);
	$edit_url =  $this->component_url($class_name, 'groupEdit');
	for ($i=0; $i<count($lines); $i++) {
		$id = $lines[$i]['id'];
		$lines[$i]['primary'] = $lines[$i]['dna']!=$user_info['dna']||$lines[$i]['id']==LibAclUser::GROUP_SYS;
		$lines[$i]['own'] = $lines[$i]['id']==$user_info['group']['id'];
		$lines[$i]['del_handler'] = $this->stream['comp_url']."?id=$id&token=".$this->s_encrypt('delete', $token_key.$id);
		$lines[$i]['edit_handler'] = $edit_url."?id=$id&token=".$this->s_encrypt('edit', $token_key.$id);
	}
	$used_groups = $user->account_search(array('dna'=>$user_info['dna']),array('groupby'=>'group'), "user_group, count(*) user_cnt");
	$this->stream['data']['used_groups'] = $this->hasharray2array($used_groups,'group','cnt');
	$this->stream['data']['groups'] = $lines;
	$this->stream['data']['operator'] = $user_info;
	$this->stream['data']['create_url'] = $edit_url."?id=0&token=".$this->s_encrypt('create', $token_key.'0');
    return;
}
$this->ajax();
$id = intval($this->request('id'));
$token_key .= $id;
if ($id&&$this->s_decrypt($token, $token_key)=='delete') {
	if (($group=$user->group('read', $id))&&$id!=$group['dna']) {
		// account exits and is not primary account
		if ($user->account_search(array('group'=>$id))) {
			// can not delete a group which has account in it.
			$this->stream['data']['message'] = "Sorry, this group can not be deleted because some accounts are using it.";
		} elseif ($user->group('delete', $id)) {
			$this->stream['data']['success'] = 'reload';
			$this->stream['data']['message'] = "OK, group has been deleted permanantly.";
			$this->stream['data']['target'] = '#main-column';
		} else {
			$this->stream['data']['message'] = "Sorry, the group can not be delete at this moment.\nPlease try again later.";
		}
	} else {
		$this->stream['data']['message'] = "Sorry, you have no suffecient privileges to delete this group.\nPlease contact your administrator.";
	}
}
