<?php

action_gatekeeper();

// get our form inputs
$feedid = get_input('feedid');
$rssimport = get_entity($feedid);
$itemidstring = get_input('rssimportImport');
$items = explode(',', $itemidstring);


//sanity checking
if(!($rssimport instanceof ElggObject)){
	register_error(elgg_echo('rssimport:invalid:id'));
	forward(REFERRER);
}

if(empty($itemidstring)){
	register_error(elgg_echo('rssimport:none:selected'));
	forward(REFERRER);	
}

//initiate simplepie
rssimport_include_simplepie();
$cache_location = rssimport_set_simplepie_cache();

// get our feed
$feed = new SimplePie($rssimport->description, $cache_location);
$num_posts_in_feed = $feed->get_item_quantity();

$history = array();
//iterate through and import anything with a matching ID
foreach ($feed->get_items(0, $num_items) as $item):
	if(in_array($item->get_id(true), $items)){
		if(!rssimport_check_for_duplicates($item, $rssimport)){
			
			switch ($rssimport->import_into) {
					case "blog":
						$history[] = rssimport_blog_import($item, $rssimport);
						break;
					case "blogs":
						$history[] = rssimport_blog_import($item, $rssimport);
						break;
					case "page":
						$history[] = rssimport_page_import($item, $rssimport);
						break;
					case "pages":
						$history[] = rssimport_page_import($item, $rssimport);
						break;
					case "bookmark":
						$history[] = rssimport_bookmarks_import($item, $rssimport);
						break;
					case "bookmarks":
						$history[] = rssimport_bookmarks_import($item, $rssimport);
						break;
					default:	// when in doubt, send to a blog
						$history[] = rssimport_blog_import($item, $rssimport);
						break;
			}
			
		}
	}
endforeach;

rssimport_add_to_history($history, $rssimport);

system_message(elgg_echo('rssimport:imported'));
forward(REFERRER);