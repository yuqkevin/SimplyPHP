<?php
/*** default component
 *	@description	Listing latest guest messages
***/
$limit = 20;
$this->stream['data']['lines'] = $this->post_search(null, "order by post_id desc limit $limit");
