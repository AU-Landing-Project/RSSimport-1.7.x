<?php

action_gatekeeper();

// get our feed object
$rssimport_id = get_input('id');
$rssimport = get_entity($rssimport_id);

// make sure we're the owner if selecting a feed
if($rssimport instanceof ElggObject && get_loggedin_userid() != $rssimport->owner_guid){
	register_error(elgg_echo('rssimport:not:owner'));
	forward(REFERRER);
}

// now we know we're logged in, and are the owner of the import
// go ahead and delete

if($rssimport instanceof ElggObject){
	$rssimport->delete();
	system_message(elgg_echo('rssimport:delete:success'));
}
else{
	register_error(elgg_echo('rssimport:delete:fail'));
}

forward(REFERRER);