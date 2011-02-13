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

require_once(CORE_DIR."/controller.class.php");

Class AppController extends Controller
{
	function initial()
	{
		$this->map['model'] = 'welcome';
        if ($this->request('act')=='logout') $this->app->logout();
	}
}
$app = new AppController;
$app->boot();
