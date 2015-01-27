<?php


$widget_id = (int)$_REQUEST['widget_id'];
$widget = module_widget::get_widget($widget_id);


if($widget_id>0 && $widget['widget_id']==$widget_id){
    $module->page_title = 'Widget' .': '.$widget['name'];
}else{
    $module->page_title = 'Widget' .': '._l('New');
}

if($widget_id>0 && $widget){
	if(class_exists('module_security',false)){
		module_security::check_page(array(
            'module' => $module->module_name,
            'feature' => 'edit',
		));
	}
}else{
	if(class_exists('module_security',false)){
		module_security::check_page(array(
            'module' => $module->module_name,
            'feature' => 'create',
		));
	}
	module_security::sanatise_data('widget',$widget);
}


?>


	
<form action="" method="post">
	<input type="hidden" name="_process" value="save_widget" />
    <input type="hidden" name="widget_id" value="<?php echo $widget_id; ?>" />


    <?php

    $fields = array(
    'fields' => array(
        'name' => 'Name',
    ));
    module_form::set_required(
        $fields
    );
    module_form::prevent_exit(array(
        'valid_exits' => array(
            // selectors for the valid ways to exit this form.
            '.submit_button',
            '.form_save',
        ))
    );
    


    hook_handle_callback('layout_column_half',1,'35');

    $fieldset_data = array(
        'heading' => array(
            'type' => 'h3',
            'title' => _l('Widget'.' Details'),
        ),
        'class' => 'tableclass tableclass_form tableclass_full',
        'elements' => array(
            'name' => array(
                'title' => _l('Name'),
                'field' => array(
                    'type' => 'text',
                    'name' => 'name',
                    'value' => $widget['name'],
                ),
            ),
        ),
        'extra_settings' => array(
            'owner_table' => 'widget',
            'owner_key' => 'widget_id',
            'owner_id' => $widget['widget_id'],
            'layout' => 'table_row',
            'allow_new' => module_widget::can_i('create','Widgets'),
            'allow_edit' => module_widget::can_i('create','Widgets'),
        )
,
    );
    $fieldset_data['elements']['Status'] = array(
        'title' => _l('Status'),
        'fields' => array(
            array(
                'type' => 'select',
                'name' => 'status',
                'value' => $widget['status'],
                'options' => module_widget::get_statuses(),
                'allow_new' => true,
            ),
        )
    );

    echo module_form::generate_fieldset($fieldset_data);

    /*** ADVANCED ****/
    if(module_widget::can_i('edit','Widgets')) {
	    $c   = array();
	    $res = module_customer::get_customers();
	    foreach ( $res as $row ) {
		    $c[ $row['customer_id'] ] = $row['customer_name'];
	    }
	    $fieldset_data = array(
		    'heading'  => array(
			    'type'  => 'h3',
			    'title' => _l( 'Advanced' ),
		    ),
		    'class'    => 'tableclass tableclass_form tableclass_full',
		    'elements' => array()
	    );
	    if ( count( $res ) <= 1 && $widget['customer_id'] && isset( $c[ $widget['customer_id'] ] ) ) {
		    $fieldset_data['elements']['change'] = array(
			    'title'  => _l( 'Change Customer' ),
			    'fields' => array(
				    htmlspecialchars( $c[ $widget['customer_id'] ] ),
				    array(
					    'type'  => 'hidden',
					    'name'  => 'customer_id',
					    'value' => $widget['customer_id'],
				    )
			    )
		    );
	    } else {
		    $fieldset_data['elements']['change'] = array(
			    'title'  => _l( 'Change Customer' ),
			    'fields' => array(
				    array(
					    'type'    => 'select',
					    'name'    => 'customer_id',
					    'options' => $c,
					    'value'   => $widget['customer_id'],
					    'help'    => 'Changing a customer will also change all the current linked jobs and invoices across to this new customer.',
				    )
			    )
		    );
	    }

	    echo module_form::generate_fieldset( $fieldset_data );
    }


    if((int)$widget_id>0){
        if(class_exists('module_group',false)){
            module_group::display_groups(array(
                 'title' => 'Widget'.' Groups',
                'owner_table' => 'widget',
                'owner_id' => $widget_id,
                'view_link' => module_widget::link_open($widget_id),

             ));
        }

        // and a hook for our new change request plugin
        hook_handle_callback('widget_sidebar',$widget_id);

    }

    hook_handle_callback('layout_column_half',2,65);


    if((int)$widget_id > 0){

	    if(class_exists('module_note',false)) {
		    module_note::display_notes( array(
				    'title'       => 'Widget' . ' Notes',
				    'owner_table' => 'widget',
				    'owner_id'    => $widget_id,
				    'view_link'   => module_widget::link_open( $widget_id ),
			    )
		    );
	    }



        // and a hook for our new change request plugin
        hook_handle_callback('widget_main',$widget_id);
    }


    hook_handle_callback('layout_column_half','end');

    $form_actions = array(
        'class' => 'action_bar action_bar_center',
        'elements' => array(
            array(
                'type' => 'save_button',
                'name' => 'butt_save',
                'value' => _l('Save '.'Widget'),
            ),
            array(
                'ignore' => !((int)$widget_id && module_widget::can_i('delete','Widgets')),
                'type' => 'delete_button',
                'name' => 'butt_del',
                'value' => _l('Delete'),
            ),
            array(
                'type' => 'button',
                'name' => 'cancel',
                'value' => _l('Cancel'),
                'class' => 'submit_button',
                'onclick' => "window.location.href='".module_widget::link_open(false)."';",
            ),
        ),
    );
    echo module_form::generate_form_actions($form_actions);

    ?>



</form>
