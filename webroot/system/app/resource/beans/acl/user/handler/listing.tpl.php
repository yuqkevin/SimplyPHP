<div id="account-listing">
<table class="w3s-list">
<caption class="w3s-lalign">
	<a class="w3s-right w3s-trigger" style="font-size:.9em;font-weight:normal;color:#333;" href="<?php print $create_url;?>" name="popup">+ New Account</a>
	Account Listing <span style="font-weight:normal;">by <?php print $operator['login'];?></span>
</caption>
<tr><th>Login ID</th><th>Name</th><th></th></tr>
<?php foreach ((array)$accounts as $account):?>
<tr class="w3s-bar"><td><?php print $account['login'];?></td><td><?php print $account['nickname'];?></td>
	<td>
		<?php if ($account['id']==$account['dna']):?>
			<em>Primary Account</em>
		<?php elseif ($account['id']==$operator['id']):?>
			<em>Your Own Account</em>
		<?php else:?>
		<span class="w3s-button">
		<a href="<?php print $account['edit_handler'];?>" class="w3s-trigger w3s-right" name="popup" title="User Account Profile">Edit</a>
		<a href="<?php print $account['del_handler'];?>" class="w3s-trigger w3s-right" name="action" rel="This account will be delete permanantly, continue?">Delete</a>
		</span>
		<?php endif;?>
	</td></tr>
<?php endforeach;?>
</table>
</div>

