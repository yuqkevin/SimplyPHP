<?php
/***
 Library for User verification
 the success result for verfication will be store in acl session
***/
class LibAclUser extends LibAcl
{
	const	DNA_SYS 		= 1;	// global system users. top privileges in global scope
	const	GROUP_SYS		= 1;	// system mantain group which has full access in global scope

	const	USER_SCOPE_PRIMARY	= 'primary';	// primary account
	const	USER_SCOPE_REGULAR = 'regular';		// regular account, means it is not a primary account
	const	USER_SCOPE_SYSTEM = 'system';	// system account but not root, has system level access

	const	DOMAIN_VERIFIED = '1';
	const	DOMAIN_UNVERIFIED = '0';

	const	COMP_PERMIT_ALLOW	= 'A';
	const	COMP_PERMIT_DENY	= 'D';

	const	EMAIL_VERIFIED = '1';
	const	EMAIL_UNVERIFIED = '0';

	const	USER_SESSION_AUTH = Core::USER_SESSION_AUTH;	// using core session define so can share core::user_info();
	const	USER_SESSION_HOOK = 'LibAcl::USER_SH';

    /*** mix user_info([string $name])
     *  @description read user authentication session, overide core::user_info() with local one.
     *  @input $name    property name
     *  @return mix whole user session if no property name given or property value by given name
    ***/
    public function user_info($name=null)
    {
        if (!$info=$this->session(self::USER_SESSION_AUTH)) return false;
        return $name?@$info[$name]:$info;
    }

	/*** bool sign_in(string $name, string $pass)
	 *	@description	Verify user's identification
	 *	@input	$name	User Login ID (email address in lower cases usually)
	 *			$pass	User's password in plain text
	 *	@return	bool	true if pass and start user session and logging user info into session, or false if failure
	 ****/
	public function sign_in($name, $pass)
	{
		if (!$name||!$pass) {
			$this->status['error_code'] = 'SIGN_IN_NODATA';
			$this->status['error'] = "Sorry, you must provide valid login name and password.";
			return false;
		}
		$filter = array('login'=>$name);
		if ($rows=$this->tbl->account->search($filter)) {
			if ($rows[0]['pass']==md5($pass)) {
				$user = $rows[0];
				$user['group'] = $this->tbl->group->read($user['group']);
				$user['group']['action'] = $this->unserialize($user['group']['action']);
				if ($user['group']['id']!=self::GROUP_SYS) {
					//check access domain if user has a domain setting
					if ($user['domain']) {
						$domain = $this->tbl->domain->read($user['group']['domain']);
						if ($this->env('DOMAIN')!==$domain['name']) {
							$this->status['error_code'] = 'INVALID_ACCESS_DOMAIN';
							$this->status['error'] = "User has no access on this domain.";
							return false;
						}
					}
					// set user account scope
					$user['scope'] = $user['id']==$user['dna']?self::USER_SCOPE_PRIMARY:self::USER_SCOPE_REGULAR;
				} else {
					$user['group']['action'] = array('*::*::*');	// system supper group, full access
					$user['scope'] = self::USER_SCOPE_SYSTEM;
				}
				return $this->user_session('new', $user);
			}
			$this->status['error_code'] = 'LOGIN_PASS_NOT_MATCH';
			$this->status['error'] = "Login ID and password does not match.";
		} else {
			$this->status['error'] = "Login ID does not exist";
			$this->status['error_code'] = 'LOGIN_NOT_EXISTS';
		}
		return false;
	}
	/*** bool sign_out()
	 *	@description	sign user out and end the member session
	***/
	public function sign_out()
	{
		$this->session_hook('clear');
		return $this->user_session('close');
	}

	/*** mix session_verify([int $lifetime[, string $login[, string $pass[, bool $cross_domain]]]])
	 *	@description	verify user session and returns user info if success, or returns false
	***/
	public function session_verify($lifetime=0, $login=null, $pass=null, $cross_domain=false)
	{
        if (!$operator=$this->user_session('verify', $lifetime)) {
			if ($login&&$pass) {
				if ($operator=$this->sign_in($login, $pass, $cross_domain)) return $operator;
			} elseif (!$this->tbl->account->read(self::DNA_SYS)) {
				$this->status['error_code'] = 'NOT_INITIALIZED';
			}
			return false;
        }
		// verify login id if it's given
		if ($login) {
			if ($operator['login']!==$login) {
				$this->status['error_code'] = 'LOGIN_SESSION_NOT_MATCH';
				$this->status['error'] = "Login ID does not match the current session.";
				return false;
			}
		}
		return $operator; // pass
	}

