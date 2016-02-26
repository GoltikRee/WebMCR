<?php

if(!defined("MCR")){ exit("Hacking Attempt!"); }

class McAPIcaPing{

	// Get server information from https://mcapi.ca/

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
		$this->ip = $ip;
		$this->port = $port;

		$json = file_get_contents("https://mcapi.ca/query/$ip:$port/info");

		if($json===false){ $this->error = var_export($json, true); return false; }

		$array = @json_decode($json, true);

		if(isset($array['error'])){ $this->error = $array['error']; return false; }

		$this->status = 1;

		$this->version = @$array['version'];

		$this->motd = @$array['motd'];

		$this->online = intval(@$array['players']['online']);

		$this->slots = intval(@$array['players']['max']);

		return true;
	}
}

?>