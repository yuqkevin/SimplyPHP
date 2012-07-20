<?php if (!$keyword):?>
<div id="_w3s-accounts-block" class="w3s-mainbox">
<?php endif;?>
<?php $url = $this->stream['comp_url']; $seq = $this->sequence(1);?>
<div class="w3s-button w3s-ralign">
<?php if ($operator['dna']==LibAclUser::DNA_SYS):?>
<span class="w3s-left">Search:<input type="text" class="w3s-search" name="keyword" id="_w3s-keyword" placeholder="User Login Id or DNA" value="<?php print $keyword;?>"
	 onKeyup="if ($(this).val()) {$(this).next().show(); } else { $(this).next().hide();}" />
<span style="display:none;"><?php print $libUi->trigger(array('url'=>$url,'name'=>'action','text'=>'Go','token'=>$this->s_encrypt('search', 0),'field'=>'_w3s-keyword','target'=>'_w3s-accounts-block'));?></span></span>
<?php print $libUi->trigger(array('url'=>$url,'name'=>'popup','title'=>'New Client Primary Account','text'=>'New Client','token'=>$this->s_encrypt('client', 0)));?>
<?php endif;?>
<?php print $libUi->trigger(array('url'=>$url,'name'=>'popup','text'=>'New Account','token'=>$this->s_encrypt('create', 0)));?>
</div>
<div class="w3s-grid">
<table>
<caption>
User Account 
</caption>
<tr><th>User</th><th>Zone</th><th>Name</th><th>Email Verified</th><th>Comments</th><th>Action</th></tr>
<?php foreach ($users as $user):?>
<tr class="w3s-bar"><td><?php print $user['login'];?></td><td><?php print $user['dna']==LibAclUser::DNA_SYS?'System':$user['dna'];?></td>
	<td><?php print $user['nickname'];?></td><td><?php print $user['email']==$user['login']?'Yes':'No';?></td>
	<td class="w3s-desc">
		<?php print $operator['id']!=$user['id']&&($operator['id']==$operator['dna']||$operator['id']==$user['creator']||($operator['dna']==LibAclUser::DNA_SYS&&$operator['dna']!=$user['dna']))?htmlspecialchars($user['comments']):'N/A';?></td>
	<td><div class="w3s-button">
<?php
	$url = $this->stream['comp_url'].'?id='.$user['id'];
	$btn_edit = $libUi->trigger(array('url'=>$url,'name'=>'popup','title'=>'User Edit','text'=>'Edit','token'=>$this->s_encrypt('edit', $user['id'])));
	$btn_del = $libUi->trigger(array('url'=>$url,'name'=>'action','text'=>'Delete','token'=>$this->s_encrypt('delete', $user['id']),'confirm'=>'Permanent Remove this account?'));
	$btn_pwd= $libUi->trigger(array('url'=>$url,'name'=>'action','text'=>'Password','token'=>$this->s_encrypt('password', $user['id']),'confirm'=>"Reset user {$user['login']}'s password, contine?"));
	$btn_copy = $libUi->trigger(array('url'=>$url,'name'=>'popup','title'=>'Clone Account','text'=>'Clone','token'=>$this->s_encrypt('copy', $user['id'])));
	if ($user['id']==$operator['id']) {
		$btn_del = $libUi->trigger(array('text'=>'Delete','class'=>'w3s-disabled'));
		$btn_edit = $libUi->trigger(array('text'=>'Edit','class'=>'w3s-disabled'));
		$btn_pwd = $libUi->trigger(array('text'=>'Password','class'=>'w3s-disabled'));
	} elseif ($user['id']===$user['dna']) {
		if ($user['dna']==LibAclUser::DNA_SYS||$operator['dna']!=LibAclUser::DNA_SYS) {
			// system primary account cannot be delete,  and non-system operator cannot delete primary account
			$btn_del = $libUi->trigger(array('text'=>'Delete','class'=>'w3s-disabled'));
		} else {
			$confirm = "Warninig!!!\nThis is a primary account. Remove it will destroy all accounts under this user.\nContinue?";
			$btn_del = $libUi->trigger(array('url'=>$url,'name'=>'action','text'=>'Delete','token'=>$this->s_encrypt('delete', $user['id']),'confirm'=>$confirm));
		}
		if ($operator['dna']!=LibAclUser::DNA_SYS&&$operator['id']!=$user['id']) {
			 $btn_edit = $libUi->trigger(array('text'=>'Edit','class'=>'w3s-disabled'));
			 $btn_copy = $libUi->trigger(array('text'=>'Copy','class'=>'w3s-disabled'));
			 $btn_pwd = $libUi->trigger(array('text'=>'Password','class'=>'w3s-disabled'));
		}
	}
	print $btn_copy.$btn_edit.$btn_pwd.$btn_del;
?>
	</div></td></tr>
<?php endforeach;?>
</table>
</div>
<?php if (!$keyword):?>
</div>
<?php endif;?>
