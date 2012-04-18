<?php
class LibBlogger extends Library
{
    protected $dependencies = array('LibAclUser'=>'user');

    const    POST_STATUS_DRAFT = '0';
    const    POST_STATUS_RELEASE = '1';
    public function db_initial()
    {
    }

    public function post($act, $id, $param=null)
    {
        $entry = $this->dna_verify('post', $id);
        if ($id&&$act!='read'&&!$entry) return false;
        $operator = $this->get_operator();
        switch ($act) {
            case 'read':
                return is_array($entry)?$entry:($id?$this->tbl->post->read($id):false);
                break;
            case 'delete':
                $this->tbl->comment->delete(array('post'=>$id));
                $this->tbl->tag->delete(array('post'=>$id));
                return $this->tbl->post->delete($id);
                break;
            case 'modify':
                if (isset($param['dna'])) unset($param['dna']); // can not change dna
                $tags_new = preg_split("/[\s,]+/", $param['tags']);
                $param['tags'] = join(', ', $tags_new);
                $this->tag('post', $id, $tags_new);
                $param['updator'] = $operator['id'];
                return $this->tbl->post->update($id, $param);
                break;
            case 'create':
                $param['author'] = $operator['id'];
                $param['dna'] = $operator['dna'];
                $param['create'] = date('Y-m-d H:i:s');
                $tags_new = preg_split("/[\s,]+/", $param['tags']);
                $param['tags'] = join(', ', $tags_new);
                if ($id=$this->tbl->post->create($param)) $this->tag('post', $id, $tags_new);
                return $id;
                break;
            case 'list': //post listing, filter: dna,limit
                $suffix = "order by post_id desc";
                if (isset($param['limit'])&&is_numeric($param['limit'])) {
                    $suffix .= " limit {$param['limit']}";
                    unset($param['limit']);
                }
                return $this->tbl->post->search($param, $suffix);
                break;
        }
        return false;
    }
    public function tag($act, $id, $param=null)
    {
        $entry = $this->dna_verify('tag', $id);
        if ($entry&&!in_array($act, array('read','list','post'))) return false;
        $operator = $this->get_operator();
        switch ($act) {
            case 'read':
                return is_array($entry)?$entry:($id?$this->tbl->tag->read($id):false);
                break;
            case 'delete':
                return $this->tbl->tag->delete($id);
                break;
            case 'modify':
                if (isset($param['dna'])) unset($param['dna']); // can not change dna
                return $this->tbl->tag->update($id, $param);
                break;
            case 'create':
                $param['dna'] = $operator['dna'];
                return $this->tbl->tag->create($param);
                break;
            case 'list': // list all tag for specific dna
                if (!isset($param['dna'])) $param['dna']=$operator['dna']; // list all tags for specific dna
                $suffix = "group by tag_name order by tag_name";
                if (isset($param['limit'])&&is_numeric($param['limit'])) {
                    $suffix .= " limit {$param['limit']}";
                    unset($param['limit']);
                }
                $fields = "tag_name, count(*) as tag_cnt";
                return $this->tbl->tag->search($param, $suffix, $fields);
                break;
            case 'post':    // renew post's tag with given new tags in param array: id:post id, param: tag in array
                $post = $this->post('read', $id);
                $tags = preg_split("/[\s,]/", $post['tags']);
                $tags_nochg = array();
                $filter = array('post'=>$id,'dna'=>$post['dna']);
                foreach ($tags as $tag) {
                    if (!in_array($tag, $param)) {
                        $filter['name'] = $tag;
                        $this->tbl->tag->delete($filter);
                    } else {
                        $tags_nochg[] = $tag;
                    }
                }
                foreach ($param as $name) {
                    $filter['name'] = trim($name);
                    if (trim($name)&&!in_array($name, $tags_nochg)) $this->tbl->tag->create($filter);
                }
                return true;
                break;
        }

    }

    public function comment($act, $id, $param=null)
    {
        $entry = $this->dna_verify('comment', $id);
        if ($entry&&!in_array($act, array('read','list','post'))) return false;
        $operator = $this->get_operator();
        switch ($act) {
            case 'read':
                return is_array($entry)?$entry:($id?$this->tbl->comment->read($id):false);
                break;
            case 'delete':
                return $this->tbl->comment->delete($id);
                break;
            case 'modify':
                if (isset($param['dna'])) unset($param['dna']); // can not change dna
                return $this->tbl->comment->update($id, $param);
                break;
            case 'create':
                $param['dna'] = $operator['dna'];
                return $this->tbl->comment->create($param);
                break;
            case 'list':    //listing comments for give post. id:post_id , param:filter
                $param['post'] = $id;
                $suffix = "order by comment_id desc";
                if (isset($param['limit'])&&is_numeric($param['limit'])) {
                    $suffix .= " limit {$param['limit']}";
                    unset($param['limit']);
                }
                return $this->tbl->comment->search($param, $suffix);
                break;
        }
        return false;
    }
}
