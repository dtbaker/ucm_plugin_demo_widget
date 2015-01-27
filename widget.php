<?php



class module_widget extends module_base{

	public $links;
	public $widget_types;

    public static function can_i($actions,$name=false,$category=false,$module=false){
        if(!$module)$module=__CLASS__;
        return parent::can_i($actions,$name,$category,$module);
    }
	public static function get_class() {
        return __CLASS__;
    }
	public function init(){
		$this->links = array();
		$this->widget_types = array();
		$this->module_name = "widget";
		$this->module_position = 16;
        $this->version = 2.1;
        //2.1 - initial release



        if($this->can_i('view','Widgets') && module_widget::is_plugin_enabled()){

            // only display if a customer has been created.
            if(isset($_REQUEST['customer_id']) && $_REQUEST['customer_id'] && $_REQUEST['customer_id']!='new'){
                // how many widgets?
                $widgets = $this->get_widgets(array('customer_id'=>$_REQUEST['customer_id']));
                $name = 'Widgets';
                if(count($widgets)){
                    $name .= " <span class='menu_label'>".count($widgets)."</span> ";
                }
                $this->links[] = array(
                    "name"=>$name,
                    "p"=>"widget_admin",
                    'args'=>array('widget_id'=>false),
                    'holder_module' => 'customer', // which parent module this link will sit under.
                    'holder_module_page' => 'customer_admin_open',  // which page this link will be automatically added to.
                    'menu_include_parent' => 0,
                    'icon_name' => 'globe',
                );
            }
            $this->links[] = array(
                "name"=>'Widgets',
                "p"=>"widget_admin",
                'args'=>array('widget_id'=>false),
                'icon_name' => 'globe',
            );

        }
		
	}
    
    public function ajax_search($search_key){
        // return results based on an ajax search.
        $ajax_results = array();
        $search_key = trim($search_key);
        if(strlen($search_key) > module_config::c('search_ajax_min_length',2)){
            $results = $this->get_widgets(array('generic'=>$search_key));
            if(count($results)){
                foreach($results as $result){
                    $match_string = _l('Widget: ');
                    $match_string .= _shl($result['name'],$search_key);
                    $ajax_results [] = '<a href="'.$this->link_open($result['widget_id']) . '">' . $match_string . '</a>';
                }
            }
        }
        return $ajax_results;
    }

    public static function link_generate($widget_id=false,$options=array(),$link_options=array()){

        $key = 'widget_id';
        if($widget_id === false && $link_options){
            foreach($link_options as $link_option){
                if(isset($link_option['data']) && isset($link_option['data'][$key])){
                    ${$key} = $link_option['data'][$key];
                    break;
                }
            }
            if(!${$key} && isset($_REQUEST[$key])){
                ${$key} = $_REQUEST[$key];
            }
        }
        $bubble_to_module = false;
        if(!isset($options['type']))$options['type']='widget';
        $options['page'] = 'widget_admin';
        if(!isset($options['arguments'])){
            $options['arguments'] = array();
        }
        $options['arguments']['widget_id'] = $widget_id;
        $options['module'] = 'widget';
        if((int)$widget_id > 0){
            $data = self::get_widget($widget_id);
            $options['data'] = $data;
        }else{
            $data = array();
            if(isset($_REQUEST['customer_id']) && (int)$_REQUEST['customer_id']>0){
                $data['customer_id'] = (int)$_REQUEST['customer_id'];
            }
            if(!isset($options['full']) || !$options['full']){
                // we are not doing a full <a href> link, only the url (eg: create new widget)
            }else{
                // we are trying to do a full <a href> link -
                return _l('N/A');
            }
        }
        // what text should we display in this link?
        $options['text'] = (!isset($data['name'])||!trim($data['name'])) ? _l('N/A') : $data['name'];
        if(isset($data['customer_id']) && $data['customer_id']>0){
            $bubble_to_module = array(
                'module' => 'customer',
                'argument' => 'customer_id',
            );
        }
        array_unshift($link_options,$options);

        if(!module_security::has_feature_access(array(
            'name' => 'Customers',
            'module' => 'customer',
            'category' => 'Customer',
            'view' => 1,
            'description' => 'view',
        ))){
            $bubble_to_module = false;
            /*if(!isset($options['full']) || !$options['full']){
                return '#';
            }else{
                return isset($options['text']) ? $options['text'] : _l('N/A');
            }*/

        }
        if($bubble_to_module){
            global $plugins;
            return $plugins[$bubble_to_module['module']]->link_generate(false,array(),$link_options);
        }else{
            // return the link as-is, no more bubbling or anything.
            // pass this off to the global link_generate() function
            return link_generate($link_options);

        }
    }

