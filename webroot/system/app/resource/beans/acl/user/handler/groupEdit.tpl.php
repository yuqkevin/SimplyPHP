<div style="width:800px;">
<style style="text/css">
div.action-model {font-weight:bold;}
div.action-handler {font-weight:normal;padding-left:1.5em;}
div.action-action {padding-left:1.5em;}
</style>
<form id="group-edit-form" class="w3s-ajax" method="post" action="<?php print $this->stream['comp_url'];?>">
<input type="hidden" name="id" value="<?php print @$group['id'];?>" />
<input type="hidden" name="token" value="<?php print $token;?>" />
<input type="hidden" name="nonce" value="<?php print $nonce;?>" />
<input type="hidden" name="timestamp" value="<?php print $timestamp;?>" />
<table class="w3s-list">
<tr><td>Group Title:<br />
    <div><input type="text" name="name" class="w3s-data-mandatory" value="<?php print htmlspecialchars(@$group['name']);?>" /></div></td></tr>
    </td></tr>
<tr><td>Comments:<br /><div><textarea name="comments"><?php print htmlspecialchars(@$group['comments']);?></textarea></div></td></tr>
<tr><td><fieldset style="border:1px solid #555;padding:1em;"><legend>Access Tuning</legend>
	<div>
	<?php foreach ($actions as $model=>$action_def):?>
		<div class="action-model"><input type="checkbox" name="actions[]" value="<?php print "$model::*::*";?>" id="m-<?php print $model;?>" class="action-obj"/>
			<?php print substr($model,2).'&nbsp;&nbsp;<span class="w3s-desc">'.htmlspecialchars(@$action_def['MODEL::']['description']).'</span>';?>
			<?php foreach ($action_def['HANDLER::'] as $handler=>$desc):?>
			<div class="action-handler m-<?php print $model;?>">
				<input type="checkbox" name="actions[]" value="<?php print "$model::$handler::*";?>" id="h-<?php print $handler;?>" class="action-obj" />
					<?php print $handler."&nbsp;&nbsp;<span class=\"w3s-desc\">".htmlspecialchars($desc)."</span>";?>
				<?php if (isset($action_def[$handler])):?>
				<?php foreach ($action_def[$handler] as $action=>$act_desc):?>
					<div class="action-action h-<?php print $handler;?>">
						<input type="checkbox" name="actions[]" value="<?php print "$model::$handler::$action";?>"/>
						<?php print $action."&nbsp;&nbsp;<span class=\"w3s-desc\">".htmlspecialchars($act_desc)."</span>";?></div>
				<?php endforeach;?>
				<?php endif;?>
			</div>
			<?php endforeach;?>
	<?php endforeach;?>
	</div>
	</fieldset></td></tr>
</table>
<div class="w3s-footer w3s-button w3s-ralign">
<a class="w3s-trigger" name="submit" target="group-edit-form">Submit</a>
<a class="w3s-trigger" name="close">Cancel</a>
</div>
</form>
<script type="text/javascript">
var action_handler = function() {
	var sub_cls = '.'+$(this).attr('id');
	if (this.checked) {
		$(sub_cls).find(':checkbox').prop('checked',true).attr('disabled','disable');
	} else {
		$(sub_cls).find(':checkbox').prop('checked',false).removeAttr('disabled');
	}
};
$(document).ready(function(){
	$('#group-edit-form :checkbox.action-obj').bind('change', action_handler);
	var actions =['<?php print join("','", (array)$group_actions);?>'];
	for (var act in actions) {
		$('#group-edit-form :checkbox').each(function(){
			if ($(this).val()==actions[act]) $(this).trigger('click');
		});
	}
});
</script>
</div>

