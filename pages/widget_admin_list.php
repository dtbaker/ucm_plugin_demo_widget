<?php 

$search = (isset($_REQUEST['search']) && is_array($_REQUEST['search'])) ? $_REQUEST['search'] : array();
if(isset($_REQUEST['customer_id'])){
    $search['customer_id'] = $_REQUEST['customer_id'];
}
$widgets = module_widget::get_widgets($search);


// hack to add a "group" option to the pagination results.
if(class_exists('module_group',false)){
    module_group::enable_pagination_hook(
        // what fields do we pass to the group module from this customers?
        array(
            'fields'=>array(
                'owner_id' => 'widget_id',
                'owner_table' => 'widget',
                'name' => 'name',
                'email' => ''
            ),
        )
    );
}
if(class_exists('module_table_sort',false)){
    module_table_sort::enable_pagination_hook(
    // pass in the sortable options.
        array(
            'table_id' => 'widget_list',
            'sortable'=>array(
                // these are the "ID" values of the <th> in our table.
                // we use jquery to add the up/down arrows after page loads.
                'widget_name' => array(
                    'field' => 'name',
                    'current' => 1, // 1 asc, 2 desc
                ),
                'widget_customer' => array(
                    'field' => 'customer_name',
                ),
                'widget_status' => array(
                    'field' => 'status',
                ),
                // special case for group sorting.
                'widget_group' => array(
                    'group_sort' => true,
                    'owner_table' => 'widget',
                    'owner_id' => 'widget_id',
                ),
            ),
        )
    );
}
// hack to add a "export" option to the pagination results.
if(class_exists('module_import_export',false) && module_widget::can_i('view','Export '.'Widgets')){
    module_import_export::enable_pagination_hook(
        // what fields do we pass to the import_export module from this customers?
        array(
            'name' => 'Widget'.' Export',
            'fields'=>array(
                'Widget'.' ID' => 'widget_id',
                'Customer Name' => 'customer_name',
                'Customer Contact First Name' => 'customer_contact_fname',
                'Customer Contact Last Name' => 'customer_contact_lname',
                'Customer Contact Email' => 'customer_contact_email',
                'Widget'.' Name' => 'name',
                'Widget'.' Status' => 'status',
            ),
            // do we look for extra fields?
            'extra' => array(
                'owner_table' => 'widget',
                'owner_id' => 'widget_id',
            ),
        )
    );
}
$header_buttons = array();
if(module_widget::can_i('create','Widgets')){
    $header_buttons[] = array(
        'url' => module_widget::link_open('new'),
        'type' => 'add',
        'title' => _l('Add New '.'Widget'),
    );
}
if(class_exists('module_import_export',false) && module_widget::can_i('view','Import '.'Widgets')){
    $link = module_import_export::import_link(
        array(
            'callback'=>'module_widget::handle_import',
            'callback_preview'=>'module_widget::handle_import_row_debug',
            'name'=>'Widgets',
            'return_url'=>$_SERVER['REQUEST_URI'],
            'group'=>'widget',
            'fields'=>array(
                'Widget'.' ID' => 'widget_id',
                'Customer Name' => 'customer_name',
                'Customer Contact First Name' => 'customer_contact_fname',
                'Customer Contact Last Name' => 'customer_contact_lname',
                'Customer Contact Email' => 'customer_contact_email',
                'Widget'.' Name' => 'name',
                'Widget'.' Status' => 'status',
            ),
            // extra args to pass to our widget import handling function.
            'options' => array(
                'duplicates'=>array(
                    'label' => _l('Duplicates'),
                    'form_element' => array(
                        'name' => 'duplicates',
                        'type' => 'select',
                        'blank' => false,
                        'value' => 'ignore',
                        'options' => array(
                            'ignore'=>_l('Skip Duplicates'),
                            'overwrite'=>_l('Overwrite/Update Duplicates')
                        ),
                    ),
                ),
            ),
            // do we attempt to import extra fields?
            'extra' => array(
                'owner_table' => 'widget',
                'owner_id' => 'widget_id',
            ),
        )
    );
    $header_buttons[] = array(
        'url' => $link,
        'type' => 'add',
        'title' => _l("Import ".'Widgets'),
    );
}
print_heading(array(
    'type' => 'h2',
    'main' => true,
    'title' => _l('Customer '.'Widgets'),
    'button' => $header_buttons,
))
?>


<form action="" method="post">


<?php $search_bar = array(
    'elements' => array(
        'name' => array(
            'title' => _l('Name:'),
            'field' => array(
                'type' => 'text',
                'name' => 'search[generic]',
                'value' => isset($search['generic'])?$search['generic']:'',
                'size' => 30,
            )
        ),
        'status' => array(
            'title' => _l('Status:'),
            'field' => array(
                'type' => 'select',
                'name' => 'search[status]',
                'value' => isset($search['status'])?$search['status']:'',
                'options' => module_widget::get_statuses(),
            )
        ),
    )
);
echo module_form::search_bar($search_bar); 


/** START TABLE LAYOUT **/
$table_manager = module_theme::new_table_manager();
$columns = array();
$columns['widget_name'] = array(
        'title' => 'Name',
        'callback' => function($widget){
            echo module_widget::link_open($widget['widget_id'],true,$widget);
        },
        'cell_class' => 'row_action',
    );

if(!isset($_REQUEST['customer_id']) && module_customer::can_i('view','Customers')){
    $columns['widget_customer'] = array(
            'title' => 'Customer',
            'callback' => function($widget){
                 echo module_customer::link_open($widget['customer_id'],true);
            },
        );
}
$columns['widget_status'] = array(
        'title' => 'Status',
        'callback' => function($widget){
            echo htmlspecialchars($widget['status']);
        },
    );
if(class_exists('module_group',false)){
    $columns['widget_group'] = array(
        'title' => 'Group',
        'callback' => function($widget){
            if(isset($widget['group_sort_widget'])){
                echo htmlspecialchars($widget['group_sort_widget']);
            }else{
                // find the groups for this widget.
                $groups = module_group::get_groups_search(array(
                    'owner_table' => 'widget',
                    'owner_id' => $widget['widget_id'],
                ));
                $g=array();
                foreach($groups as $group){
                    $g[] = $group['name'];
                }
                echo htmlspecialchars(implode(', ',$g));
            }
        }
    );
}
if(class_exists('module_extra',false)){
    $table_manager->display_extra('widget',function($widget){
        module_extra::print_table_data('widget',$widget['widget_id']);
    });
}
if(class_exists('module_subscription',false)){
    $table_manager->display_subscription('widget',function($widget){
        module_subscription::print_table_data('widget',$widget['widget_id']);
    });
}

$table_manager->set_columns($columns);
$table_manager->row_callback = function($row_data){
    // load the full vendor data before displaying each row so we have access to more details
    return module_widget::get_widget($row_data['widget_id']);
};
$table_manager->set_rows($widgets);
$table_manager->pagination = true;
$table_manager->print_table();
?>
</form>