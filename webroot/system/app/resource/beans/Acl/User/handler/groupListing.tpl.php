<div id="account-listing">
<table class="w3s-list">
<caption class="w3s-lalign">
	<a class="w3s-right w3s-trigger" style="font-size:.9em;font-weight:normal;color:#333;" href="<?php print $create_url;?>" name="popup">+ New Group</a>
	Group Listing <span style="font-weight:normal;">by <?php print $operator['login'];?></span>
</caption>
<tr><th>Name</th><th>Comments</th><th></th></tr>
<?php foreach ((array)$groups as $group):?>
<tr class="w3s-bar"><td><?php print $group['name'];?></td><td class="w3s-desc"><?php print $group['comments'];?></td>
	<td>
		<?php if ($group['primary']):?>
			<em>System Super Group</em>
		<?php elseif ($group['own']):?>
			<em>Your Own Group</em>
		<?php else:?>
		<span class="w3s-button">
		<a href="<?php print $group['edit_handler'];?>" class="w3s-trigger w3s-right" name="popup" title="User Group Definition">Edit</a>
		<?php if (isset($used_groups[$group['id']])):?>
			<a class="w3s-disabled">Delete</a>
		<?php else:?>
			<a href="<?php print $group['del_handler'];?>" class="w3s-trigger w3s-right" name="action" rel="This group will be delete permanantly, continue?">Delete</a>
		<?php endif;?>
		</span>
		<?php endif;?>
	</td></tr>
<?php endforeach;?>
</table>
</div>

