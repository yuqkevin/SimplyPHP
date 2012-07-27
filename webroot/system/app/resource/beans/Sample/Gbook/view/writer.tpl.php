<!DOCTYPE html>
<html lang="en-us">
<head>
<title>Sample: Guest Book</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="keywords" content="" />
<meta name="description" content="" />
</head>
<body>
<h1 id="page-header">Sample: Guest Book <span style="font-weight:normal;font-size:13px;">New Message</span></h1>
<hr />
<div id="page-body" style="width:800px;">
	<div style="color:red;"><?php print $message;?></div>
	<form method="post">
	<label>Guest Name</label><br /><input type="text" name="guest" placeholder="Guest Name" style="width:100%;"/><br />
	<label>Message</label><br /><textarea name="content" style="width:100%; height:5em;"></textarea><br />
	<div style="text-align:right;padding-right:1em;">
		<button type="button" onClick="window.location.href='/SampleGbook/'">Cancel</button>
		<button type="submit" name="new">Submit</button>
	</div>
	</form>
</div>
<hr />
<div id="page-footer">Sample for SimplyPHP</div>
</body>
</html>

