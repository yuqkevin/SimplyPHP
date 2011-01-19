<?php
// -------------------------------------------------------------------------------+
// | Name: Controller                                                             |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu <kevin@cgtvgames.com>                                   |
// -------------------------------------------------------------------------------+
// | Release: 2011.01.18                                                          |
// -------------------------------------------------------------------------------+
//
if (!defined('APP_DIR')) exit('No application folder defined.');
define('CORE_DIR', dirname(APP_DIR).'/core');

include(APP_DIR."/conf/database.php");
require_once(CORE_DIR."/controller.class.php");

Class AppController extends Controller
{
	function initial()
	{
		$this->map = array('model'=>'App','method'=>'index','param'=>null);
        if ($this->request('act')=='logout') $this->app->logout();
	}
}
$conf = array('dsn'=>$dsn);
$lotto = new AppController($conf);
$lotto->boot();
