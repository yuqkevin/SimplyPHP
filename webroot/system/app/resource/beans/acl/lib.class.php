<?php
class LibAcl extends Library
{
	/*** bool nonce(string $act, string $nonce, int $timestamp)
	 *	@description validating a nonce with timestamp and register for valid nonce
	 *	@input	$act		'check/record'
	 *			$nonce		nonce in string
	 *			$timestmp	unix timestamp, seconds since unix epoch
	 *	@return	true if no use or recorded successfuly, or false for an used nonce
	***/
	public function nonce($act, $nonce, $timestamp)
	{
		$param = array('id'=>$nonce,'timestamp'=>$timestamp);
		if ($act=='record') return (bool) $this->tbl->nonce->create($param);
		if ($act=='check') return !(bool)$this->tbl->nonce->search($param);
	}
    /*** string nonce_verify(string $user_nonce, string $server_nonce, int $timestamp[, int $lifetime=0])
     *  @description verify nonce key and returns error code if failure, or null for pass
     *  @input  $user_nonce     nonce user submitted
     *          $server_nonce   nonce generate at server side
     *          $timestamp      timestamp of nonce
     *          $lifetime       lifetime of nonce in seconds, 0 for no expire, 1 hour as default
     *  @return error code for failure, null for pass
    ***/
    public function nonce_verify($user_nonce, $server_nonce, $timestamp, $lifetime=3600)
    {
        if ($lifetime&&(time()-intval($timestamp))>$lifetime) return 'NONCE_FAILURE_EXPIRE';
        if ($user_nonce!==$server_nonce) return 'NONCE_FAILURE_NOMATCH';
        if (!$this->nonce('check', $user_nonce, $timestamp)) return 'NONCE_FAILURE_DUPLICATED';
		return null;
    }
}
