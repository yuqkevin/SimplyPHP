<?php
/**
 * @description Account Management Tool
 * New Client: For system user (dna=sysdna) only
 *   		System account for new client, using hook.
 * New Account: same dna,domain as operator
***/
$libUser = $this->lib->{$this->dependencies['LibAclUser']?$this->dependencies['LibAclUser']:'AclUser'};
$libUi = $this->lib->{$this->dependencies['LibUiHtml']?$this->dependencies['LibUiHtml']:'UiHtml'};
$libCms = $this->lib->{$this->dependencies['LibCms']?$this->dependencies['LibCms']:'Cms'};

if (!$operator=$this->operator_verify()) $this->error("Invalid Access: {$this->stream['comp_url']}");
$param = array('group'=>0,'nickname'=>0,'comments'=>0);
$id = $this->request('id');
if ($token=$this->request('_token')) $token = $this->s_decrypt($token, intval($id));
if ($id&&$token!='delete') $account = $libUser->account('read', $id);
if ($stage=$this->request('stage')) $this->ajax();
$groups = $this->hasharray2array($libUser->groups(array('domain'=>$operator['domain'])),'id','name');
$groups[0] = 'Idle';
if ($token) {
	$this->stream['data']['_token'] = $this->request('_token');
	switch ($token) {
		case 'client':
		case 'copy':
		case 'create':
            if ($stage) {
				if (!$login=$this->request('login','post')) {
					$this->stream['data']['message'] = "Sorry, Login Id is mandatory.";
					break;
				} elseif ($r=$libUser->account('check', 0, array('login'=>$login))) {
					$this->stream['data']['message'] = "The login Id $login is already existed.\nPlease try another one.";
					break;
				}
				$param['login'] = 1;
				$hook = null;
                foreach ($param as $key=>$mandatory) {
                    if (!($param[$key]=$this->request($key))&&$mandatory) {
                        $this->stream['data']['message'] = 'Insufficient data.'. $key;
                        break 2;
                    }
                }
				$param['dna'] = $operator['dna'];
				if ($token=='client') {
					if ($operator['dna']!=LibAclUser::DNA_SYS) {
						$this->stream['data']['message'] = 'Insufficient privilege to create a client account.';
						break;
					}
					if ($param['group']==LibAclUser::GROUP_SYS) {
						$hook = 'u'.date('Ymd-').$login;
					}
					$param['dna'] = '0';
				}
				$param['domain'] = $operator['domain'];
				$pass = substr(uniqid(), 0, 8);
				$param['pass'] = md5($pass);
				$param['comments'] .= ($param['comments']?"\n":'')."Initial PWD: $pass";
				$param['email'] = bin2hex(md5($pass.$param['login']));
				$param['creator'] = $operator['id'];
				if ($param['group']=='x') {
					if (!$gname=$this->request('new-group')) {
						$this->stream['data']['message'] = 'Sorry, group name is mandatory for new group.';
						break;
					}
					$gid = $libUser->group('create',0,array('creator'=>$operator['id'],'dna'=>$operator['dna'],'name'=>$gname,'domain'=>$operator['domain']));
					if (!$gid) {
						$this->stream['data']['message'] = 'Sorry, failed to create new group.';
						break;
					}
					$param['group'] = $gid;
				}
                if ($id=$libUser->account('create', 0, $param)) {
					if ($token=='client') {
						$user = array('dna'=>$id);
						if ($hook) {
							$domain_id = $libUser->domain('create',0,array('hook'=>$hook,'dna'=>$id));
							$user['domain'] = $domain_id;
							$libCms->domain('create',0, array('id'=>$domain_id,'dna'=>$id,'theme'=>'default'));
							$libCms->menu('create',0, array('parent'=>LibCms::MENU_ROOT,'domain'=>$domain_id,'dna'=>$id,'title'=>'ROOT'));
							$libCms->chapter('create',0, array('parent'=>LibCms::CHAPTER_ROOT,'domain'=>$domain_id,'dna'=>$id,'title'=>$hook));
						}
						$libUser->account('modify', $id, $user);
					}
					$this->stream['data']['success'] = 'reload';
                    $this->stream['data']['message'] = 'The user has been created.';
                } else {
                     $this->stream['data']['message'] = 'Failed to create new user.';
                }
                break;
            }
			$this->stream['view'] .= '.edit';
			if ($token==='copy') {
				$account = array('group'=>$account['group']);
			} else {
				$account = array();
			}
			$this->stream['data']['account'] = array();
			$this->stream['data']['libUi'] = $libUi;
			$this->stream['data']['group_options'] = $libUi->hash_options($groups, @$account['group']);
			break;
		case 'edit':
			if ($stage) {
				foreach ($param as $key=>$mandatory) {
					if (!($param[$key]=$this->request($key))&&$mandatory) {
						$this->stream['data']['message'] = 'Insufficient data.';
						break 2;
					}
				}
				if ($libUser->account('modify', $id, $param)) {
					$this->stream['data']['success'] = 'reload';
					$this->stream['data']['message'] = 'The user has been modified.';
				} else {
					 $this->stream['data']['message'] = 'Failed to modify user.';
				}
				break;
			}
			$this->stream['view'] .= '.edit';
			$this->stream['data']['libUi'] = $libUi;
			$this->stream['data']['account'] = $account;
			$this->stream['data']['group_options'] = $libUi->hash_options($groups, @$account['group']);
			break;
		case 'password':
			$this->ajax();
			$base = time()%1000000;
			$pass = rand($base, $base+1000000);
			$param = array('pass'=>md5($pass),'comments'=>preg_replace("/(Initial|Reset) PWD: \w+/", "Reset PWD: $pass", $account['comments']));
			if ($libUser->account('modify', $id, $param)) {
				$this->stream['data']['success'] = 'reload';
				$this->stream['data']['message'] = "OK, the account has been update with new password: $pass";
			} else {
				$this->stream['data']['message'] = "Sorry, failed to reset account's password.";
			}
			break;
		case 'delete':
			$this->ajax();
			if ($libUser->account('delete', $id)) {
				$this->stream['data']['success'] = 'reload';
			} else {
				$this->stream['data']['message'] = 'The user cannot be removed.';
			}
			break;
		case 'search':
			$keyword = $this->request('keyword');
			$this->stream['data']['libUi'] = $libUi;
			$this->stream['data']['operator'] = $operator;
			$this->stream['data']['keyword'] = $keyword;
			if ($operator['dna']!=LibAclUser::DNA_SYS||!$keyword) {
				$filter = array('creator'=>$operator['id']); // only account create by operator
			} else {
				$filter = is_numeric($keyword)?array('dna'=>$keyword):array('login::like'=>"%$keyword%");
			}
			$this->stream['data']['users'] = (array) $libUser->search($filter);
			break;
		default:
			$this->ajax();	// invalid token
	}
} else {
	$this->stream['data']['libUi'] = $libUi;
	$this->stream['data']['operator'] = $operator;
	$this->stream['data']['keyword'] = null;
	if ($operator['dna']==LibAclUser::DNA_SYS) {
		$filter = array('::or::'=>array('dna'=>$operator['dna'],'creator'=>$operator['id'],'id::func'=>'user_dna'));
	} elseif ($operator['dna']==$operator['dna']) {
		$filter = array('dna'=>$operator['dna']);
	} else {
		$filter = array('creator'=>$operator['id']);
	}
	$this->stream['data']['users'] = (array) $libUser->search($filter);
}
