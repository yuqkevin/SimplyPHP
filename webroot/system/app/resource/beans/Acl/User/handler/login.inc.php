<?php
$MAX_TRY = 15;	// max try time, over the limit, then goto password retrieve
$MAX_LIFETIME = 0;	// life time for trying
$SCHEMA = 'loginFailure';	// session schema
$acl = $this->get_lib('LibAclUser');
$failures = $this->sequence(0, $SCHEMA);
//if ($failures>$MAX_TRY) $this->load_component('MoPropertyManager','passwordRetrieve',array('message'=>'Tried too many times.'), true);
$login = $this->request('login', 'post');
$pass = $this->request('pass', 'post');
$session_id = $this->session('id');
if (!(isset($login)||isset($pass))) {
	$this->stream['data'] = $acl->nonce('new');
	return;
}
$this->ajax();
$timestamp = $this->request('timestamp');
$nonce = $this->request('nonce');
if ($acl->nonce('verify', $nonce, $timestamp, $MAX_LIFETIME)) {
    // pass
    if ($acl->sign_in($login, $pass)) {
        $this->sequence('clear', $SCHEMA);  // clear failure time
        $acl->nonce('record', $nonce, $timestamp);
        $this->stream['data']['success'] = 'reload';
    } else {
        $this->stream['data']['message'] = $acl->status['error'];
        $this->sequence(1, $SCHEMA);    // increse failure time
    }
} elseif ($acl->get_error('code')==='NONCE_FAILURE_NOMATCH') {
	$this->stream['data']['message'] = $acl->get_error('message');
}
