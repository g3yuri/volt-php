<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Util;
use Respect\Validation\Validator as v;
use \Lib\Storage;

class Petar {
	public static function list(Req $req) {
		$list = DB::lot("SELECT * FROM petar LIMIT 20");
		go(true,[
			"list" => $list
		]);
	}
	public static function store(Req $req) {
		$in = $req->filter([
			"id" => v::optional(v::digit()),
			"fecha" => v::date(),
			"area" => v::alnum(),
			"actividad" => v::length(2),
			"nivel" => v::optional(v::length(1)),
			"labor" => v::length(1),
			"sup_id" => v::digit(),
			"sup_nombre" => v::length(3),
			"jefe_id" => v::digit(),
			"jefe_nombre" => v::length(3),
			"turno" => v::in(['dia','noche']),
			"zona" => v::in(['cuerpos','vetas', "superficie"])
		]);
		$in->area = $req->user()->area;
		$in->fecha = date("Y-m-d");
		if ($in->id) {
			DB::query("UPDATE petar SET fecha=:fecha, area=:area, actividad=:actividad, nivel=:nivel, labor=:labor, sup_id=:sup_id, sup_nombre=:sup_nombre, jefe_id=:jefe_id, jefe_nombre=:jefe_nombre, turno=:turno, zona=:zona WHERE id=:id", $in);
		} else {
			unset($in->id);
			DB::query("INSERT INTO petar (fecha, area, actividad, nivel, labor, sup_id, sup_nombre, jefe_id, jefe_nombre, turno, zona)
					VALUES (:fecha, :area, :actividad, :nivel, :labor, :sup_id, :sup_nombre, :jefe_id, :jefe_nombre, :turno, :zona)", $in);
			$in->id = DB::last_insert_id();
		}
		$me = DB::me("SELECT * FROM petar WHERE id=?",[$in->id]);
		go(true,$me);
	}
}