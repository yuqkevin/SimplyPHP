<style type="text/css">
.w3s-rotator {border:1px solid gray;background:#eee;padding:1em;-moz-border-radius:4px;-webkit-border-radius: 4px;<?php print $style;?>;overflow:hidden;}
.w3s-rotator {line-height:25px;}
.w3s-rotator ul {padding-left:1em;line-height:25px;font-style:italic;}
.w3s-rotator img {opacity:0.7;filter:alpha(opacity=70);}
.w3s-rotator:hover img {opacity:1;filter:alpha(opacity=100)}
</style>
<div class="w3s-rotator"><?php print $body;?></div>
<script type="text/javascript">
$(document).ready(function() {
    var max_h = $('.w3s-rotator').innerHeight();
    $('.w3s-rotator').w3sBox('rotator',{'stay':8000}).find('img').css('max-height',max_h);
});
</script>