	/*** array account_search([array $filter[, string $suffix[,string $fields="*"]])
	 *	@description listing user with specific filter and suffix limitation, for user other than system group, the list is within dan only.
	***/
	public function account_search($filter=null, $suffix=null, $fields="*")
	{
		$operator = $this->user_info();
		$filter['id::>'] = 1; // Hide system root
		if (!isset($filter['dna'])&&$operator['dna']!=self::DNA_SYS) $filter['dna'] = $operator['dna'];
		$lines= $this->tbl->account->search($filter, $suffix, $fields);
		return $lines;
	}

	/*** mix account(string $act[, int $id[, array $param]])
	 *	@description	Applying action on specific user account or create a new account
	 *	@input	string $act		action name
	 *			int    $id		user inner number
	 *			array  $param	user's new properties
	 *	@return	mix
	***/
	public function account($act, $id=null, $param=null)
	{
		if ($id) {
			$data = $this->tbl->account->read($id);
			if ($act=='read') return $data;
			if (!$this->dna_verify($data['dna'])) return false;
		}
		$operator = $this->user_info();
		switch ($act) {
			case 'create':
				if (!isset($param['dna'])||$operator['group']['id']!=self::GROUP_SYS) $param['dna']=$operator['dna'];
				return $this->tbl->account->create($param);
				break;
			case 'modify':
				// Do not allow change dna for user's safty
				if (isset($param['dna'])&&$operator['group']['id']!=self::GROUP_SYS) unset($param['dna']);
				return $this->tbl->account->update($id, $param);
				break;
			case 'primary':
				// only sys user user can set account to be a primary account
				if ($operator['dna']==self::DNA_SYS) {
					return $this->tbl->account->update($id, array('dna'=>$data['id']));
				}
				$this->status['error_code'] = 'NON_SYS_ACCOUNT';
				$this->status['error'] = "Only system account can change an account to be a primary account.";
				break;
			case 'delete':
				return $this->tbl->account->delete($id);
				break;
			case 'check': // read by login
				$r=$this->tbl->account->search(array('login'=>$id));
				return @$r[0];
				break;
		}
		return false;
	}

	/*** mix group(string $act[, int $id[, array $param]])
	 *	@description	applying action on specific group
	***/
	public function group($act, $id=null, $param=null)
	{
		if ($id) {
			$data = $this->tbl->group->read($id);
			if ($act=='read') return $data;
			if (!$this->dna_verify($data['dna'])) return false;
		}
        $operator = $this->user_info();
        switch ($act) {
            case 'create':
				if (!isset($param['dna'])||$operator['group']['id']!=self::GROUP_SYS) $param['dna']=$operator['dna'];
                return $this->tbl->group->create($param);
                break;
            case 'modify':
				if (isset($param['dna'])&&$operator['group']['id']!=self::GROUP_SYS) unset($param['dna']); // Only system account can change account's dna
                return $this->tbl->group->update($id, $param);
                break;
            case 'delete':
                return $this->tbl->group->delete($id);
                break;
        }
        return false;
	}

	/*** array group_search([array $filter[, string $suffix]])
	 *	@description listing group with specific filter and limit
	***/
	public function group_search($filter=null, $suffix=null)
	{
		$operator = $this->user_info();
		if (!isset($filter['dna'])&&$operator['dna']!=self::DNA_SYS) $filter['dna'] = $operator['dna'];
		return $this->tbl->group->search($filter, $suffix);
	}

	/*** bool disable(int $userid[, string $comments])
	 *	@description	Disable specific user with comments option
	 *	@return	true if success or false if failure
	***/
	public function disable($userid, $comments=null)
	{
		$session = $this->user_session('get');
		$comments .= sprintf("\n done by %s at %s", $session['login'], date('Y-m-d H:i'));
		return (bool) $this->tbl->account->update($userid, array('group'=>0, 'comments'=>$comments));
	}

