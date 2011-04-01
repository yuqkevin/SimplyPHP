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
	function swapping(){}
	function __construct($conf=null)
	{
		$this->conf = $conf?$conf:$this->configure();
		// loading libraries
		foreach ($this->w3s_zones as $zone=>$fold) {
			if (is_array(@$this->conf[self::TAG_LIBRARY][$zone])) {
        		if (in_array(get_class($this),$this->conf[self::TAG_LIBRARY][$zone])) continue; // no recursion hook
				foreach ($this->conf[self::TAG_LIBRARY][$zone] as $class) {
					$this->load_library($class, $zone);
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
		if (!$this->handler($stream)) $this->swapping();
		return $this->stream;
	}
	function handler($stream)
	{
		$this->stream = $stream;
		$folder = $this->stream['folder'];
		$class = strtolower($this->stream['model']);
		$handler = "$folder/handler/{$this->stream['method']}.inc.php";
		$temp = "$folder/handler/{$this->stream['method']}.tpl.php";
		$prefix = 'handler_';
		if (file_exists($handler)) {
			include($handler);
		} elseif (file_exists($temp)) {
		} elseif (method_exists($this, $prefix.$this->stream['method'])) {
			call_user_func(array($this, $prefix.$this->stream['method']), $this->stream['param']);
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
	function load_library($class, $zone='application', $name=null)
	{
        if (!$name) $name = strtolower($class);
        $libraries = $this->global_store(self::TAG_LIBRARY);
		if ($class==get_class($this)) return null; // no recursion hook
		if ($zone=='application'&&(!isset($this->lib)||!is_object($this->lib))) {
        	$this->lib = (object) self::TAG_LIBRARY;
        	if (!isset($libraries['lib'])) $libraries['lib'] = array();
		}
		$fold = $this->w3s_zones[$zone];
        if ($zone==='core'&&isset($libraries[$class])) {
            $this->$name = $libraries[$class];
        } elseif ($zone!=='core'&&is_object(@$libraries['lib'][$class])) {
            $this->lib->$name = $libraries['lib'][$class];
        } else {
            $file = $fold."/".self::TAG_LIBRARY."/".strtolower($class).".class.php";
            if (file_exists($file)) {
                include_once($file);
                if ($zone==='core') {
                    $this->$name = new $class;
                    $libraries[$class] = $this->$name;
                } else {
                    $this->lib->$name = new $class;
                    $libraries['lib'][$class] = $this->lib->$name;
                }
                $this->global_store(self::TAG_LIBRARY, $libraries);
            }
        }
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
class Swap extends Model
{
	function boot(){}
	function swapping()
	{
		//$class = strtolower(get_class($this));
		//$this->stream['folder'] .= APP_DIR."/model/$class";
		$this->stream['model'] = $this->stream['method'];
		$this->stream['method'] = count($this->stream['param'])?array_shift($this->stream['param']):'index';
		$this->stream['folder'] .= '/'.$this->stream['model'];
		if (!$page=$this->stream['model']!==$this::COMPONENT_PREFIX) {
         	if (!is_array($this->stream['param'])||count($this->stream['param'])<1) return null; // missing component name
			$this->stream['model'] = $this->stream['method'];
			$this->stream['method'] = array_shift($this->stream['param']);
		}
		$this->boot($page);
	}
}

class Library extends Model
{
    function __construct()
    {
        $this->conf = $this->configure();
        // loading core libraries
        $libraries = $this->global_store(Model::TAG_LIBRARY);
		$zone = 'core';
        if (is_array(@$this->conf[Model::TAG_LIBRARY][$zone])) {
            if (in_array(get_class($this),$this->conf[Model::TAG_LIBRARY][$zone])) continue; // no recursion hook
            foreach ($this->conf[Model::TAG_LIBRARY][$zone] as $class) {
                $lib = strtolower($class);
                if ($zone==='core'&&isset($libraries[$class])) {
                    $this->$lib = $libraries[$class];
                } else {
                    $file = $this->w3s_zones[$zone]."/".Model::TAG_LIBRARY."/$lib.class.php";
                    if (file_exists($file)) {
                        include_once($file);
                        $this->$lib = new $class;
                        $libraries[$class] = $this->$lib;
                        $this->global_store(Model::TAG_LIBRARY, $libraries);
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
    }
}
