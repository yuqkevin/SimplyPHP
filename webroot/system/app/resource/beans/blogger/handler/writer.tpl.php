<?php $libUi = $this->get_lib('LibUiHtml');?>
<div class="w3s-mainbox" id="blogger-writing">
	<div class="w3s-button w3s-ralign">
		<?php print $libUi->trigger(array('url'=>$this->stream['comp_url'],'name'=>'overlap','target'=>'blogger-writing','text'=>'New Post','token'=>$this->s_encrypt('create', 0)));?>
	</div>
	<h2 style="font:small-caption;border-bottom:1px solid #ddd;padding:5px;">Blog Post</h2>
	<div class="w3s-mainbox"><ul>
	<?php foreach ((array)$posts as $post):?>
	<?php
		$url = $this->stream['comp_url'].'?id='.$post['id'];
		$btn_edit = $libUi->trigger(array('url'=>$url,'name'=>'overlap','target'=>'blogger-writing','text'=>'','title'=>'Blog Post Edit','class'=>'w3s-icon w3s-icon-edit','token'=>$this->s_encrypt('modify', $post['id'])));
		$btn_delete = $libUi->trigger(array('url'=>$url,'name'=>'action','text'=>'','class'=>'w3s-icon w3s-icon-delete','confirm'=>'Are you really want delete this post?','token'=>$this->s_encrypt('delete', $post['id'])));
	?>
	<li class="w3s-bar" style="line-height:25px;"><span class="w3s-desc"><?php print $post['create'];?></span>&nbsp;<strong><?php print htmlspecialchars($post['title']);?></strong>&nbsp;
		<span class="w3s-desc"><em>(<?php print $post['status']==LibBlogger::POST_STATUS_DRAFT?'Draft':'Released';?>)</em></span>
		<span class="w3s-button"><?php print $btn_edit.$btn_delete;?></span>
	</li>
	<?php endforeach;?>
	</ul>
	</div>
</div>
