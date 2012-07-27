<?php
$MAX_TRY = 15;	// max try time, over the limit, then goto password retrieve
$MAX_LIFETIME = 15*60;	// life time for trying
$SCHEMA = 'loginFailure';	// session schema
$acl = $this->get_lib('LibAclUser');
$failures = $this->sequence(0, $SCHEMA);
//if ($failures>$MAX_TRY) $this->load_component('MoPropertyManager','passwordRetrieve',array('message'=>'Tried too many times.'), true);
$login = $this->request('login', 'post');
$pass = $this->request('pass', 'post');
$session_id = $this->session('id');
if (!(isset($login)||isset($pass))) {
	$timestamp = time();
	$this->stream['data']['nonce'] = md5($this->conf['global']['salt'].$timestamp.$session_id);
	$this->stream['data']['timestamp'] = $timestamp;
	return;
}
$this->ajax();
$timestamp = $this->request('timestamp');
$rec_nonce = $this->request('nonce');
$gen_nonce = md5($this->conf['global']['salt'].$timestamp.$session_id);

$nonce_error = $acl->nonce_verify($rec_nonce, $gen_nonce, $timestamp, $MAX_LIFETIME);
switch ($nonce_error) {
	case 'NONCE_FAILURE_EXPIRE':
		$this->stream['data']['message'] = $this->language_tag($nonce_error);
		$this->stream['data']['success'] = 'reload';
		break;
	case 'NONCE_FAILURE_NOMATCH':
		$this->stream['data']['message'] = $this->language_tag($nonce_error);
		break;
	case 'NONCE_FAILURE_DUPLICATED':
		// submit twice or more, ignore it.
		break;
	default:
		// pass
		if ($acl->sign_in($login, $pass)) {
			$this->sequence('clear', $SCHEMA);	// clear failure time
			$acl->nonce('record', $rec_nonce, $timestamp);
			$this->stream['data']['success'] = 'reload';
		} else {
			$this->stream['data']['message'] = $acl->status['error'];
			$this->sequence(1, $SCHEMA);	// increse failure time
		}
}