	public static function link_open($widget_id,$full=false){
        return self::link_generate($widget_id,array('full'=>$full));
    }

	
	public function process(){
		$errors=array();
		if(isset($_REQUEST['butt_del']) && $_REQUEST['butt_del'] && $_REQUEST['widget_id']){
            $data = self::get_widget($_REQUEST['widget_id']);
            if(module_form::confirm_delete('widget_id',"Really delete ".'Widget'.": ".$data['name'],self::link_open($_REQUEST['widget_id']))){
                $this->delete_widget($_REQUEST['widget_id']);
                set_message('Widget'." deleted successfully");
                redirect_browser(self::link_open(false));
            }
		}else if("save_widget" == $_REQUEST['_process']){
			$widget_id = $this->save_widget($_REQUEST['widget_id'],$_POST);
            hook_handle_callback('widget_save',$widget_id);
			$_REQUEST['_redirect'] = $this->link_open($widget_id);
			set_message('Widget'." saved successfully");
		}
		if(!count($errors)){
			redirect_browser($_REQUEST['_redirect']);
			exit;
		}
		print_error($errors,true);
	}


	public static function get_widgets($search=array(), $return_options=array()){
		// limit based on customer id
		/*if(!isset($_REQUEST['customer_id']) || !(int)$_REQUEST['customer_id']){
			return array();
		}*/
		// build up a custom search sql query based on the provided search fields
		$sql = "SELECT ";
        if(isset($return_options['columns'])){
            $sql .= $return_options['columns'];
        }else{
            $sql .= " u.*,u.widget_id AS id ";
            $sql .= ", u.name AS name ";
            $sql .= ", c.customer_name ";
            $sql .= ", cc.name AS customer_contact_fname ";
            $sql .= ", cc.last_name AS customer_contact_lname ";
            $sql .= ", cc.email AS customer_contact_email ";
            // add in our extra fields for the csv export
            //if(isset($_REQUEST['import_export_go']) && $_REQUEST['import_export_go'] == 'yes'){
            if(class_exists('module_extra',false)){
                $sql .= " , (SELECT GROUP_CONCAT(ex.`extra_key` ORDER BY ex.`extra_id` ASC SEPARATOR '"._EXTRA_FIELD_DELIM."') FROM `"._DB_PREFIX."extra` ex WHERE owner_id = u.widget_id AND owner_table = 'widget') AS extra_keys";
                $sql .= " , (SELECT GROUP_CONCAT(ex.`extra` ORDER BY ex.`extra_id` ASC SEPARATOR '"._EXTRA_FIELD_DELIM."') FROM `"._DB_PREFIX."extra` ex WHERE owner_id = u.widget_id AND owner_table = 'widget') AS extra_vals";
            }
        }
        $from = " FROM `"._DB_PREFIX."widget` u ";
        $from .= " LEFT JOIN `"._DB_PREFIX."customer` c USING (customer_id)";
        $from .= " LEFT JOIN `"._DB_PREFIX."user` cc ON c.primary_user_id = cc.user_id ";
		$where = " WHERE 1 ";
		if(isset($search['generic']) && $search['generic']){
			$str = mysql_real_escape_string($search['generic']);
			$where .= " AND ( ";
			$where .= " u.name LIKE '%$str%' ";
			$where .= ' ) ';
		}
        foreach(array('customer_id','status') as $key){
            if(isset($search[$key]) && $search[$key] !== ''&& $search[$key] !== false){
                $str = mysql_real_escape_string($search[$key]);
                $where .= " AND u.`$key` = '$str'";
            }
        }
        // tie in with customer permissions to only get jobs from customers we can access.
        switch(module_customer::get_customer_data_access()){
            case _CUSTOMER_ACCESS_ALL:
                // all customers! so this means all jobs!
                break;
            case _CUSTOMER_ACCESS_ALL_COMPANY:
            case _CUSTOMER_ACCESS_CONTACTS:
            case _CUSTOMER_ACCESS_STAFF:
                $valid_customer_ids = module_security::get_customer_restrictions();
                if(count($valid_customer_ids)){
                    $where .= " AND u.customer_id IN ( ";
                    foreach($valid_customer_ids as $valid_customer_id){
                        $where .= (int)$valid_customer_id.", ";
                    }
                    $where = rtrim($where,', ');
                    $where .= " )";
                }
                break;
            case _CUSTOMER_ACCESS_TASKS:
                // only customers who have a job that I have a task under.
                // this is different to "assigned jobs" Above
                // this will return all jobs for a customer even if we're only assigned a single job for that customer
                // tricky!
                // copied from customer.php
                $where .= " AND u.widget_id IN ";
                $where .= " ( SELECT jj.widget_id FROM `"._DB_PREFIX."job` jj ";
                $where .= " LEFT JOIN `"._DB_PREFIX."task` tt ON jj.job_id = tt.job_id ";
                $where .= " WHERE (jj.user_id = ".(int)module_security::get_loggedin_id()." OR tt.user_id = ".(int)module_security::get_loggedin_id().")";
                $where .= " )";

                break;

        }

		$group_order = ' GROUP BY u.widget_id ORDER BY u.name'; // stop when multiple company sites have same region
		$sql = $sql . $from . $where . $group_order;
		$result = qa($sql);
		//module_security::filter_data_set("widget",$result);
		return $result;
//		return get_multiple("widget",$search,"widget_id","fuzzy","name");

	}
	public static function get_widget($widget_id){
		$widget = get_single("widget","widget_id",$widget_id);
        if($widget){
            switch(module_customer::get_customer_data_access()){
                case _CUSTOMER_ACCESS_ALL:
                    // all customers! so this means all jobs!
                    break;
                case _CUSTOMER_ACCESS_ALL_COMPANY:
                case _CUSTOMER_ACCESS_CONTACTS:
                case _CUSTOMER_ACCESS_STAFF:
                    $valid_customer_ids = module_security::get_customer_restrictions();
                    $is_valid_widget = isset($valid_customer_ids[$widget['customer_id']]);
                    if(!$is_valid_widget){
                        $widget = false;
                    }
                    break;
                case _CUSTOMER_ACCESS_TASKS:
                    // only customers who have linked jobs that I am assigned to.
                    $has_job_access = false;
                    if(isset($widget['customer_id']) && $widget['customer_id']){
                        $jobs = module_job::get_jobs(array('customer_id'=>$widget['customer_id']));
                        foreach($jobs as $job){
                            if($job['user_id']==module_security::get_loggedin_id()){
                                $has_job_access=true;
                                break;
                            }
                            $tasks = module_job::get_tasks($job['job_id']);
                            foreach($tasks as $task){
                                if($task['user_id']==module_security::get_loggedin_id()){
                                    $has_job_access=true;
                                    break;
                                }
                            }
                        }
                    }
                    if(!$has_job_access){
                        $widget = false;
                    }
                    break;

            }
        }

        if(!$widget){
            $widget = array(
                'widget_id' => 'new',
                'customer_id' => isset($_REQUEST['customer_id']) ? $_REQUEST['customer_id'] : 0,
                'name' => '',
                'status'  => module_config::s('widget_status_default','New'),
            );
        }
		return $widget;
	}
	public function save_widget($widget_id,$data){
        if((int)$widget_id>0){
            $original_widget_data = $this->get_widget($widget_id);
            if(!$original_widget_data || $original_widget_data['widget_id'] != $widget_id){
                $original_widget_data = array();
                $widget_id = false;
            }
        }else{
            $original_widget_data = array();
            $widget_id = false;
        }
        if(_DEMO_MODE && $widget_id == 1){
            set_error('This is a Demo Widget. Some things cannot be changed.');
            foreach(array('name','customer_id') as $key){
                if(isset($data[$key]))unset($data[$key]);
            }
        }

        // check create permissions.
        if(!$widget_id && !self::can_i('create','Widgets')){
            // user not allowed to create widgets.
            set_error('Unable to create new Widgets');
            redirect_browser(self::link_open(false));
        }

		$widget_id = update_insert("widget_id",$widget_id,"widget",$data);
        if(isset($original_widget_data['customer_id']) && $original_widget_data['customer_id'] && isset($data['customer_id']) && $data['customer_id'] && $original_widget_data['customer_id'] != $data['customer_id']){
            //module_cache::clear_cache();
            // the customer id has changed. update jobs and invoices.
            // bad! this will swap all jobs, invoices and files from this customer to another customer.
            //module_job::customer_id_changed($original_widget_data['customer_id'],$data['customer_id']);
        }
        module_extra::save_extras('widget','widget_id',$widget_id);
		return $widget_id;
	}

