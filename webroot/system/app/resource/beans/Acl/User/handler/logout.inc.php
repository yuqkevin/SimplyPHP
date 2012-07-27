<?php
$target = isset($this->stream['conf']['target'])?$this->stream['conf']['target']:'/';
$acl = $this->get_lib('LibAclUser');
$acl->sign_out();
$this->redirect($target);
