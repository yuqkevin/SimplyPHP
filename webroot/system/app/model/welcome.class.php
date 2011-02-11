<?php
/** My first application module  **/
class Welcome extends Model
{
	protected $dsn_name = 'default';
    protected $tables = array(
        'user'  => array('name'=>'app_user','pkey'=>'user_id', 'seq'=>null,'schema'=>null),
    );
	function initial()
	{
        if (!$this->session_auth()) {
            $this->output($this->load_view('login'));
        }
        if (!$this->action_auth()) {
            $this->output($this->load_view('invalid_access'));
        }
	}
    function session_auth() {return true;}
	function action_auth(){return true;}
}
