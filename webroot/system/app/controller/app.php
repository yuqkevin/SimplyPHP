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
		if ($this->request('_ENTRY')=='/'||!$this->request('_ENTRY')) {
			// set up application model for root entry
			# $this->map['model'] = 'your_main_model';
			$this->output($this->load_view('welcome'));
		}
	}
}
$app = new AppController;
$app->boot();
