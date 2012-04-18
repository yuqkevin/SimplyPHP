<div style="width:600px;"><?php $seq=$this->sequence(1);?>
<form class="w3s-ajax" method="post" action="<?php print $this->stream['comp_url'];?>">
<input type="hidden" name="id" value="<?php print @$account['id'];?>" />
<input type="hidden" name="stage" value="1" />
<input type="hidden" name="_token" value="<?php print $_token;?>" />
<table class="w3s-list">
<tr><td class="w3s-title">Email</td><td>
    <?php if (isset($account['login'])):?><strong><?php print $account['login'];?></strong><?php else:?>
    <input type="text" name="login" class="w3s-mandatory" value="<?php print @$account['login'];?>" /><?php endif;?></td></tr>
<tr><td class="w3s-title">Name</td><td><input type="text" name="nickname" value="<?php print @$account['nickname'];?>" /></td></tr>
<tr><td class="w3s-title">Group</td><td>
    <select name="group" onChange="var g=$('#g-<?php print $seq;?>');$(this).val()=='x'?g.removeClass('w3s-invisible'):g.addClass('w3s-invisible'); "><?php print $group_options;?><option value="x">New Group</option></select><span id="g-<?php print $seq;?>" class="w3s-invisible" style="padding-left:2em;">Group Name:&nbsp;<input type="text" name="new-group" style="width:8em;" /></span>
    </td></tr>
<tr><td class="w3s-title">Comments</td><td><textarea name="comments"><?php print htmlspecialchars(@$account['comments']);?></textarea></td></tr>
</table>
<div class="w3s-footer w3s-button w3s-ralign">
<?php print $libUi->trigger(array('url'=>'#','name'=>'submit','text'=>'Submit'));?>
<?php print $libUi->trigger(array('url'=>'#','name'=>'close','text'=>'Close'));?>
</div>
</form>
</div>

