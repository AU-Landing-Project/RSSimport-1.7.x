<?php 
if(get_context() == "rssimport" || get_context() == "rssimport_history"){
?>
<link rel="stylesheet" href="<?php echo $vars['url']; ?>mod/rssimport/css/style.css" type="text/css" />
<script type="text/javascript" src="<?php echo $vars['url']; ?>mod/rssimport/js/javascript.js"></script>
<?php 
}
?>