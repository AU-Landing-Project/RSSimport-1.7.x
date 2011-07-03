<?php

//
//	this function returns an array of all imports for the logged in user
//
function get_user_rssimports(){
	$user = get_loggedin_user();

	if(!$user){
		return false;
	}

	$num_imports = get_entities('object', 'rssimport', $user->guid, '', '', '', true);
	
	$options = array();
	$options['owner_guids'] = $user->guid;
	$options['type_subtype_pairs'] = array('object' => 'rssimport');
	$options['limit'] = $num_imports;

	return elgg_get_entities($options);
}

//
//	This function adds a list of item ids (passed as array $items)
//	and adds them to the blacklist for the given import
//	These items won't be imported on cron, or visible by default
//
function rssimport_add_to_blacklist($items, $rssimport){
	$blacklist = $rssimport->blacklist;
	
	// turn list into an array
	$blackarray = explode(',', $blacklist);
	
	// add new items to the existing array
	$itemcount = count($items);
	for($i=0; $i<$itemcount; $i++){
		$blackarray[] = $items[$i];
	}
	
	// make sure we don't have duplicate entries
	$blackarray = array_unique($blackarray);
	$blackarray = array_values($blackarray);
	
	// reform list from array
	$blacklist = implode(',', $blackarray);
	
	$rssimport->blacklist = $blacklist;
}


//
//	This function annotates an rssimport object with the most recent import
//	stores a string of guids that were created
//
function rssimport_add_to_history($array, $rssimport){
	//create comma delimited string of new guids
	if(is_array($array)){
		if(count($array) > 0){
			$history = implode(',', $array);
			$rssimport->annotate('rssimport_history', $history, ACCESS_PRIVATE, $rssimport->owner_guid);		
		}
	}	
}

//
//	Checks if an item has been imported previously
//	was a unique function, now a wrapper for rssimport_check_for_duplicates()
//
function rssimport_already_imported($item, $rssimport){
	return rssimport_check_for_duplicates($item, $rssimport);
}


//
//	this function saves a blog post from an rss item
//
function rssimport_blog_import($item, $rssimport){
	// Initialise a new ElggObject
	$blog = new ElggObject();
	// 	Tell the system it's a blog post
	$blog->subtype = "blog";
	// 	Set its owner to the current user
	$blog->owner_guid = $rssimport->owner_guid;
	// Set it's container
	// This could be selectable for groups or something...
	$blog->container_guid = $rssimport->containerid;
	// For now, set its access
	$blog->access_id = $rssimport->defaultaccess;
	// Set its title and description appropriately
	$blog->title = $item->get_title();
				
	//	build content of blog post
	$author = $item->get_author();
	$blogbody = $item->get_content();
	$blogbody .= "<br><br>";
	$blogbody .= "<hr><br>";
	$blogbody .= elgg_echo('rssimport:original') . ": <a href=\"" . $item->get_permalink() . "\">" . $item->get_permalink() . "</a> <br>";
	
	// some feed items don't have an author to get, check first 
	if(is_object($author)){
		$blogbody .= elgg_echo('rssimport:by') . ": " . $author->get_name() . "<br>";
	}
	
	$blogbody .= elgg_echo('rssimport:posted') . ": " . $item->get_date('F j, Y, g:i a');
	$blog->description = $blogbody;
	
	//add feed tags to default tags and remove duplicates
	$tagarray = string_to_tag_array($rssimport->defaulttags);
	foreach ($item->get_categories() as $category)
		{
			$tagarray[] = $category->get_label();
		}
	$tagarray = array_unique($tagarray);
	$tagarray = array_values($tagarray);
	
		// Now let's add tags. We can pass an array directly to the object property! Easy.
	if (is_array($tagarray)) {
		$blog->tags = $tagarray;
	}
				
	//whether the user wants to allow comments or not on the blog post
	// do we want to make this selectable?
	$blog->comments_on = true;
		// Now save the object
	$blog->save();
				
	//add metadata
	$token = rssimport_create_comparison_token($item);
	$blog->rssimport_token = $token;
	$blog->rssimport_id = $item->get_id();
	$blog->rssimport_permalink = $item->get_permalink();
	
	return $blog->guid;	
}

