<?php

if(!defined("MCR")){ exit("Hacking Attempt!"); }

class submodule{
	private $core, $db, $config, $user, $lng;

	public function __construct($core){
		$this->core		= $core;
		$this->db		= $core->db;
		$this->config	= $core->config;
		$this->user		= $core->user;
		$this->lng		= $core->lng_m;

		if(!$this->core->is_access('sys_adm_groups')){ $this->core->notify($this->core->lng['403'], $this->core->lng['e_403']); }

		$bc = array(
			$this->lng['mod_name'] => BASE_URL."?mode=admin",
			$this->lng['groups'] => BASE_URL."?mode=admin&do=groups"
		);

		$this->core->bc = $this->core->gen_bc($bc);
	}

	private function group_array(){

		$start		= $this->core->pagination($this->config->pagin['adm_groups'], 0, 0); // Set start pagination
		$end		= $this->config->pagin['adm_groups']; // Set end pagination

		$query = $this->db->query("SELECT id, title, description
									FROM `mcr_groups`
									ORDER BY id DESC
									LIMIT $start, $end");

		if(!$query || $this->db->num_rows($query)<=0){ return $this->core->sp(MCR_THEME_MOD."admin/groups/group-none.html"); }

		ob_start();

		while($ar = $this->db->fetch_assoc($query)){

			$page_data = array(
				"ID" => intval($ar['id']),
				"TITLE" => $this->db->HSC($ar['title']),
				"TEXT" => $this->db->HSC($ar['description']),
			);
		
			echo $this->core->sp(MCR_THEME_MOD."admin/groups/group-id.html", $page_data);
		}

		return ob_get_clean();
	}

	private function group_list(){

		$query = $this->db->query("SELECT COUNT(*) FROM `mcr_groups`");

		if(!$query){ exit("SQL Error"); }

		$ar = $this->db->fetch_array($query);

		$data = array(
			"PAGINATION" => $this->core->pagination($this->config->pagin['adm_groups'], "?mode=admin&do=groups&pid=", $ar[0]),
			"GROUPS" => $this->group_array()
		);

		return $this->core->sp(MCR_THEME_MOD."admin/groups/group-list.html", $data);
	}

	private function delete(){
		if(!$this->core->is_access('sys_adm_groups_delete')){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng['e_403'], 2, '?mode=admin&do=groups'); }

		if($_SERVER['REQUEST_METHOD']!='POST'){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng['e_hack'], 2, '?mode=admin&do=groups'); }
			
		$list = @$_POST['id'];

		if(empty($list)){ $this->core->notify($this->core->lng["e_msg"], $this->lng['grp_not_selected'], 2, '?mode=admin&do=groups'); }

		$list = $this->core->filter_int_array($list);

		$list = array_unique($list);

		$list = $this->db->safesql(implode(", ", $list));

