<?php
$conf = $this->stream['conf'];
$style = null;
$class = null;
if (isset($conf['style'])) {
	$style.=$conf['style'].";";
	unset($conf['style']);
}
if (isset($conf['class'])) {
	$class = $conf['class'];
	unset($conf['class']);
}
foreach ($conf as $key=>$val) $style.="$key:$val;";

$attr = null;
if ($style) $attr = "style=\"$style\"";
if ($class) $attr .= " class=\"$class\"";
$this->stream['data']['attr'] = $attr;
