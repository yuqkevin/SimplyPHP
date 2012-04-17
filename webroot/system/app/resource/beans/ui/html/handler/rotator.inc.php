<?php
$divs = preg_split("/,/", $this->stream['conf']['div']);
$body = null;
foreach ($divs as $div) {
	list($model, $method) = preg_split("/::/", $div);
	$param = null;
	if (strpos($method, '/')!==false) {
		$param = preg_split("/\//", $method);
		$method = array_shift($param);
	}
	$comp_url = $this->component_url($model, $method);
	$body .= $this->switch_to($comp_url, $param, false);
}
$this->stream['data']['body'] = $body;
$this->stream['data']['style'] = @$this->stream['conf']['style'];
