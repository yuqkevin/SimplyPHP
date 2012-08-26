<?php
class LibAcl extends Library
{
	/*** bool nonce(string $act[, string $nonce[, int $timestamp[, int $ttl=3600]])
	 *	@description create,record,check or verify nonces
	 *	@input	$act	new: get a new nonce,
	 *					record: register nonce,timestamp pair into database
	 *					check: search nonce,timestamp pair in database
	 *					verify: verify given nonce by given timestamp
	 *			$nonce	the nonce will be verified/record/checked here
	 *			$timestamp	timestamp for verifying/record/checking
	 *			$ttl	lifetime in seconds for verification, 0 for never expire verify
	 *	@return	new: array('nonce'=>new_nonce, 'timestamp'=>timestamp_in_nonce)
	 *			record: true for record success, false for failure
	 *			check:	true no-duplicate found, false for duplicated nonce found
	 *			verify:	false if failure or true for pass
	***/
	public function nonce($act, $nonce=null, $timestamp=null, $ttl=3600)
	{
		switch ($act) {
			case 'new':
				$timestamp = time();
				$nonce = md5($this->conf['global']['salt'].$timestamp.$this->session('id'));
				return compact('nonce', 'timestamp');
			case 'record':
				return (bool) $this->tbl->nonce->create(array('id'=>$nonce,'timestamp'=>$timestamp));
			case 'check':
				return !(bool)$this->tbl->nonce->search(array('id'=>$nonce,'timestamp'=>$timestamp));
			case 'verify':
				if ($ttl&&(time()-intval($timestamp))>$ttl) {
					$this->status['error_code'] = 'NONCE_FAILURE_EXPIRE';
					return false;
				}
				$nonce_verify = md5($this->conf['global']['salt'].$timestamp.$this->session('id'));
				if ($nonce!=$nonce_verify) {
					$this->status['error_code'] = 'NONCE_FAILURE_NOMATCH';
					return false;
				}
				if (!$this->nonce('check', $nonce, $timestamp)) {
					$this->status['error_code'] = 'NONCE_FAILURE_DUPLICATED';
					return false;
				}
				return true;
		}
		return false;
	}
}
