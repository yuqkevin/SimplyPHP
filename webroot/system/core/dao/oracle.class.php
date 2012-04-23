<?php
// -------------------------------------------------------------------------------+
// | Name: Oracle - Oracle implement of Dao                                       |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// -------------------------------------------------------------------------------+
// | Repository: https://github.com/yuqkevin/SimplyPHP/                           |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu                                                         |
// -------------------------------------------------------------------------------+
// | Checkout: 2011.01.19                                                         |
// +------------------------------------------------------------------------------+

class Oracle extends Dao
{

	public function alt_session($name, $val)
	{
		return (bool) $this->update_row("alter session set $name='$val'");
	}
}
