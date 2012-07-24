<!DOCTYPE html>
<html lang="en-us">
<head>
<title>Sample: Guest Book</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="keywords" content="" />
<meta name="description" content="" />
<style style="text/css">
li:nth-child(odd) {background:#eee;}
li span.action {visibility:hidden;float:right;}
li:hover span.action {visibility:visible;}
li:hover {background:#ddd;}
</style>
</head>
<body>
<h1 id="page-header">Sample: Guest Book <a href="/SampleGbook/writer" style="font-weight:normal;font-size:13px;">New Message</a></h1>
<hr />
<div id="page-body">
<?php if ($lines):?>
<form method="post" action="/SampleGbook/maintainer">
<ul>
	<?php foreach ($lines as $line):?>
	<li><?php if ($line['ip']==$_SERVER['REMOTE_ADDR']):?>
			<span class="action">
				<button type="submit" name="edit" value="<?php print $line['id'];?>">Edit</button>
				<button type="submit" name="del" value="<?php print $line['id'];?>">Delete</button>
			</span>
		<?php endif;?>
		<strong><?php print htmlspecialchars($line['guest']);?></strong>@<em><?php print $line['timestamp'];?></em>
		<div><?php print htmlspecialchars($line['content']);?></div>
	</li>
	<?php endforeach;?>
</ul>
</form>
<?php else:?>
No message found
<?php endif;?>
</div>
<hr />
<div id="page-footer">Sample for SimplyPHP</div>
</body>
</html>
