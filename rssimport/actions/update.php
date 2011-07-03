<?php

// this action saves a new import feed

action_gatekeeper();

$_SESSION['rssimport'] = array();
$_SESSION['rssimport']['feedtitle'] = $feedtitle = get_input('feedtitle');
$_SESSION['rssimport']['feedurl'] = $feedurl = get_input('feedurl');
$_SESSION['rssimport']['cron'] = $cron = get_input('cron');
$_SESSION['rssimport']['defaultaccess'] = $defaultaccess = get_input('defaultaccess');
$_SESSION['rssimport']['defaulttags'] = $defaulttags = get_input('defaulttags');
$copyright = get_input('copyright');
$updating_id = get_input('updating_id');
$import_into = get_input('import_into');
$containerid = get_input('containerid');


//sanity checking
$rssimport = get_entity($updating_id);

if(!($rssimport instanceof ElggObject)){
	register_error(elgg_echo('rssimport:wrong:id'));
	foward(REFERRER);
}

if($rssimport->owner_guid != get_loggedin_userid()){
	register_error(elgg_echo('rssimport:not:owner'));
	forward(REFERRER);
}

if(empty($feedtitle) || empty($feedurl)){
	register_error(elgg_echo('rssimport:empty:field'));
	forward(REFERRER);
}

if($copyright != true){
	register_error(elgg_echo('rssimport:copyright:error'));
	forward(REFERRER);
}



//update our object
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
else{
	$rssimport->copyright = false;
}

$rssimport->cron = $cron;
$rssimport->defaultaccess = $defaultaccess;
$rssimport->defaulttags = $defaulttags;
$rssimport->import_into = $import_into;
// don't want to name it container_guid but that's the function of this
$rssimport->containerid = $containerid;

//set message and send back
system_message(elgg_echo('rssimport:import:updated'));
unset($_SESSION['rssimport']);
forward(REFERRER);