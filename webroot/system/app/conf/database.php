<?php
if (!defined('DEBUG')) define('DEBUG', 1); // GMT
if (!defined('TZ_OFFSET')) define('TZ_OFFSET', 0); // GMT
if (!defined('SESSIONLIFETIME')) define('SESSIONLIFETIME', 3600*24);
if (!defined('SESSION')) define('SESSION', $_SERVER['HTTP_HOST']);

$conf = array('model'=>null); // application model for root entry

$dsn['default']['hostname'] = '';
$dsn['default']['username'] = '';
$dsn['default']['password'] = '';
$dsn['default']['database'] = '';
$dsn['default']['dbdriver'] = ''; // mysql, oracle

$conf['dsn'] = $dsn;