// imports a feed item into a bookmark
function rssimport_bookmarks_import($item, $rssimport){
		// flag to prevent saving if there are issues
		$error = false;
		
		//initiate our object
		$bookmark = new ElggObject;
		//set the subtype
		$bookmark->subtype = "bookmarks";
		//set the owner
		$bookmark->owner_guid = $rssimport->owner_guid;
		// set the container - for now it's just the owner
		$bookmark->container_guid = $rssimport->containerid;
		// set the title
		$bookmark->title = $item->get_title();
		// set the link
		// don't allow malicious code.
		// put this in a context of a link so HTMLawed knows how to filter correctly.
		$xss_test = "<a href=\"" . $item->get_permalink() . "\"></a>";
		if(function_exists('filter_tags')){
			if ($xss_test != filter_tags($xss_test)) {
				register_error(elgg_echo('rssimport:invalid:permalink'));
				$error = true;
			}
		}
		
		$bookmark->address = $item->get_permalink();
		// set the description
		$bookmark->description = $item->get_description();
		// set the access
		$bookmark->access_id = $rssimport->defaultaccess;
		
		// merge default tags with any from the feed
		$tagarray = string_to_tag_array($rssimport->defaulttags);
		foreach ($item->get_categories() as $category)
		{
			$tagarray[] = $category->get_label();
		}
		$tagarray = array_unique($tagarray);
		$tagarray = array_values($tagarray);
		$bookmark->tags = $tagarray;
		
		//if no errors save it
		if(!$error){
			$bookmark->save();
			
			//add metadata
			$token = rssimport_create_comparison_token($item);
			$bookmark->rssimport_token = $token;
			$bookmark->rssimport_id = $item->get_id();
			$bookmark->rssimport_permalink = $item->get_permalink();
			
			return $bookmark->guid;
		}
}


/**
 * 	Checks if a blog post exists for a user that matches a feed item
 * 	Return true if there is a match
 */
function rssimport_check_for_duplicates($item, $rssimport){
	
	// look for id first - less resource intensive
	// this will filter out anything that has already been imported
	$options = array();
	$options['container_guids'] = $rssimport->containerid;
	$options['type_subtype_pairs'] = array('object' => $rssimport->import_into);
	$options['metadata_name_value_pairs'] = array('name' => 'rssimport_id', 'value' => $item->get_id());
	$blogs = elgg_get_entities_from_metadata($options);
	
	if(!empty($blogs)){
		return true;
	}
	
	// look for permalink
	// this will filter out anything that has already been imported
	$options = array();
	$options['container_guids'] = $rssimport->containerid;
	$options['type_subtype_pairs'] = array('object' => $rssimport->import_into);
	$options['metadata_name_value_pairs'] = array('name' => 'rssimport_permalink', 'value' => $item->get_permalink());
	$blogs = elgg_get_entities_from_metadata($options);
	
	if(!empty($blogs)){
		return true;
	}
	
	$token = rssimport_create_comparison_token($item);
	
	//check by token - this will filter out anything that was a repost on the feed
	$options = array();
	$options['container_guids'] = $rssimport->containerid;
	$options['type_subtype_pairs'] = array('object' => $rssimport->import_into);
	$options['metadata_name_value_pairs'] = array('name' => 'rssimport_token', 'value' => $token);
	$blogs = elgg_get_entities_from_metadata($options);
	
	if(!empty($blogs)){
		return true;
	}
	
	return false;
}


/**
 * 	Creates a hash of various feed item variables for
 * 	easy comparison to feed created blogs
 */
function rssimport_create_comparison_token($item){
	$author = $item->get_author();
	$pretoken = $item->get_title();
	$pretoken .= $item->get_content();
	if(is_object($author)){
		$pretoken .= $author->get_name();
	}
	
	return md5($pretoken);
}


/**
 * Trigger imports
 *	use $params['period'] to find out which we are on
 *	eg; $params['period'] = 'hourly'
 */
