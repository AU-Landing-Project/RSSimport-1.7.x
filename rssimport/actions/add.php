<?php

// this action saves a new import feed

action_gatekeeper();

$_SESSION['rssimport'] = array();
$_SESSION['rssimport']['feedtitle'] = $feedtitle = get_input('feedtitle');
$_SESSION['rssimport']['feedurl'] = $feedurl = get_input('feedurl');
$_SESSION['rssimport']['copyright'] = $copyright = get_input('copyright');
$_SESSION['rssimport']['cron'] = $cron = get_input('cron');
$_SESSION['rssimport']['defaultaccess'] = $defaultaccess = get_input('defaultaccess');
$_SESSION['rssimport']['defaulttags'] = $defaulttags = get_input('defaulttags');
$_SESSION['rssimport']['import_into'] = $import_into = get_input('import_into');
$_SESSION['rssimport']['containerid'] = $containerid = get_input('containerid');


//sanity checking
if(empty($feedtitle) || empty($feedurl)){
	register_error(elgg_echo('rssimport:empty:field'));
	forward(REFERRER);
}

if(empty($copyright)){
	register_error(elgg_echo('rssimport:empty:copyright'));
	forward(REFERRER);
}



//create our object
$rssimport = new ElggObject();
$rssimport->title = $feedtitle;
$rssimport->owner_guid = get_loggedin_userid();
$rssimport->subtype = 'rssimport';
$rssimport->description = $feedurl;
$rssimport->access_id = ACCESS_PRIVATE;
$rssimport->save();

//add our metadata
if($copyright == true){
	$rssimport->copyright = true;
}

$rssimport->cron = $cron;
$rssimport->defaultaccess = $defaultaccess;
$rssimport->defaulttags = $defaulttags;
$rssimport->import_into = $import_into;
// don't want to name it container_guid but that's the function of this
$rssimport->containerid = $containerid;

unset($_SESSION['rssimport']);

//set message and send back
system_message(elgg_echo('rssimport:import:created'));
$url = $vars['url'] . "pg/rssimport/" . $containerid . "/" . $import_into . "/" . $rssimport->guid;
forward($url);