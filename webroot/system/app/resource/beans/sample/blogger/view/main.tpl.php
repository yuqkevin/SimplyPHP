<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" >
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" >
<head>
<title>Demo</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="keywords" content="" />
<meta name="description" content="" />
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js"></script>
<script type="text/javascript" src="http://malsup.github.com/jquery.form.js"></script>
<script type="text/javascript" src="/d7/themes/default/js/script.js"></script>
<link rel="stylesheet" href="/d7/themes/default/css/style.css" type="text/css" />
<style>
#page-header {background:#888;color:#fff;}
#page-footer {background:#333;color:#888;}
#page-header>div.w3s-wrapper {background:#aaa;}
#page-footer>div.w3s-wrapper {background:#888;color:#fff;}
#page-header>div.w3s-wrapper>div,#page-footer>div.w3s-wrapper>div {padding:5px;}
body>div.w3s-body>.w3s-wrapper {background-color:#ccc;}
</style>
</head>
<body>
<div class="w3s-header" id="page-header">
	<div class="w3s-wrapper"><div>SampleBlogger</div>
	</div>
	<div class="w3s-wrapper">
		<div id="page-menu" class="w3s-menu" style="padding:0;margin:0;">
			<ul class="w3s-right">
				<li><a href="/SampleBlogger">Home</a></li>
				<li><a href="/SampleBlogger/writing">Writing</a></li>
			</ul>
		</div>
	</div>
</div>
<div class="w3s-body" style="background-color:#aaa;">
    <div class="w3s-wrapper">
    <div class="w3s-column w3s-width-p70" style="background-color:#fff;"><div>
		<?php print $content;?>
	</div></div>
    <div class="w3s-column w3s-width-p30"><div>
	</div></div>
    <p class="w3s-clear" />
    </div>
</div>
<div class="w3s-footer" id="page-footer"><div class="w3s-wrapper w3s-ralign"><div>Powered by W3S SimplyPHP</div></div></div>
</body>
</html>
