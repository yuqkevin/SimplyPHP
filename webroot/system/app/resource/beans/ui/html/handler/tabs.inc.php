<?php
$divs = preg_split("/,/", $this->stream['conf']['div']);
$titles = preg_split("/,/", $this->stream['conf']['title']);
$body = $title = null;
$id_prefix = "tab-".$this->sequence(1);
$i = 0;
foreach ($divs as $div) {
	$tab_id = $id_prefix.'-'.$i;
	list($model, $method) = preg_split("/::/", $div);
	$param = null;
	if (strpos($method, '/')!==false) {
		$param = preg_split("/\//", $method);
		$method = array_shift($param);
	}
	$comp_url = $this->component_url($model, $method);
	$class = @$this->stream['conf']['mouseover']?'class="w3s-mover"':null;
	$body .= "<div id=\"$tab_id\" $class>".$this->switch_to($comp_url, $param, false)."</div>\n";
	$title .= "<li><a href=\"#\" target=\"$tab_id\">".$titles[$i]."</a></li>";
	$i++;
}
$this->stream['data']['body'] = $body;
$this->stream['data']['title'] = $title;
