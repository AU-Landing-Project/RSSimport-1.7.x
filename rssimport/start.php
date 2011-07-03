<?php

global $CONFIG;

include_once 'lib/functions.php';


// our init function
function rssimport_init() {
	// Load system configuration
	global $CONFIG;

	// Extend system CSS with our own styles
	elgg_extend_view('metatags','rssimport/metatags');

	// Load the language file
	register_translations($CONFIG->pluginspath . "rssimport/languages/");
	
	// Set up menu for logged in users
	
	//register action to save our import object
	register_action('rssimport/add', false, $CONFIG->pluginspath . "rssimport/actions/add.php");
	
	//register action to delete an import object
	register_action('rssimport/delete', false, $CONFIG->pluginspath . "rssimport/actions/delete.php");
	
	//register action to update an import object
	register_action('rssimport/update', false, $CONFIG->pluginspath . "rssimport/actions/update.php");

	//register action to manually import a feed object
	register_action('rssimport/import', false, $CONFIG->pluginspath . "rssimport/actions/import.php");
	
	//register action to blacklist a feed object
	register_action('rssimport/blacklist', false, $CONFIG->pluginspath . "rssimport/actions/blacklist.php");
	
	//register action to undo an import from the history
	register_action('rssimport/undoimport', false, $CONFIG->pluginspath . "rssimport/actions/undoimport.php");

	// register page handler
	register_page_handler('rssimport','rssimport_page_handler');
	
	// register cron hook
    register_plugin_hook('cron', 'all', 'rssimport_cron');
    
    // override permissions for the rssimport_cron context
	register_plugin_hook('permissions_check', 'all', 'rssimport_permissions_check');
}


// page structure for imports <url>/pg/rssimport/<container_guid>/<context>/<rssimport_guid>
// history: <url>/pg/rssimport/history/<rssimport_guid>
function rssimport_page_handler($page){
	global $CONFIG;
	
	if(is_numeric($page[0])){
	
		//set import_into based on context
		//sometimes context is plural, make it match the subtype in the database
		if($page[1] == 'blog'){ $import_into = "blog"; }
		if($page[1] == 'blogs'){ $import_into = "blog"; }
		if($page[1] == 'bookmark'){ $import_into = "bookmarks"; }
		if($page[1] == 'bookmarks'){ $import_into = "bookmarks"; }
		if($page[1] == 'pages'){ $import_into = "page"; }
		if($page[1] == 'page'){ $import_into = "page"; }
		//first page of "pages" has context of search
		if($page[1] == "search"){ $import_into = "page"; }
	
		set_input('container_guid', $page[0]);
		set_input('import_into', $import_into);
		set_input('rssimport_guid', $page[2]);
		if(!include $CONFIG->pluginspath . '/rssimport/pages/rssimport.php'){
			forward(REFERRER);
		}		
	}
	else{		//not numeric first option, so must be another page
		if($page[0] == "history" && is_numeric($page[1])){
			set_input('rssimport_guid', $page[1]);
			set_context('rssimport_history');
			if(!include $CONFIG->pluginspath . '/rssimport/pages/history.php'){
				forward(REFERRER);
			}
		}
	}
}

// add links to submenus
function rssimport_submenus() {

	global $CONFIG;

	// Get the page owner entity
	$page_owner = page_owner_entity();
	$context = get_context();
	$rssimport_guid = get_input('rssimport_guid');
	$rssimport = get_entity($rssimport_guid);
	$createlink = false;

	// Submenu items for group pages, if logged in and context is one of our imports
	if(isloggedin() && ($context == 'blog' || $context == "pages" || $context == "bookmarks" || strpos($_SERVER['REQUEST_URI'], '/pg/pages/'))){
		// if we're on a group page, check that the user is a member of the group
		if($page_owner instanceof ElggGroup){
			if($page_owner->isMember($_SESSION['user'])) {
				$createlink = true;
			}
		}
		
		// if we are the owner
		if($page_owner->guid == $_SESSION['user']->guid){
			$createlink = true;
		}
	}
	
	if($createlink){
		add_submenu_item(elgg_echo('rssimport:import'), $CONFIG->wwwroot . "pg/rssimport/" . page_owner() . "/" . $context);
	}
	
	// create "back" link on import page - go back to blogs/pages/etc.
	if(isloggedin() && $context == "rssimport"){
		//have to parse URL to figure out what page type and owner to send them back to
		//this function does it, and returns an array('link_text','url')
		$linkparts = rssimport_get_return_url();
		add_submenu_item($linkparts[0], $linkparts[1]);
	}
	
	// create link to "View History" on import page
	if(isloggedin() && $context == "rssimport" && !empty($rssimport_guid)){
		add_submenu_item(elgg_echo('rssimport:view:history'), $CONFIG->wwwroot . "pg/rssimport/history/" . $rssimport_guid);
	}
	

	// create link to "View Import" on history page
	if(isloggedin() && $context == "rssimport_history" && !empty($rssimport_guid)){
		add_submenu_item(elgg_echo('rssimport:view:import'), $CONFIG->wwwroot . "pg/rssimport/" . $rssimport->containerid . "/" . $rssimport->import_into . "/" . $rssimport_guid);
	}
}

	
register_elgg_event_handler('init','system','rssimport_init');
// add our submenu links
register_elgg_event_handler('pagesetup','system','rssimport_submenus');
add_subtype('object','rssimport');
?>