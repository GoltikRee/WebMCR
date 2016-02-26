<?php

if(!defined("MCR")){ exit("Hacking Attempt!"); }

class submodule{
	private $core, $db, $config, $user, $lng;

	public function __construct($core){
		$this->core = $core;
		$this->db	= $core->db;
		$this->config = $core->config;
		$this->user	= $core->user;
		$this->lng	= $core->lng_m;

		if(!$this->core->is_access('sys_adm_menu')){ $this->core->notify($this->core->lng['403'], $this->core->lng['e_403']); }

		$bc = array(
			$this->lng['mod_name'] => BASE_URL."?mode=admin",
			$this->lng['menu'] => BASE_URL."?mode=admin&do=menu"
		);

		$this->core->bc = $this->core->gen_bc($bc);
	}

	private function menu_array(){

		$start		= $this->core->pagination($this->config->pagin['adm_menu'], 0, 0); // Set start pagination
		$end		= $this->config->pagin['adm_menu']; // Set end pagination

		$query = $this->db->query("SELECT `m`.id, `m`.title, `m`.`parent`, `m`.`url`, `m`.`target`, `p`.title AS `ptitle`
									FROM `mcr_menu` AS `m`
									LEFT JOIN `mcr_menu` AS `p`
										ON `p`.id=`m`.`parent`
									ORDER BY `m`.id ASC
									LIMIT $start, $end");

		if(!$query || $this->db->num_rows($query)<=0){ return $this->core->sp(MCR_THEME_MOD."admin/menu/menu-none.html"); }

		ob_start();

		while($ar = $this->db->fetch_assoc($query)){

			$parent = (intval($ar['parent'])===0) ? $this->lng['menu_top_lvl'] : $this->db->HSC($ar['ptitle']);

			$page_data = array(
				"ID" => intval($ar['id']),
				"TITLE" => $this->db->HSC($ar['title']),
				"PID" => intval($ar['parent']),
				"URL" => $this->db->HSC($ar['url']),
				"TARGET" => $this->db->HSC($ar['target']),
				"PARENT" => $parent
			);
		
			echo $this->core->sp(MCR_THEME_MOD."admin/menu/menu-id.html", $page_data);
		}

		return ob_get_clean();
	}

	private function menu_list(){

		$query = $this->db->query("SELECT COUNT(*) FROM `mcr_menu`");

		if(!$query){ exit("SQL Error"); }

		$ar = $this->db->fetch_array($query);

		$data = array(
			"PAGINATION" => $this->core->pagination($this->config->pagin['adm_menu'], "?mode=admin&do=menu&pid=", $ar[0]),
			"MENU" => $this->menu_array()
		);

		return $this->core->sp(MCR_THEME_MOD."admin/menu/menu-list.html", $data);
	}

	private function delete(){
		if(!$this->core->is_access('sys_adm_menu_delete')){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng['e_403'], 2, '?mode=admin&do=menu'); }

		if($_SERVER['REQUEST_METHOD']!='POST'){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng['e_hack'], 2, '?mode=admin&do=menu'); }
			
		$list = @$_POST['id'];

		if(empty($list)){ $this->core->notify($this->core->lng["e_msg"], $this->lng['menu_not_selected'], 2, '?mode=admin&do=menu'); }

		$list = $this->core->filter_int_array($list);

		$list = array_unique($list);

		$list = $this->db->safesql(implode(", ", $list));

		if(!$this->db->remove_fast("mcr_menu", "id IN ($list) AND `parent` IN ($list)")){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=menu'); }

		$count = $this->db->affected_rows();

		// Последнее обновление пользователя
		$this->db->update_user($this->user);

		// Лог действия
		$this->db->actlog($this->lng['log_del_menu']." $list ".$this->lng['log_menu'], $this->user->id);

		$this->core->notify($this->core->lng["e_success"], $this->lng['menu_del_elements']." $count", 3, '?mode=admin&do=menu');

	}

	private function add(){
		if(!$this->core->is_access('sys_adm_menu_add')){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng['e_403'], 2, '?mode=admin&do=menu'); }

		$bc = array(
			$this->lng['mod_name'] => BASE_URL."?mode=admin",
			$this->lng['menu'] => BASE_URL."?mode=admin&do=menu",
			$this->lng['menu_add'] => BASE_URL."?mode=admin&do=menu&op=add",
		);

		$this->core->bc = $this->core->gen_bc($bc);

