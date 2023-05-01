<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Util;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;

class Persona {
	private static $global_cols = [
		"id" => null,
		"id_sap" => null
	];
	public static function list(Req $req) {
		$query = $req->param('query',null);
		if ($query==null) {
			go(true,["list"=>[], "query" => $query]);
		}
		$query = "%{$query}%";
		$lot = DB::lot("SELECT * FROM persona WHERE nombre LIKE ? OR id LIKE ? LIMIT 20",[$query,$query]);
		go(true,["list"=>$lot, "query" => $query]);
	}
	public static function upload(Req $req) {
		$req->file('file')->store('persona/input.csv');
		$path = Storage::path_local('persona/input.csv');

		DB::query("TRUNCATE persona");

		foreach(Util::import_csv($path,self::$global_cols) as $values) {
			$keys = array_keys($values);
			$fields = implode(",",$keys);
			$pfields = implode(",", array_map(function($v){return ":$v";}, $keys) );
			DB::query("INSERT INTO persona ($fields) VALUES ($pfields)",$values);
		}

		go(true,"OK:");
	}
}