	public static function delete_widget($widget_id){
		$widget_id=(int)$widget_id;
		if(_DEMO_MODE && $widget_id == 1){
            set_error('Sorry this is a Demo Widget. It cannot be deleted.');
			return;
		}
        if((int)$widget_id>0){
            $original_widget_data = self::get_widget($widget_id);
            if(!$original_widget_data || $original_widget_data['widget_id'] != $widget_id){
                return false;
            }
        }
        if(!self::can_i('delete','Widgets')){
            return false;
        }

        hook_handle_callback('widget_deleted',$widget_id);
		$sql = "DELETE FROM "._DB_PREFIX."widget WHERE widget_id = '".$widget_id."' LIMIT 1";
		query($sql);
        if(class_exists('module_group',false)){
            module_group::delete_member($widget_id,'widget');
        }
        foreach(module_job::get_jobs(array('widget_id'=>$widget_id)) as $val){
            module_job::delete_job($val['widget_id']);
        }
		module_note::note_delete("widget",$widget_id);
        module_extra::delete_extras('widget','widget_id',$widget_id);
	}
    public function login_link($widget_id){
        return module_security::generate_auto_login_link($widget_id);
    }

    public static function get_statuses(){
        $sql = "SELECT `status` FROM `"._DB_PREFIX."widget` GROUP BY `status` ORDER BY `status`";
        $statuses = array();
        foreach(qa($sql) as $r){
            $statuses[$r['status']] = $r['status'];
        }
        return $statuses;
    }



