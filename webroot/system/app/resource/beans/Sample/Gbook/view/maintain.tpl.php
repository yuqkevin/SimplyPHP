<!DOCTYPE html>
<html lang="en-us">
<head>
<title>Sample: Guest Book</title>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<meta name="keywords" content="" />
<meta name="description" content="" />
</head>
<body>
<h1 id="page-header">Sample: Guest Book <span style="font-weight:normal;font-size:13px;">Edit Posted Message</span></h1>
<hr />
<div id="page-body" style="width:800px;">
    <div style="color:red;"><?php print $message;?></div>
    <form method="post">
	<div><strong><?php print htmlspecialchars($post['guest']);?></strong> posted at <em><?php print $post['timestamp'];?></em>
    <label>Message</label><br /><textarea name="content" style="width:100%; height:5em;"><?php print htmlspecialchars($post['content']);?></textarea><br />
    <div style="text-align:right;padding-right:1em;">
        <button type="button" onClick="window.location.href='/SampleGbook/'">Cancel</button>
        <button type="submit" name="update" value="<?php print $post['id'];?>">Submit</button>
    </div>
    </form>
</div>
<hr />
<div id="page-footer">Sample for SimplyPHP</div>
</body>
</html>

