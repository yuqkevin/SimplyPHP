<?php
// -------------------------------------------------------------------------------+
// | Name: Html - Library of HTML functions                                       |
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

class Html
{
	public function hasharray_options($lines, $field_key, $field_val=null, $in=null)
	{
		$result = null;
		foreach ($lines as $line) {
			$result .= sprintf("<option value=\"%s\" %s>%s</option>\n",
				htmlspecialchars($line[$field_key]), $line[$field_key]==$in?'selected':null, htmlspecialchars($line[isset($field_val)?$field_val:$field_key]));
		}
		return $result;
	}
    public function hash_options($status_list, $status = null)
    {
        $result = null;
        foreach ($status_list as $val => $name) {
           $result .= sprintf("<option value=\"%s\" %s>%s</option>\n",
                      $val, $val==$status ? 'selected': null, htmlspecialchars($name));
        }
        return $result; 
    }
	public function array_options($array, $in=null)
	{
        $opt = null;
        foreach ($array as $item) {
           $opt .= sprintf("<option value=\"%s\" %s>%s</option>", htmlspecialchars($item),$item==$in ? 'selected':null, htmlspecialchars($item));
        }
        return $opt;
    }
	public function trigger($url, $param=array('href'=>null,'class'=>null,'name'=>'popup'))
	{
		$timestamp = time();
		return <<<EOT
<a id="trigger-$timestamp" href="$url" class="trigger hidden {$param['class']}" name="{$param['name']}"></a>
<script type="text/javascript">
	$(document).ready(function(){
		$('#trigger-$timestamp').trigger('click');
	});
</script>
EOT;
	}
}
