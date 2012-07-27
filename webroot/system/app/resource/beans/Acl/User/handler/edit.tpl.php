<div style="width:600px;">
<form id="account-edit-form" class="w3s-ajax" method="post" action="<?php print $this->stream['comp_url'];?>">
<input type="hidden" name="id" value="<?php print @$account['id'];?>" />
<input type="hidden" name="token" value="<?php print $token;?>" />
<input type="hidden" name="nonce" value="<?php print $nonce;?>" />
<input type="hidden" name="timestamp" value="<?php print $timestamp;?>" />
<table class="w3s-list">
<caption class="w3s-lalign">User Profile</option>
<tr><td class="w3s-ralign">Login</td><td>
    <?php if ($act!=='create'):?><em><?php print $account['login'];?></em><?php else:?>
    <div><input type="text" name="login" class="w3s-data-mandatory" placeholder="User's email address" /></div><?php endif;?></td></tr>
<tr><td class="w3s-ralign">Name</td>
	<td><div><input type="text" class="w3s-data-mandatory" name="nickname" value="<?php print @$account['nickname'];?>" /></div></td></tr>
<tr><td class="w3s-ralign">Group</td><td>
    <select name="group" onChange="var g=$('#act-group');$(this).val()=='x'?g.removeClass('w3s-invisible'):g.addClass('w3s-invisible'); "><?php print $group_options;?><option value="x">New Group</option></select><span id="act-group" class="w3s-invisible" style="padding-left:2em;">Group Name:&nbsp;<input type="text" name="new-group" style="width:8em;" /></span>
    </td></tr>
<tr><td class="w3s-ralign">Comments</td><td><div><textarea name="comments"><?php print htmlspecialchars(@$account['comments']);?></textarea></div></td></tr>
</table>
<div class="w3s-footer w3s-button w3s-ralign">
<a class="w3s-trigger" name="submit" target="account-edit-form">Submit</a>
<a class="w3s-trigger" name="close">Cancel</a>
</div>
</form>
</div>