    public static function handle_import_row_debug($row, $add_to_group, $extra_options){
        return self::handle_import_row($row,true,$add_to_group,$extra_options);
    }

    public static function handle_import_row($row, $debug, $add_to_group, $extra_options){

        $debug_string = '';
        if(!isset($row['name']))$row['name'] = '';

        if(isset($row['widget_id']) && (int)$row['widget_id']>0){
            // check if this ID exists.
            $widget = self::get_widget($row['widget_id']);
            if(!$widget || $widget['widget_id'] != $row['widget_id']){
                $row['widget_id'] = 0;
            }
        }
        if(!isset($row['widget_id']) || !$row['widget_id']){
            $row['widget_id'] = 0;
        }
        if(isset($row['name']) && strlen(trim($row['name']))){
            // we have a widget name!
            // search for a widget based on name.
            $widget = get_single('widget','name',$row['name']);
            if($widget && $widget['widget_id'] > 0){
                $row['widget_id'] = $widget['widget_id'];
            }
        }
        if(!strlen($row['name'])) {
            $debug_string .= _l('No widget data to import');
            if($debug){
                echo $debug_string;
            }
            return false;
        }
        // duplicates.
        //print_r($extra_options);exit;
        if(isset($extra_options['duplicates']) && $extra_options['duplicates'] == 'ignore' && (int)$row['widget_id']>0){
            if($debug){
                $debug_string .= _l('Skipping import, duplicate of widget %s',self::link_open($row['widget_id'],true));
                echo $debug_string;
            }
            // don't import duplicates
            return false;
        }
        $row['customer_id'] = 0; // todo - support importing of this id? nah
        if(isset($row['customer_name']) && strlen(trim($row['customer_name']))>0){
            // check if this customer exists.
            $customer = get_single('customer','customer_name',$row['customer_name']);
            if($customer && $customer['customer_id'] > 0){
                $row['customer_id'] = $customer['customer_id'];
                $debug_string .= _l('Linked to customer %s',module_customer::link_open($row['customer_id'],true)) .' ';
            }else{
                $debug_string .= _l('Create new customer: %s',htmlspecialchars($row['customer_name'])) .' ';
            }
        }else{
            $debug_string .= _l('No customer').' ';
        }
        if($row['widget_id']){
            $debug_string .= _l('Replace existing widget: %s',self::link_open($row['widget_id'],true)).' ';
        }else{
            $debug_string .= _l('Insert new widget: %s',htmlspecialchars($row['name'])).' ';
        }

        $customer_primary_user_id = 0;
        if($row['customer_id']>0 && isset($row['customer_contact_email']) && strlen(trim($row['customer_contact_email']))){
            $users = module_user::get_users(array('customer_id'=>$row['customer_id']>0));
            foreach($users as $user){
                if(strtolower(trim($user['email']))==strtolower(trim($row['customer_contact_email']))){
                    $customer_primary_user_id = $user['user_id'];
                    $debug_string .= _l('Customer primary contact is: %s',module_user::link_open_contact($customer_primary_user_id,true)).' ';
                    break;
                }
            }
        }

        if($debug){
            echo $debug_string;
            return true;
        }
        if(isset($extra_options['duplicates']) && $extra_options['duplicates'] == 'ignore' && $row['customer_id'] > 0){
            // don't update customer record with new one.

        }else if((isset($row['customer_name']) && strlen(trim($row['customer_name']))>0) || $row['customer_id']>0){
            // update customer record with new one.
            $row['customer_id'] = update_insert('customer_id',$row['customer_id'],'customer',$row);
            if(isset($row['customer_contact_fname']) || isset($row['customer_contact_email'])){
                $data = array(
                    'customer_id' => $row['customer_id']
                );
                if(isset($row['customer_contact_fname'])){
                    $data['name']=$row['customer_contact_fname'];
                }
                if(isset($row['customer_contact_lname'])){
                    $data['last_name']=$row['customer_contact_lname'];
                }
                if(isset($row['customer_contact_email'])){
                    $data['email']=$row['customer_contact_email'];
                }
                if(isset($row['customer_contact_phone'])){
                    $data['phone']=$row['customer_contact_phone'];
                }
                $customer_primary_user_id = update_insert("user_id",$customer_primary_user_id,"user",$data);
                module_customer::set_primary_user_id($row['customer_id'],$customer_primary_user_id);
            }
        }
        $widget_id = (int)$row['widget_id'];
        // check if this ID exists.
        $widget = self::get_widget($widget_id);
        if(!$widget || $widget['widget_id'] != $widget_id){
            $widget_id = 0;
        }
        $widget_id = update_insert("widget_id",$widget_id,"widget",$row);

        // handle any extra fields.
        $extra = array();
        foreach($row as $key=>$val){
            if(!strlen(trim($val)))continue;
            if(strpos($key,'extra:')!==false){
                $extra_key = str_replace('extra:','',$key);
                if(strlen($extra_key)){
                    $extra[$extra_key] = $val;
                }
            }
        }
        if($extra){
            foreach($extra as $extra_key => $extra_val){
                // does this one exist?
                $existing_extra = module_extra::get_extras(array('owner_table'=>'widget','owner_id'=>$widget_id,'extra_key'=>$extra_key));
                $extra_id = false;
                foreach($existing_extra as $key=>$val){
                    if($val['extra_key']==$extra_key){
                        $extra_id = $val['extra_id'];
                    }
                }
                $extra_db = array(
                    'extra_key' => $extra_key,
                    'extra' => $extra_val,
                    'owner_table' => 'widget',
                    'owner_id' => $widget_id,
                );
                $extra_id = (int)$extra_id;
                update_insert('extra_id',$extra_id,'extra',$extra_db);
            }
        }

        foreach($add_to_group as $group_id => $tf){
            module_group::add_to_group($group_id,$widget_id,'widget');
        }

        return $widget_id;

    }

