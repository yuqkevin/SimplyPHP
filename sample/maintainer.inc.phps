<?php
$target = array('url'=>'/SampleGbook/');
$this->stream['data']['message'] = null;
if ($id=$this->request('del','post')) {
	if ($this->post('delete', $id)) {
		$target['message'] = "OK, the message has been deleted.";
	} else {
        $this->stream['data']['message'] = "Sorry, failed to delete the message.";
        $this->stream['data']['post'] = $this->post('read', $id);
        return;
	}
} elseif ($id=$this->request('edit','post')) {
	$this->stream['data']['post'] = $this->post('read', $id);
	return;
} elseif ($id=$this->request('update','post')) {
	if ($this->post('update', $id, array('content'=>$this->request('content','post')))) {
		$target['message'] = "OK, the message has been updated.";
	} else {
		$this->stream['data']['message'] = "Sorry, failed to update message.";
		$this->stream['data']['post'] = $this->post('read', $id);
		return;
	}
}
$this->redirect($target);
