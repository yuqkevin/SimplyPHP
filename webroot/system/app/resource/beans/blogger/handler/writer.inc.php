<?php
$libUser = $this->get_lib('LibAclUser');
$libBlogger = $this->get_lib('LibBlogger');
$libUi = $this->get_lib('LibUiHtml');
if (!$operator=$this->operator_verify()) $this->error("Invalid Access: {$this->stream['comp_url']}");
$id = intval($this->request('id'));
if ($token=$this->request('_token')) {
    $token=$this->s_decrypt($token, $id);
    $this->stream['data']['_token'] = $this->request('_token');
    if ($stage=$this->request('stage')) $this->ajax();
}
$param = array('title'=>1,'body'=>0,'tags'=>0);
switch($token) {
    case 'create':
    case 'modify':
        $this->stream['data']['post'] = $id?$libBlogger->post('read', $id):array();
        $this->stream['view'] .= '.edit';
        break;
    case 'delete':
        $this->ajax();
        if ($id) {
            $post = $libBlogger->post('read', $id);
            if ($post['status']!=LibBlogger::POST_STATUS_DRAFT) {
                if ($libBlogger->post('delete', $id)) {
                    $this->stream['data']['success'] = 'reload';
                } else {
                    $this->stream['data']['message'] = "Sorry, failed to delete post.";
                }
            } else {
                $this->stream['data']['message'] = "Sorry, you cannot delete a released post.";
            }
        }
        break;
    case 'save':
    case 'release':
        foreach ($param as $key=>$flag) {
            if (!($param[$key]=$this->request($key))&&$flag) {
                $this->stream['data']['message'] = "Sorry, insufficient data.";
                break 2;
            }
        }
        if (!$this->stream['data']['message']) {
            $act = $id?'modify':'create';
            if ($token=='release') $param['status']=LibBlogger::POST_STATUS_RELEASE;
            if ($libBlogger->post($act, $id, $param)) {
                $this->stream['data']['success'] = 'reload';
            } else {
                $this->stream['data']['message'] = "Sorry, failed to $token post.";
            }
        }
        break;
    default:
        $lines = $libBlogger->post('list', null,array('dna'=>$operator['dna']));
        $this->stream['data']['posts'] = $lines;
}
