<?php
/***
 Library for User verification
 the success result for verfication will be store in acl session
***/
class LibAclUser extends LibAcl
{
	const	DNA_SYS 		= 1;	// global system users. top privileges in global scope
	const	GROUP_SYS		= 1;	// system mantain group which has full access in global scope

	const	DOMAIN_VERIFIED = '1';
	const	DOMAIN_UNVERIFIED = '0';

	const	COMP_PERMIT_ALLOW	= 'A';
	const	COMP_PERMIT_DENY	= 'D';

	const	EMAIL_VERIFIED = '1';
	const	EMAIL_UNVERIFIED = '0';

	protected $tbl_ini = 'LibAcl:';	// using LibAcl default db.tbl.ini
	/*** Database initializing ***/
	function db_initial($passwd)
	{
        $this->status['error_code'] = 'INITIAL_FAILURE';
        $this->status['error'] = "Failed to initial AclUser database.";
		if (!$passwd) {
			$this->status['error'] = "You must set initial password for system account.";
			return false;
		}
		if ($this->tbl->account->read(self::DNA_SYS)) {
			$this->status['error'] = "System account already exists.";
			return false;
		}
		// domain
		$domain = array('id'=>1,'name'=>$this->env('DOMAIN'),'dna'=>self::DNA_SYS, 'status'=>self::DOMAIN_VERIFIED);
		if (!$domain_id=$this->tbl->domain->create($domain)) {
			$this->status['error'] = "Failed to create domain.";
			return false;
		}

		$group_id = self::GROUP_SYS;
		$group = array('id'=>$group_id,'dna'=>self::DNA_SYS,'domain'=>$domain_id,'name'=>'System');
		if (!$this->tbl->group->create($group)) {
			$this->status['error'] = "Failed to create group.";
			$this->tbl->domain->delete($domain_id);
			return false;
		}

		$user = array('id'=>self::DNA_SYS,'login'=>'administrator','group'=>$group_id,'domain'=>$domain_id,'dna'=>self::DNA_SYS,'pass'=>md5($passwd),'comments'=>"Init PWD:$passwd",'creator'=>0);
		if (!$this->tbl->account->create($user)) {
			$this->status['error'] = "Failed to create user.";
			$this->tbl->group->delete($group_id);
			$this->tbl->domain->delete($domain_id);
			return false;
		}
		return $this->sign_in('administrator', $passwd);
	}
	/*** bool sign_in(string $name, string $pass)
	 *	@description	Verify user's identification
	 *	@input	$name	User Login ID (email address in lower cases usually)
	 *			$pass	User's password in plain text
	 *	@return	bool	true if pass and start user session and logging user info into session, or false if failure
	 ****/
	function sign_in($name, $pass)
	{
		$filter = array('login'=>$name);
		if ($rows=$this->tbl->account->search($filter)) {
			if ($rows[0]['pass']==md5($pass)) {
				$user = $rows[0];
				$user['group'] = $this->tbl->group->read($user['group']);
				$domain = $this->tbl->domain->read($user['group']['domain']);
				//check access domain
				if ($this->env('DOMAIN')!==$domain['name']) {
					$pass = false;
					// check user domain for sys group account
					if ($user['group']['id']==self::GROUP_SYS) {
						$domain = $this->tbl->domain->read($user['domain']);
						$pass = (bool) $this->env('DOMAIN')==$domain['name'];
					}
					if (!$pass) {
						$this->status['error_code'] = 'INVALID_ACCESS_DOMAIN';
						$this->status['error'] = "User has no access on this domain.";
						return false;
					}
				}
				return $this->session_cookie('new', $user);
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
	function sign_out()
	{
		$this->session_hook('clear');
		return $this->session_cookie('close');
	}

	/*** user information setter/getter ***/
	function info($user=null)
	{
		return  $user?$this->session_cookie('set', $user):$this->session_cookie('get');
	}

	/*** mix session_verify([int $lifetime[, string $login[, string $pass[, bool $cross_domain]]]])
	 *	@description	verify user session and returns user info if success, or returns false
	***/
	function session_verify($lifetime=0, $login=null, $pass=null, $cross_domain=false)
	{
        if (!$operator=$this->session_cookie('verify', $lifetime)) {
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

	/*** array search([array $filter[, string $suffix]])
	 *	@description listing user with specific filter and suffix limitation
	***/
	function search($filter=null, $suffix=null)
	{
		$operator = $this->info();
		$filter['id::>'] = 1; // Hide system root
		if (!isset($filter['dna'])&&$operator['dna']!=self::DNA_SYS) $filter['dna'] = $operator['dna'];
		$lines= $this->tbl->account->search($filter, $suffix);
		return $lines;
	}

	/*** mix account(string $act[, int $id[, array $param]])
	 *	@description	Applying action on specific user account or create a new account
	 *	@input	string $act		action name
	 *			int    $id		user inner number
	 *			array  $param	user's new properties
	 *	@return	mix
	***/
	function account($act, $id=null, $param=null)
	{
		if (!($entry=$this->dna_verify('account', $id))&&!in_array($act,array('check','read'))) return false;
		$operator = $this->info();
		switch ($act) {
			case 'read':
				return $entry?$entry:$this->tbl->account->read($id);
				break;
			case 'create':
				$param['dna'] = $operator['dna'];
				return $this->tbl->account->create($param);
				break;
			case 'modify':
				// Do not allow change dna for user's safty
				if (isset($param['dna'])&&$operator['dna']!=self::DNA_SYS) unset($param['dna']);
				return $this->tbl->account->update($id, $param);
				break;
			case 'primary':
				// only sys user user can set account to be a primary account
				if ($operator['dna']==self::DNA_SYS) {
					return $this->tbl->account->update($id, array('dna'=>$entry['id']));
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
			default:
				return $entry;
				break;
		}
		return false;
	}

	/*** mix group(string $act[, int $id[, array $param]])
	 *	@description	applying action on specific group
	***/
	function group($act, $id=null, $param=null)
	{
        if (!($entry=$this->dna_verify('group', $id))&&$act!='read') return false;
        $operator = $this->info();
        switch ($act) {
            case 'read':
                return $entry;
                break;
            case 'create':
				if ($operator['dna']!=self::DNA_SYS) {
                	$param['dna'] = $operator['dna'];
				}
                return $this->tbl->group->create($param);
                break;
            case 'modify':
				if (isset($param['dna'])&&$operator['dna']!=self::DNA_SYS) unset($param['dna']); // Only system account can change account's dna 
                return $this->tbl->group->update($id, $param);
                break;
            case 'delete':
                return $this->tbl->group->delete($id);
                break;
        }
        return false;
	}

	/*** array groups([array $filter[, string $suffix]])
	 *	@description listing group with specific filter and limit
	***/
	function groups($filter=null, $suffix=null)
	{
		$operator = $this->info();
		if (!isset($filter['dna'])&&$operator['dna']!=self::DNA_SYS) $filter['dna'] = $operator['dna'];
		return $this->tbl->group->search($filter, $suffix);
	}

	/*** bool disable(int $userid[, string $comments])
	 *	@description	Disable specific user with comments option
	 *	@return	true if success or false if failure
	***/
	function disable($userid, $comments=null)
	{
		$session = $this->session_cookie('get');
		$comments .= sprintf("\n done by %s at %s", $session['login'], date('Y-m-d H:i'));
		return (bool) $this->tbl->account->update($userid, array('group'=>0, 'comments'=>$comments));
	}

	/*** mix domain(string $act[, int $id[, array $param]])
	 *	@description	Applying action on specific domain
	***/
	function domain($act, $id=null, $param=null)
	{
        if (!($entry=$this->dna_verify('domain', $id))&&!in_array($act,array('read','locate'))) return false;
        $operator = $this->info();
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
}
