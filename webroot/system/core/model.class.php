<?php
// -------------------------------------------------------------------------------+
// | Name: Model - Base class and common mthods shared by application modules     |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// -------------------------------------------------------------------------------+
// | Repository: https://github.com/yuqkevin/SimplyPHP/                           |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu                                                         |
// -------------------------------------------------------------------------------+
// | Checkout: 2011.01.19                                                         |
// -------------------------------------------------------------------------------+
//

class Model extends Core
{
	public $dsn_name = null;
	public $tables = null;
	public $stream = array();
	const TAG_LIBRARY = 'library';
	function initial(){}	// reserved for customization
	function load_module(){} // loading app inner module
	function __construct($conf=null)
	{
		$this->conf = $conf?$conf:$this->configure();
		// loading libraries
		$libraries = $this->global_store(self::TAG_LIBRARY);
		$this->lib = (object) self::TAG_LIBRARY;
		if (!isset($libraries['lib'])) $libraries['lib'] = array();
		foreach ($this->w3s_zones as $zone=>$fold) {
			if (is_array(@$this->conf[self::TAG_LIBRARY][$zone])) {
				if (in_array(get_class($this),$this->conf[self::TAG_LIBRARY][$zone])) continue; // no hook support for library
				foreach ($this->conf[self::TAG_LIBRARY][$zone] as $class) {
					$lib = strtolower($class);
					if ($zone==='core'&&isset($libraries[$class])) {
						$this->$lib = $libraries[$class];
					} elseif ($zone!=='core'&&is_object(@$libraries['lib'][$class])) {
						$this->lib->$lib = $libraries['lib'][$class];
					} else {
						$file = $fold."/".self::TAG_LIBRARY."/$lib.class.php";
						if (file_exists($file)) {
							include_once($file);
							if ($zone==='core') {
								$this->$lib = new $class;
								$libraries[$class] = $this->$lib;
							} else {
								$this->lib->$lib = new $class;
								$libraries['lib'][$class] = $this->lib->$lib;
							}
							$this->global_store(self::TAG_LIBRARY, $libraries);
						}
					}
				}
			}
		}
		if ($this->dsn_name) {
			$this->db = $this->load_db($this->conf['dsn'][$this->dsn_name]);
			if ($this->db&&is_array($this->tables)) {
				foreach ($this->tables as $name=>$def) $this->$name = $this->db->load_table($def);
			}
		}
		$this->initial();
	}
	function run($stream)
	{
		$stream = array_merge($stream, $this->stream); // this->stream can be overrided in initial()
		if (!$this->handler($stream))  $this->load_module();
		return $this->stream;
	}
	function handler($stream)
	{
		$this->stream = $stream;
		$folder = $this->stream['folder'];
		$class = strtolower($this->stream['model']);
		$handler = "$folder/$class/{$this->stream['method']}.inc.php";
		if (!file_exists($handler)) $handler = "$folder/{$this->stream['method']}.inc.php";
		if (file_exists($handler)) {
			include($handler);
		} elseif (method_exists($this, $this->stream['method'])) {
			call_user_func(array($this, $this->stream['method']), $this->stream['param']);
		} else {
			return null;
		}
        return $this->stream;
	}
	function load_db($dsn,$name=null)
	{
		if (!$name) $name = 'db';
		$dbdriver = ucfirst(strtolower($dsn['dbdriver']));
		if ($dbdriver&&(!isset($this->$name)||!is_object($this->$name))) {
			$this->$name = new $dbdriver($dsn);
		}
		return $dbdriver?$this->$name:null;
	}

	function logout()
	{
		$this->clear();
		$this->output($this->load_view('logout'));
	}

    function clear()
    {
		$this->mysession('clear');
		unset($_COOKIE);
        return true;
    }
}
