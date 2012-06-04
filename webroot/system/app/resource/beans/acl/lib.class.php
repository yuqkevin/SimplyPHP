<?php
class LibAcl extends Library
{
	const	SESSION_COOKIE = 'acl';	// user authenticate session
	const	SESSION_HOOK = 'LibAcl::session_user'; // store session which has same lifetime as user authenticated session

	/*** mix session_hook(string $name[,mix $val])
	 *	@description	Setting/Getting user variables in session other than user_session
	 *	@input	$name	variable name, 'clear','reset' are reserved for clean session
	 *			$val	value of variable
	 *	@return value of variable or true if success, false for failure
	***/
	public function session_hook($name, $val=null)
	{
		if (in_array($name, array('clear','reset'))&&!$val) return $this->session(self::SESSION_HOOK, 'reset');
		$session = $this->session(self::SESSION_HOOK);
		if (!isset($val)) return @$session[$name];
		$session[$name] = $val;
		$this->session(self::SESSION_HOOK, $session);
		return $val?$val:true;
	}

	/*** mix user_session(string $act[, mix $val])
	 *	@description	Apply action on user session
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
					'url'=>$this->env('PATH'),
					'user'=>$val['id'],
					'action'=>'0'
				);
				$this->user_session('close');
				$this->tbl->session->create($data);
				$val['timestamp'] = time();
				$val['data'] = array('url'=>array($this->env('PATH')));
				$val['step'] = 0;
				$this->session(self::SESSION_COOKIE, $val);
				return $val;
				break;
			case 'set': // set with given value
				$this->session(self::SESSION_COOKIE, $val);
				return $val;
				break;
			case 'get':
				return $this->session(self::SESSION_COOKIE);
				break;
			case 'close': // clear, set value to null
				if (!$session=$this->session(self::SESSION_COOKIE)) return true;
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
				return $this->session(self::SESSION_COOKIE, 'reset');
				break;
			case 'refresh': // refresh with given parameters
				if (is_array($val)&&($session=$this->session(self::SESSION_COOKIE))) {
					foreach ($val as $k=>$v) $session[$k] = $v;
					$session['timestamp'] = time();
					$this->session(self::SESSION_COOKIE, $session);
					return $session;
				}
				return false;  // no session to refresh
				break;
			case 'verify': // verify session and refresh timestamp if applied.
				if ($session=$this->session(self::SESSION_COOKIE)) {
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
					array_push($urls, $this->env('PATH'));
					$session['data']['url'] = $urls;
					$this->session(self::SESSION_COOKIE, $session);
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