		if($_SERVER['REQUEST_METHOD']=='POST'){
			$title			= $this->db->safesql(@$_POST['title']);
			$url			= $this->db->safesql(@$_POST['url']);
			$parent			= intval(@$_POST['parent']);
			$target			= (@$_POST['target']=="_blank") ? "_blank" : "_self";
			$permissions	= $this->db->safesql(@$_POST['permissions']);

			if(!$this->core->validate_perm($permissions)){ $this->core->notify($this->core->lng["e_msg"], $this->lng['menu_perm_not_exist'], 2, '?mode=admin&do=menu'); }

			$insert = $this->db->query("INSERT INTO `mcr_menu`
											(title, `parent`, `url`, `target`, `permissions`)
										VALUES
											('$title', '$parent', '$url', '$target', '$permissions')");

			if(!$insert){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=menu'); }

			$id = $this->db->insert_id();

			// Последнее обновление пользователя
			$this->db->update_user($this->user);

			// Лог действия
			$this->db->actlog($this->lng['log_add_menu']." #$id ".$this->lng['log_menu'], $this->user->id);
			
			$this->core->notify($this->core->lng["e_success"], $this->lng['menu_add_success'], 3, '?mode=admin&do=menu');
		}

		$data = array(
			"PAGE" => $this->lng['menu_add_page_name'],
			"TITLE" => '',
			"URL" => '',
			"PERMISSIONS" => $this->core->perm_list(),
			"PARENTS" => $this->parents(),
			"TARGET" => '',
			"BUTTON" => $this->lng['menu_add_btn']
		);

		return $this->core->sp(MCR_THEME_MOD."admin/menu/menu-add.html", $data);
	}

	private function edit(){
		if(!$this->core->is_access('sys_adm_menu_edit')){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng['e_403'], 2, '?mode=admin&do=menu'); }

		$id = intval($_GET['id']);

		$query = $this->db->query("SELECT title, `parent`, `url`, `target`, permissions
									FROM `mcr_menu`
									WHERE id='$id'");

		if(!$query || $this->db->num_rows($query)<=0){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=menu'); }

		$ar = $this->db->fetch_assoc($query);

		$bc = array(
			$this->lng['mod_name'] => BASE_URL."?mode=admin",
			$this->lng['menu'] => BASE_URL."?mode=admin&do=menu",
			$this->lng['menu_edit'] => BASE_URL."?mode=admin&do=menu&op=edit&id=$id",
		);

		$this->core->bc = $this->core->gen_bc($bc);

		if($_SERVER['REQUEST_METHOD']=='POST'){
			$title			= $this->db->safesql(@$_POST['title']);
			$url			= $this->db->safesql(@$_POST['url']);
			$parent			= intval(@$_POST['parent']);
			$target			= (@$_POST['target']=="_blank") ? "_blank" : "_self";
			$permissions	= $this->db->safesql(@$_POST['permissions']);

			if(!$this->core->validate_perm($permissions)){ $this->core->notify($this->core->lng["e_msg"], $this->lng['menu_perm_not_exist'], 2, '?mode=admin&do=menu'); }


			$update = $this->db->query("UPDATE `mcr_menu`
										SET title='$title', `parent`='$parent', `url`='$url', `target`='$target', `permissions`='$permissions'
										WHERE id='$id'");

			if(!$update){ $this->core->notify($this->core->lng["e_msg"], $this->core->lng["e_sql_critical"], 2, '?mode=admin&do=menu&op=edit&id='.$id); }

			// Последнее обновление пользователя
			$this->db->update_user($this->user);

			// Лог действия
			$this->db->actlog($this->lng['log_edit_menu']." #$id ".$this->lng['log_menu'], $this->user->id);
			
			$this->core->notify($this->core->lng["e_success"], $this->lng['menu_edit_success'], 3, '?mode=admin&do=menu&op=edit&id='.$id);
		}

		$data = array(
			"PAGE" => $this->lng['menu_edit_page_name'],
			"TITLE" => $this->db->HSC($ar['title']),
			"URL" => $this->db->HSC($ar['url']),
			"PERMISSIONS" => $this->core->perm_list($ar['permissions']),
			"PARENTS" => $this->parents($ar['parent'], $id),
			"TARGET" => ($ar['target']=='_blank') ? 'selected' : '',
			"BUTTON" => $this->lng['menu_edit_btn']
		);

		return $this->core->sp(MCR_THEME_MOD."admin/menu/menu-add.html", $data);
	}

	private function parents($select=0, $not=false){

		$select = intval($select);

		$not = ($not===false) ? -1 : intval($not);

		$query = $this->db->query("SELECT id, title
									FROM `mcr_menu`
									WHERE id!='$not'
									ORDER BY title ASC");

		ob_start();

		$selected = ($select===0) ? 'selected' : '';
		
		echo '<option value="0" '.$selected.'>'.$this->lng['menu_top_lvl'].'</option>';

		if(!$query || $this->db->num_rows($query)<=0){ return ob_get_clean(); }

		while($ar = $this->db->fetch_assoc($query)){
			$id = intval($ar['id']);
			$selected = ($id == $select) ? "selected" : "";

			$title = $this->db->HSC($ar['title']);

			echo "<option value=\"$id\" $selected>$title</option>";
		}

		return ob_get_clean();
	}

	public function content(){

		$op = (isset($_GET['op'])) ? $_GET['op'] : 'list';

		switch($op){
			case 'add':		$content = $this->add(); break;
			case 'edit':	$content = $this->edit(); break;
			case 'delete':	$this->delete(); break;

			default:		$content = $this->menu_list(); break;
		}

		return $content;
	}
}

?>