function rssimport_cron($hook, $entity_type, $returnvalue, $params){
	// change context for permissions
	$context = get_context();
	set_context('rssimport_cron');
	elgg_set_ignore_access(TRUE);
	
	rssimport_include_simplepie();
	$cache_location = rssimport_set_simplepie_cache();
	// get array of imports we need to look at
	$options = array();
	$options['metadata_name_value_pairs'] = array('name' => 'cron', 'value' => $params['period']);
	$rssimport = elgg_get_entities_from_metadata($options);	
	$numimports = count($rssimport);
	
	
	// iterate through our imports
	for($i=0; $i<$numimports; $i++){
		if($rssimport[$i]->getSubtype() == "rssimport"){ // make sure we're only dealing with our import objects
		
		//get the feed
		$feed = new SimplePie($rssimport[$i]->description, $cache_location);
		
		$history = array();
		// for each feed, iterate through the items
		foreach ($feed->get_items(0,0) as $item):
			if(!rssimport_check_for_duplicates($item, $rssimport[$i]) && !rssimport_is_blacklisted($item, $rssimport[$i])){
				// no duplicate entries exist
				// item isn't blacklisted
				// import it
				switch ($rssimport[$i]->import_into) {
					case "blog":
						$history[] = rssimport_blog_import($item, $rssimport[$i]);
						break;
					case "blogs":
						$history[] = rssimport_blog_import($item, $rssimport[$i]);
						break;
					case "page":
						$history[] = rssimport_page_import($item, $rssimport[$i]);
						break;
					case "pages":
						$history[] = rssimport_page_import($item, $rssimport[$i]);
						break;
					case "bookmark":
						$history[] = rssimport_bookmarks_import($item, $rssimport[$i]);
						break;
					case "bookmarks":
						$history[] = rssimport_bookmarks_import($item, $rssimport[$i]);
						break;
					default:	// when in doubt, send to a blog
						$history[] = rssimport_blog_import($item, $rssimport[$i]);
						break;
				}
					
			}
		endforeach;

		rssimport_add_to_history($history, $rssimport[$i]);

		}
	}
	elgg_set_ignore_access(FALSE);
	//logout our admin
//	logout();
	set_context($context);
}

//
//	returns an array of groups that a user is a member of
//	and can post content to
//	returns false if there are no groups the user can post to
function rssimport_get_postable_groups($user){
	//get all groups
	$entity = get_entities('group');
	
	$entitycount = count($entity);
	if($entitycount == 0){
		return false;
	}
	
	$usergroups = array();
	for($i=0; $i<$entitycount; $i++){
		if(is_object($entity[$i])){
			if($entity[$i]->isMember($user)){
				$usergroups[] = $entity[$i];
			}
		}
	}
	
	if(count($usergroups) == 0){
		return false;
	}
	
	return $usergroups;
}

//
//	this function parses the URL to figure out what context and owner it belongs to, so we can generate
// 	a return URL 
//
//	URL is in the form of <baseurl>/pg/rssimport/<container_guid>/<context> where context is "blog", "bookmarks", or "page"
//	Generate a url of <baseurl>/pg/<context>/owner/<owner_name>
//	Note that pages has to make things difficult, it wants a url of pg/pages/owned/<owner_name> *note plural pages and owned instead of owner*
//	For groups, the <owner_name> is actually "group:###" where ### is the guid of the group
function rssimport_get_return_url(){
	global $CONFIG;
	$url = $_SERVER['REQUEST_URI'];
	
	if(get_context() == "rssimport"){
		// strips the url down to just the parts separated by "/", eg 2/blog/932
		// which is <owner_guid>/<context>/<rssimport_guid>  we're only concerned with owner_guid and context
		$ext = end(explode('rssimport/',$url));
		
		// takes the url parts, transforms to an array and returns the first two
		// $array[0] is the container_guid, $array[1] is the context
		$array = array_slice(explode("/", $ext), 0, 2);
	}
	
	//standardize for pages stupidity
	if($array[1] == "page" || $array[1] == "search"){
		$array[1] = "pages";
	}
	
	// get our owner entity
	$entity = get_entity($array[0]);
		
	// get the owner_name type, different for user and group
	if($entity instanceof ElggUser){
		$name = $entity->username;
	}
			
	if($entity instanceof ElggGroup){
		$name = "group:" . $array[0];
	}
	
	// create the url, switch to differentiate for pages... default means no match, return false
	switch($array[1]){
		case "blog":
		case "bookmarks":			
			$backurl = $CONFIG->url . "pg/{$array[1]}/owner/$name";
			break;
		case "pages":
			$backurl = $CONFIG->url . "pg/{$array[1]}/owned/$name";
			break;
		default:
			return false;		
	}
	
	//return array of link text and url
	$linktext = elgg_echo('rssimport:back:to:' . $array[1]);
	return array($linktext, $backurl);
}

//
//	this function includes the simplepie class if it doesn't exist
//
function rssimport_include_simplepie(){
	global $CONFIG;

	if (!class_exists('SimplePie')) {
		require_once $CONFIG->pluginspath . '/rssimport/lib/simplepie.inc';
	}
}

// returns true if the item has been blacklisted by the current user
function rssimport_is_blacklisted($item, $rssimport){
	$blacklist = $rssimport->blacklist;
	
	//create array from our list
	$blackarray = explode(',', $blacklist);
	
	if(in_array($item->get_id(true), $blackarray)){
		return true;
	}
	
	return false;
}


