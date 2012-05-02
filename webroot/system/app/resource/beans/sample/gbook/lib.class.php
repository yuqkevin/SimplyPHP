<?php

class LibSampleGbook extends LibSample
{
    function post($act, $id, $param=null)
    {
		if (!$id&&$act!='create') {
			$this->error("No gbook given for operating");
			return false;
		}
        switch ($act) {
            case 'read':
				return $this->tbl->gbook->read($id);
                break;
            case 'create':
				return $this->tbl->gbook->create($param);
                break;
            case 'update':
				return $this->tbl->gbook->update($id, $param);
                break;
            case 'delete':
				return $this->tbl->gbook->delete($id);
                break;
        }
		return false;
    }
	function search($filter, $suffix=null)
	{
		return $this->tbl->gbook->search($filter, $suffix);
	}
}
