<?php
define('DEBUG', 1);
define('TZ_OFFSET', 0); // GMT
define('SESSIONLIFETIME', 3600*24);
define('SESSION', $_SERVER['HTTP_HOST']);

$conf = array('model'=>null); // application model for root entry

$dsn['default']['hostname'] = '';
$dsn['default']['username'] = '';
$dsn['default']['password'] = '';
$dsn['default']['database'] = '';
$dsn['default']['dbdriver'] = ''; // mysql, oracle

$conf['dsn'] = $dsn;
