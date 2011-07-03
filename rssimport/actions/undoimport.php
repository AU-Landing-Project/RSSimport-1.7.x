<?php

action_gatekeeper();

$id = get_input('id');

$history = get_annotation($id);

//sanity check
if(!is_object($history)){
	register_error(elgg_echo('rssimport:invalid:history'));
	forward(REFERRER);	
}

if($history->owner_guid != get_loggedin_userid()){
	register_error(elgg_echo('rssimport:wrong:permissions'));
	forward(REFERRER);
}


// so now we know we're the owner, we can go ahead and delete
$ids = explode(',', $history->value);
for($i=0; $i<count($ids); $i++){
	$entity = get_entity($ids[$i]);
	delete_entity($ids[$i]);
}

// all imported entities deleted - now delete the history entry
$history->delete();

//set message and return to referrer
system_message(elgg_echo('rssimport:undoimport:success'));
forward(REFERRER);