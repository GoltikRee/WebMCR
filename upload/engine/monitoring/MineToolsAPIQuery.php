<?php

if(!defined("MCR")){ exit("Hacking Attempt!"); }

class MineToolsAPIQuery{

	// Set default values
	public $ip			= '127.0.0.1';
	public $port		= 25565;

	public $status		= 0; // server status
	public $version		= '';
	public $online		= 0;
	public $slots		= 0;
	public $players		= '';
	public $motd		= '';
	public $plugins		= '';
	public $map			= '';
	public $error		= '';

	public function connect($ip='127.0.0.1', $port=25565){
		$this->status = $this->online = $this->slots = 0;
		$this->version = $this->players = $this->motd = $this->plugins = $this->map = $this->error = '';
		
		$this->ip = $ip;
		$this->port = $port;

		$json = file_get_contents("http://www.api.minetools.eu/query/$ip/$port");

		if($json===false){ $this->error = var_export($json, true); return false; }

		$array = @json_decode($json, true);

		if(isset($array['error'])){ $this->error = $array['error']; return false; }

		$this->status = 1;

		$this->version = @$array['Version'];

		$this->online = intval(@$array['Players']);

		$this->slots = intval(@$array['MaxPlayers']);

		$this->players = implode(', ', @$array['Playerlist']);

		$this->plugins = @implode(', ', @$array['Plugins']);

		$this->map = @$array['Map'];

		return true;
	}
}

?>