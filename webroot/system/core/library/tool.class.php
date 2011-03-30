<?php
// -------------------------------------------------------------------------------+
// | Name: Tool - Library of miscellaneous functions                              |
// +------------------------------------------------------------------------------+
// | Package: Simply PHP Framework                                                |
// -------------------------------------------------------------------------------+
// | Repository: https://github.com/yuqkevin/SimplyPHP/                           |
// +------------------------------------------------------------------------------+
// | Author:  Kevin Q. Yu                                                         |
// -------------------------------------------------------------------------------+
// | Checkout: 2011.03.30                                                         |
// -------------------------------------------------------------------------------+
//

class Tool
{
    function hasharray2array($lines, $fkey, $fval=null)
    {
        $array = array();
        foreach ($lines as $line) {
            if (isset($fval)) {
                $array[$line[$fkey]] = $line[$fval];
            } else {
                $array[] = $line[$fkey];
            }
        }
        return $array;
    }

	function hash2str($hash)
	{
		$str = null;
		if (!is_array($hash)) return null;
		foreach ($hash as $key=>$val) $str .= "$key=\"$val\" ";
		return trim($str);
	}
	function str2hash($str)
	{
		$hash = array();
		if (preg_match_all("/([^=\"]+)=\"([^\"]+)\"/", $str, $match)) {
			for ($i=0; $i<count($match[0]); $i++) $hash[trim($match[1][$i])] = trim($match[2][$i]);
		}
		return $hash;
	}
}

