<?php 

if(isset($_REQUEST['widget_id'])){

	include(module_theme::include_ucm("includes/plugin_widget/pages/widget_admin_edit.php"));

}else{ 
	
    include(module_theme::include_ucm("includes/plugin_widget/pages/widget_admin_list.php"));
	
} 

