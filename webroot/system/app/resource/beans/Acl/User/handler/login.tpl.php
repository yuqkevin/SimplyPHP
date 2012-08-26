<div id="MoAclUser-login" style="max-width:500px;margin:0 auto;">
<style type="text/css">
<?php include("login.css");?>
</style>
<form id="user-login-form" class="w3s-ajax" method="post" action="<?php print $this->stream['comp_url'];?>">
<input type="hidden" name="timestamp" value="<?php print $timestamp;?>" />
<input type="hidden" name="nonce" value="<?php print $nonce;?>" />
<div>
	<fieldset><legend>Notice</legend>
	<p>This resource is for registered and authorized user only. Please sign in to continue access.</p>
	</fieldset>
</div>
<div>
	<label for="login-id">Login Id</label><br />
	<input type="text" name="login" id="login-id" />
</div>
<div>
	<label for="login-pass">Password</label><br />
	<input type="password" name="pass" id="login-pass" />
</div>
<div class="w3s-button w3s-ralign"><a class="w3s-trigger" name="submit" target="user-login-form">Sign In</a></div>
</form>
</div>