		if(!$this->db->remove_fast("mcr_groups", "id IN ($list)")){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=groups'); }

		$count = $this->db->affected_rows();

		if(!$this->db->remove_fast("mcr_users", "gid IN ($list)")){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=groups'); }

		$count1 = $this->db->affected_rows();

		// Последнее обновление пользователя
		$this->db->update_user($this->user);

		// Лог действия
		$this->db->actlog($this->lng['log_del_grp']." $list ".$this->lng['log_grp'], $this->user->id);

		$this->core->notify($this->core->lng["e_success"], $this->lng['grp_del_msg1']." $count, ".$this->lng['grp_del_msg2']." $count1", 3, '?mode=admin&do=groups');

	}

	private function get_default_value($name='false', $value, $type='boolean'){
		switch($type){
			case 'integer':
				$value = intval($value);
				$input = '<input type="text" class="span8" name="'.$name.'" value="'.$value.'" id="inputDefault" placeholder="'.$this->lng['grp_def_val'].'">';
			break;

			case 'float':
				$value = floatval($value);
				$input = '<input type="text" class="span8" name="'.$name.'" value="'.$value.'" id="inputDefault" placeholder="'.$this->lng['grp_def_val'].'">';
			break;

			case 'string':
				$value = $this->db->HSC($value);
				$input = '<input type="text" class="span8" name="'.$name.'" value="'.$value.'" id="inputDefault" placeholder="'.$this->lng['grp_def_val'].'">';
			break;

			default:
				$select = ($value=='true') ? 'selected' : '';
				$input = '<select name="'.$name.'" class="span8"><option value="false">FALSE</option><option value="true" '.$select.'>TRUE</option></select>';
			break;
		}


		return $input;
	}

	private function perm_list($perm=''){
		$query = $this->db->query("SELECT title, `value`, `default`, `type` FROM `mcr_permissions`");
		if(!$query || $this->db->num_rows($query)<=0){ return; }

		if(!empty($perm)){ $json = json_decode($perm, true); }

		ob_start();

		while($ar = $this->db->fetch_assoc($query)){
			$data["TITLE"] = $this->db->HSC($ar['title']);
			$data["VALUE"] = $this->db->HSC($ar['value']);

			$data['DEFAULT'] = @$this->get_default_value($ar['value'], $json[$ar['value']], $ar['type']);

			echo $this->core->sp(MCR_THEME_MOD."admin/groups/perm-id.html", $data);
		}

		return ob_get_clean();
	}

	private function gen_permissions($data){
		if(empty($data)){ exit("System permissions error"); }

		foreach($data as $key => $value){
			if($value=='true' || $value=='false'){
				$data[$key] = ($value=='true') ? true : false;
			}else{
				$data[$key] = intval($value);
			}
		}

		return json_encode($data);
	}

	private function add(){
		if(!$this->core->is_access('sys_adm_groups_add')){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng['e_403'], 2, '?mode=admin&do=groups'); }

		$bc = array(
			$this->lng['mod_name'] => BASE_URL."?mode=admin",
			$this->lng['groups'] => BASE_URL."?mode=admin&do=groups",
			$this->lng['grp_add'] => BASE_URL."?mode=admin&do=groups&op=add",
		);

		$this->core->bc = $this->core->gen_bc($bc);

		if($_SERVER['REQUEST_METHOD']=='POST'){
			$title			= $this->db->safesql(@$_POST['title']);
			$text			= $this->db->safesql(@$_POST['text']);
			$permissions	= $this->db->safesql(@$_POST['permissions']);

			$perm_data = $_POST;

			unset($perm_data['submit'], $perm_data['mcr_secure'], $perm_data['title'], $perm_data['text']);

			$new_permissions = $this->db->safesql($this->gen_permissions($perm_data));

			$insert = $this->db->query("INSERT INTO `mcr_groups`
											(title, description, `permissions`)
										VALUES
											('$title', '$text', '$new_permissions')");

			if(!$insert){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=groups'); }

			$id = $this->db->insert_id();

			// Последнее обновление пользователя
			$this->db->update_user($this->user);

			// Лог действия
			$this->db->actlog($this->lng['log_add_grp']." #$id ".$this->lng['log_grp'], $this->user->id);
			
			$this->core->notify($this->core->lng["e_success"], $this->lng['grp_del_success'], 3, '?mode=admin&do=groups');
		}

		$data = array(
			"PAGE" => $this->lng['grp_add_page_name'],
			"TITLE" => '',
			"TEXT" => '',
			"PERMISSIONS" => $this->perm_list(),
			"BUTTON" => $this->lng['grp_add_btn']
		);

		return $this->core->sp(MCR_THEME_MOD."admin/groups/group-add.html", $data);
	}

	private function edit(){
		if(!$this->core->is_access('sys_adm_groups_edit')){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng['e_403'], 2, '?mode=admin&do=groups'); }

		$id = intval($_GET['id']);

		$query = $this->db->query("SELECT title, `description`, `permissions`
									FROM `mcr_groups`
									WHERE id='$id'");

		if(!$query || $this->db->num_rows($query)<=0){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=groups'); }

		$ar = $this->db->fetch_assoc($query);

		$bc = array(
			$this->lng['mod_name'] => BASE_URL."?mode=admin",
			$this->lng['groups'] => BASE_URL."?mode=admin&do=groups",
			$this->lng['grp_edit'] => BASE_URL."?mode=admin&do=groups&op=edit&id=$id",
		);

		$this->core->bc = $this->core->gen_bc($bc);

		if($_SERVER['REQUEST_METHOD']=='POST'){
			$title			= $this->db->safesql(@$_POST['title']);
			$text			= $this->db->safesql(@$_POST['text']);
			$permissions	= $this->db->safesql(@$_POST['permissions']);

			$perm_data = $_POST;

			unset($perm_data['submit'], $perm_data['mcr_secure'], $perm_data['title'], $perm_data['text']);

			$new_permissions = $this->db->safesql($this->gen_permissions($perm_data));

			$update = $this->db->query("UPDATE `mcr_groups`
										SET title='$title', description='$text', `permissions`='$new_permissions'
										WHERE id='$id'");

			if(!$update){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=groups&op=edit&id='.$id); }

			// Последнее обновление пользователя
			$this->db->update_user($this->user);

			// Лог действия
			$this->db->actlog($this->lng['log_edit_grp']." #$id ".$this->lng['log_grp'], $this->user->id);
			
			$this->core->notify($this->core->lng["e_success"], $this->lng['grp_edit_success'], 3, '?mode=admin&do=groups&op=edit&id='.$id);
		}

		$data = array(
			"PAGE"			=> $this->lng['grp_edit_page_name'],
			"TITLE"			=> $this->db->HSC($ar['title']),
			"TEXT"			=> $this->db->HSC($ar['description']),
			"PERMISSIONS"	=> $this->perm_list($ar['permissions']),
			"BUTTON"		=> $this->lng['grp_edit_btn']
		);

		return $this->core->sp(MCR_THEME_MOD."admin/groups/group-add.html", $data);
	}

	public function content(){

		$op = (isset($_GET['op'])) ? $_GET['op'] : 'list';

		switch($op){
			case 'add':		$content = $this->add(); break;
			case 'edit':	$content = $this->edit(); break;
			case 'delete':	$this->delete(); break;

			default:		$content = $this->group_list(); break;
		}

		return $content;
	}
}

?>