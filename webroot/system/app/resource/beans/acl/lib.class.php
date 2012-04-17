<?php
class LibAcl extends Library
{
	const	SESSION_COOKIE = 'acl';	// user authenticate session
	const	SESSION_HOOK = 'LibAcl::session_user'; // store session which has same lifetime as user authenticated session

	public function session_hook($name, $val=null)
	{
		if ($name=='clear'&&!$val) return $this->session(self::SESSION_HOOK, '');
		$session = $this->session(self::SESSION_HOOK);
		if (!isset($val)) return @$session[$name];
		$session[$name] = $val;
		$this->session(self::SESSION_HOOK, $session);
		return $val?$val:true;
	}
	public function session_cookie($act, $val=null)
	{
		switch ($act) {
			case 'new':
				$data = array(
					'ip'=>$this->env('REMOTE_ADDR'),
					'url'=>$this->env('PATH'),
					'user'=>$val['id'],
					'action'=>'0'
				);
				$this->session_cookie('close');
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
