<?php
/** Bean: Sample Bean for guest book 
	Model: SampleGbook
**/
class SampleGbook extends Sample
{
	protected $dependencies = array('LibSampleGbook'=>'gbook');
	protected function post($act, $id, $param=null)
	{
		return $this->lib->gbook->post($act, $id, $param);
	}
	protected function post_search($filter, $suffix=null)
	{
		return $this->lib->gbook->search($filter, $suffix);
	}
}
