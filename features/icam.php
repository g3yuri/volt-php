<?php
namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;

class Icam {
	public static function list() {
		$lot = DB::lot("SELECT * FROM icam ORDER BY fecha DESC");
		go(true,[
			"lista" => $lot
		]);
	}
	public static function ver(Req $req) {
		$in = $req->filter([
			'id' => v::intVal()
		]);
		$me = DB::me("SELECT * FROM icam WHERE id=:id",$in);
		go(true,$me);
	}
	public static function store(Req $req) {
		$in = $req->filter([
			'fecha' => v::date(),
			'info' => v::length(2)
		]);
		$id = intval($req->param('id',null));
		if ($id) {
			$in->id = $id;
			DB::query("UPDATE icam SET fecha=:fecha, info=:info WHERE id=:id",(array)$in);
		} else {
			DB::query("INSERT INTO icam (fecha, info) VALUES (:fecha,:info)",(array)$in);
			$id = DB::last_insert_id();
		}
		go(true,$id);
	}
}