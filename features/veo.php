<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Util;
use Respect\Validation\Validator as v;
use \Lib\Storage;

class Veo {
	public static function sso_list(Req $req) {
		$in = $req->filter([
			"zona" => v::in(['cuerpos', 'vetas', 'superficie'])
		]);
		$avance = DB::lot("SELECT * FROM veo_item WHERE tipo='avance' AND zona=:zona LIMIT 20", $in);
		$tajo = DB::lot("SELECT * FROM veo_item WHERE tipo='tajo' AND zona=:zona LIMIT 20", $in);
		go(true,[
			"avance" => $avance,
			"tajo" => $tajo
		]);
	}
	public static function sso_store(Req $req) {
		$in = $req->filter([
			"id" => v::optional(v::digit()),
			"nivel" => v::optional(v::length(1)),
			"labor" => v::length(1),
			"zona" => v::in(['cuerpos','vetas', "superficie"]),
			"tipo" => v::in(['avance','tajo'])
		]);
		$in->area = $req->user()->area;
		if ($in->id) {
			DB::query("UPDATE veo_item SET area=:area, nivel=:nivel, labor=:labor, zona=:zona, tipo=:tipo WHERE id=:id", $in);
		} else {
			unset($in->id);
			DB::query("INSERT INTO veo_item (area, nivel, labor, zona, tipo) VALUES (:area, :nivel, :labor, :zona, :tipo)", $in);
			$in->id = DB::last_insert_id();
		}
		$me = DB::me("SELECT * FROM veo_item WHERE id=?",[$in->id]);
		go(true,$me);
	}
}