<?php

$config = require './config.inc.php';

function config( $key ) {
    global $config;
    return $config[ $key ];
}

require config('ispconfig_configuration');
require 'Web.php';

$web = new Web();
$web->runBackup();