<?php
if(get_context() == "blog" || get_context() == "pages" || get_context() == "bookmarks"){

echo "<a href=\"" . $vars['url'] . "pg/rssimport/" . page_owner() . "/" . get_context() . "\" class=\"pagelinks rssimport_pagelink\">" . elgg_echo('rssimport:import') . "</a>";
 
}
?>

