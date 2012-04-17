<div id="comp-user-login" style="width:300px;height:100px;margin:0 auto;padding:1em;">
<div><?php print @$this->stream['conf']['message'];?></div>
<form>
<input type="hidden" name="_token" value="<?php print $this->s_encrypt('go', 0);?>" id="_token" />
<table style="width:100%;">
<caption>User Login Window</caption>
<tr><td><strong>User Id</td><td><input type="text" name="w3s-login" id="user_login" /></td></tr>
<tr><td><strong>Password</td><td><input type="password" name="w3s-pass" id="user_pass" /></td></tr>
</table>
<div class="w3s-footer w3s-ralign w3s-button"><a href="<?php print $this->stream['conf']['src_url'];?>" class="w3s-trigger w3s-tmp" name="action" rev="user_login,user_pass,_token" >Sign In</a></div>
</form>
<script>
$('#user_login').focus();
</script>
</div>