    public static function handle_import($data,$add_to_group,$extra_options){

        // woo! we're doing an import.
        $count = 0;
        // first we find any matching existing widgets. skipping duplicates if option is set.
        foreach($data as $rowid => $row){
            if(self::handle_import_row($row, false, $add_to_group, $extra_options)){
                $count++;
            }
        }
        return $count;


    }

    public static function get_replace_fields($widget_id,$widget_data=false){

        if(!$widget_data)$widget_data = self::get_widget($widget_id);

        $data = array(
            'widget_name' => $widget_data['name'],
        );

        $data = array_merge($data,$widget_data);
        

        if(class_exists('module_group',false)){
            // get the widget groups
            $g = array();
            if($widget_id>0){
                $widget_data = module_widget::get_widget($widget_id);
                foreach(module_group::get_groups_search(array(
                    'owner_table' => 'widget',
                    'owner_id' => $widget_id,
                )) as $group){
                    $g[$group['group_id']] = $group['name'];
                }
            }
            $data['widget_group'] = implode(', ',$g);
        }

        // addition. find all extra keys for this widget and add them in.
        // we also have to find any EMPTY extra fields, and add those in as well.
        $all_extra_fields = module_extra::get_defaults('widget');
        foreach($all_extra_fields as $e){
            $data[$e['key']] = _l('N/A');
        }
        // and find the ones with values:
        $extras = module_extra::get_extras(array('owner_table'=>'widget','owner_id'=>$widget_id));
        foreach($extras as $e){
            $data[$e['extra_key']] = $e['extra'];
        }

        return $data;
    }


    public function get_install_sql(){
        ob_start();
        ?>

CREATE TABLE `<?php echo _DB_PREFIX; ?>widget` (
  `widget_id` int(11) NOT NULL auto_increment,
  `customer_id` INT(11) NULL,
  `name` varchar(255) NOT NULL DEFAULT  '',
  `status` varchar(255) NOT NULL DEFAULT  '',
  `date_created` date NULL,
  `date_updated` date NULL,
  PRIMARY KEY  (`widget_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    <?php

        return ob_get_clean();
    }

}