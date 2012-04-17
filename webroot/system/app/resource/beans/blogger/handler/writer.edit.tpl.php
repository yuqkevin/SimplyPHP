<?php $libUi=$this->get_lib('LibUiHtml');?>
<div id="post-form-div" class="w3s-mainbox">
<form class="w3s-ajax w3s-mainbox" method="post" action="<?php print $this->stream['comp_url'];?>">
	<input type="hidden" name="id" value="<?php print @$post['id'];?>" />
	<input type="hidden" name="stage" value="1" />
	<table id="post-form-tbl" class="w3s-list">
	<tr><td class="w3s-title">Title</td><td><input type="text" name="title" value="<?php print @$post['title'];?>" /></td></tr>
	<tr><td class="w3s-title">Tags</td><td><input type="text" name="tags" style="width:70%;" value="<?php print @$post['tags'];?>" /><span class="w3s-desc">(separate with ',' or space)</span></td></tr>
	<tr><td class="w3s-title w3s-malign" colspan="2">Content</td></tr>
	</table>
	<textarea name="body" class="w3s-mainbox"><?php print htmlspecialchars(@$post['body']);?></textarea>
	<div class="w3s-footer w3s-button w3s-ralign">
	<?php 
		print $libUi->trigger(array('url'=>'#','name'=>'submit','text'=>'Save','token'=>$this->s_encrypt('save', intval(@$post['id']))));
		if (@$post['status']!=LibBlogger::POST_STATUS_RELEASE) print $libUi->trigger(array('url'=>'#','name'=>'submit','text'=>'Release','token'=>$this->s_encrypt('release', intval(@$post['id']))));
		print $libUi->trigger(array('url'=>'#','name'=>'close','text'=>'Close'));
	?>
	</div>
</form>
</div>
