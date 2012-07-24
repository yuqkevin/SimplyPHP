<?php
$this->stream['data']['message'] = "Hi, please leave your message here.";
if (isset($_POST['new'])) {
	$param = array(
		'guest'=>$this->request('guest','post')?$this->request('guest'):'Anonymous',
		'content'=>$this->request('content','post')
	);
	if (!$param['content']) {
		$this->stream['data']['message'] = "Sorry, what message do you want to leave here?";
		return;
	}
	if ($this->post('create', 0, $param)) {
		$target = array(
			'url'=>'/SampleGbook/',	// redirect to listing page
			'delay'=>2,	// delay in seconds
			'message'=>'Thank you! your message has been submitted, and it will be redirected to listing page shortly.'
		);
		$this->redirect($target);
	} else {
		$this->stream['data']['message'] = "Sorry, failed to sumit your message.";
	}
}
