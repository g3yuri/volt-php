<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;

class Reflex {
	const TABLES = [
		"usuario" => [
			"ID_USUARIO" => "primary",
			"NOMBRES" => "text",
			"APELLIDOS" => "text",
			"EMAIL" => "text",
			"AREA" => "text",
			"URI" => "image",
			"MODIFICADO" => "updated_at"
		]
	];
	public static function schema(Req $req) {
		go(true,self::TABLES);
	}
	public static function sync(Req $req) {
		$in = $req->filter([
			'table' => v::alpha('_','-'),
			'last' => v::dateTime()
		]);
		$table = $req->param("table", null);
		if (!isset(self::TABLES[$table])) {
			go(false, "No existe el schema de ($table)");
		}
		$schema = self::TABLES[$table];
		$last = $req->param('last',null);
		$keys = array_keys($schema);
		$cols = implode(",",$keys);
		$updated_at = array_search("updated_at", $schema);

		$lot = DB::lot("SELECT $cols FROM $table WHERE $updated_at>? LIMIT 100", [$last]);
		$me = DB::me("SELECT COUNT(*) AS CANT FROM $table WHERE $updated_at>?", [$last]);

		go(true,[
			"inserts" => $lot,
			"table" => $table,
			"last" => $last,
			"more" => intval($me["CANT"]) - count($lot)
		]);
	}
}