	/*** mix domain(string $act[, int $id[, array $param]])
	 *	@description	Applying action on specific domain
	***/
	public function domain($act, $id=null, $param=null)
	{
        if (!($entry=$this->dna_verify('domain', $id))&&!in_array($act,array('read','locate'))) return false;
        $operator = $this->user_info();
        switch ($act) {
            case 'read':
                return $this->tbl->domain->read($id);
                break;
			case 'locate':
				$r = $this->tbl->domain->search($param);
				return @$r[0];
				break;
            case 'create':
                if ($operator['dna']!=self::DNA_SYS) {
                    $param['dna'] = $operator['dna'];
                }
                return $this->tbl->domain->create($param);
                break;
            case 'modify':
                if (isset($param['dna'])&&$operator['dna']!=self::DNA_SYS) unset($param['dna']); // Only system account can change account's dna
                return $this->tbl->domain->update($id, $param);
                break;
            case 'delete':
                return $this->tbl->domain->delete($id);
                break;
        }
        return false;
	}
	/*** bool dna_verify(int $obj_dna[, int $operator_dna])
	 *	@description  verify dna between object and operator, current user is default operator if operator ommited, system user is verified always.
	 *	@input	$obj_dna	dna of object which has dna property, such as group, user, ..., etc
	 *			$operator_dna	dna of operator, current user is default if operator ommited here
	 *	@return	true if get verified, false if failure
	***/
	public function dna_verify($obj_dna, $operator_dna=null)
	{
		if (!isset($operator_dna)) {
			$operator = $this->user_info();
			if ($operator['group']['id']==self::GROUP_SYS) return true;
			$operator_dna = $operator['dna'];
		}
		return $obj_dna===$operator_dna;
	}

	/*** mix session_hook(string $name[,mix $val])
	 *	@description	Setting/Getting user variables in session other than user_session
	 *	@input	$name	variable name, 'clear','reset' are reserved for clean session
	 *			$val	value of variable
	 *	@return value of variable or true if success, false for failure
	***/
	public function session_hook($name, $val=null)
	{
		if (in_array($name, array('clear','reset'))&&!$val) return $this->session(self::USER_SESSION_HOOK, 'reset');
		$session = $this->session(self::USER_SESSION_HOOK);
		if (!isset($val)) return @$session[$name];
		$session[$name] = $val;
		$this->session(self::USER_SESSION_HOOK, $session);
		return $val?$val:true;
	}

	/*** mix user_session(string $act[, mix $val])
	 *	@description	Apply action on user authentication session
	 *	@input	$act	Action name
	 *			$val	array for session update, int for session lifetime verifying
	 *	@return	array for session value if success, false for failure
	***/ 
	public function user_session($act, $val=null)
	{
		switch ($act) {
			case 'new':
				$data = array(
					'ip'=>$this->env('REMOTE_ADDR'),
					'url'=>$this->env('URI'),
					'user'=>$val['id'],
					'action'=>'0'
				);
				$this->user_session('close');
				$this->tbl->session->create($data);
				$val['timestamp'] = time();
				$val['data'] = array('url'=>array($this->env('URI')));
				$val['step'] = 0;
				$this->session(self::USER_SESSION_AUTH, $val);
				return $val;
				break;
			case 'set': // set with given value
				$this->session(self::USER_SESSION_AUTH, $val);
				return $val;
				break;
			case 'get':
				return $this->session(self::USER_SESSION_AUTH);
				break;
			case 'close': // clear, set value to null
				if (!$session=$this->session(self::USER_SESSION_AUTH)) return true;
				$urls = $session['data']['url'];
				array_pop($urls);
				unset($session['data']['url']);
				$data = array(
					'ip'=>$_SERVER['REMOTE_ADDR'],
					'url'=>end($urls),
					'user'=>$session['id'],
					'action'=>'1',
					'data'=>serialize($session['data']),
				);
				$this->tbl->session->create($data);
				$this->session_hook('clear'); // wipe out user's other session as well
				return $this->session(self::USER_SESSION_AUTH, 'reset');
				break;
			case 'refresh': // refresh with given parameters
				if (is_array($val)&&($session=$this->session(self::USER_SESSION_AUTH))) {
					foreach ($val as $k=>$v) $session[$k] = $v;
					$session['timestamp'] = time();
					$this->session(self::USER_SESSION_AUTH, $session);
					return $session;
				}
				return false;  // no session to refresh
				break;
			case 'verify': // verify session and refresh timestamp if applied.
				if ($session=$this->session(self::USER_SESSION_AUTH)) {
					if (isset($session['timestamp'])) {
						if ($val&&$val<(time()-$session['timestamp'])) {
							$this->status['error_code'] = 'SESSION_EXPIRED';
							$this->status['error'] = 'Session has been expired.';
							return false;
						}
						$session['timestamp'] = time();
					}
					$session['step'] ++;
					// record last two step urls
					$urls = $session['data']['url'];
					if (count($urls)>1) array_shift($urls);
					array_push($urls, $this->env('URI'));
					$session['data']['url'] = $urls;
					$this->session(self::USER_SESSION_AUTH, $session);
					return $session;
				} else {
					$this->status['error_code'] = 'SESSION_NOT_EXISTS';
					$this->status['error'] = "Session does not exists.";
				}
				return false;
				break;
		}
	}
}