//
//	removes a single item from an array
//	resets keys
//
function rssimport_removeFromArray($value, $array){
	if(!is_array($array)){ return $array; }
	if(!in_array($value, $array)){ return $array; }
	
	for($i=0; $i<count($array); $i++){
		if($value == $array[$i]){
			unset($array[$i]);
			$array = array_values($array);
		}
	}
	
	return $array;
}

// this function removes an item from the blacklist
function rssimport_remove_from_blacklist($items, $rssimport){
	$blacklist = $rssimport->blacklist;
	
	// turn list into an array
	$blackarray = explode(',', $blacklist);
	
	// remove items from existing array
	$itemcount = count($items);
	for($i=0; $i<$itemcount; $i++){
		$blackarray = rssimport_removeFromArray($items[$i], $blackarray);
	}
	
	// reform list from array
	$blacklist = implode(',', $blackarray);
	
	$rssimport->blacklist = $blacklist;
}


function rssimport_page_import($item, $rssimport){
	//check if we have a parent page yet
	$options = array();
	$options['type_subtype_pairs'] = array('object' => 'page_top');
	$options['container_guids'] = $rssimport->containerid;
	$options['metadata_name_value_pairs'] = array(array('name' => 'rssimport_feedpage', 'value' => $rssimport->title), array('name' => 'rssimport_url', 'value' => $rssimport->description));
	$testpage = elgg_get_entities_from_metadata($options);
	
	if(!$testpage){
		//create our parent page
		$parent = new ElggObject();
		$parent->subtype = 'page_top';
		$parent->container_guid = $rssimport->containerid;
		$parent->owner_guid = $rssimport->owner_guid;
		$parent->access_id = $rssimport->defaultaccess;
		$parent->parent_guid = 0;
		$parent->write_access_id = $rssimport->defaultaccess;
		$parent->title = $rssimport->title;
		$parent->description = $rssimport->description;
		//set default tags
		$tagarray = string_to_tag_array($rssimport->defaulttags);
		$parent->tags = $tagarray;
		$parent->save();
		
		$parent->annotate('page', $parent->description, $parent->access_id, $parent->owner_guid);
		
		$parent_guid = $parent->guid;
		
		//add our identifying metadata
		$parent->rssimport_feedpage = $rssimport->title;
		$parent->rssimport_url = $rssimport->description;
	}
	else{
		$parent_guid = $testpage[0]->guid;
	}
	
	//initiate our object
	$page = new ElggObject();
	$page->subtype = 'page';
	$page->container_guid = $rssimport->containerid;
	$page->owner_guid = $rssimport->owner_guid;
	$page->access_id = $rssimport->defaultaccess;
	$page->parent_guid = $parent_guid;
	$page->write_access_id = $rssimport->defaultaccess;
	$page->title = $item->get_title();
	
	$author = $item->get_author();
	$pagebody = $item->get_content();
	$pagebody .= "<br><br>";
	$pagebody .= "<hr><br>";
	$pagebody .= elgg_echo('rssimport:original') . ": <a href=\"" . $item->get_permalink() . "\">" . $item->get_permalink() . "</a> <br>";
	if(is_object($author)){
		$pagebody .= elgg_echo('rssimport:by') . ": " . $author->get_name() . "<br>";
	}
	$pagebody .= elgg_echo('rssimport:posted') . ": " . $item->get_date('F j, Y, g:i a');
	
	$page->description = $pagebody;
	
	//set default tags
	$tagarray = string_to_tag_array($rssimport->defaulttags);
	foreach ($item->get_categories() as $category)
		{
			$tagarray[] = $category->get_label();
		}
	$tagarray = array_unique($tagarray);
	$tagarray = array_values($tagarray);

	// Now let's add tags. We can pass an array directly to the object property! Easy.
	if (is_array($tagarray)) {
		$page->tags = $tagarray;
	}
	
	$page->save();
	
	$page->annotate('page', $page->description, $page->access_id, $page->owner_guid);
	
	//add our identifying metadata
	$token = rssimport_create_comparison_token($item);
	$page->rssimport_token = $token;
	$page->rssimport_id = $item->get_id();
	$page->rssimport_permalink = $item->get_permalink();
	
	return $page->guid;
}

// allows write permissions when we are adding metadata to an object
function rssimport_permissions_check(){
	if (get_context() == 'rssimport_cron') {
		return true;
	}
 
	return null;
}


function rssimport_set_simplepie_cache(){
	global $CONFIG;
	// 	set cache for simplepie if it doesn't exist
	$cache_location = $CONFIG->dataroot . '/simplepie_cache/';
	if (!file_exists($cache_location)) {
		mkdir($cache_location, 0777);
	}
	
	return $cache_location;
}