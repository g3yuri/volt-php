<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Util;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;

class Rrhh {
	private static $global_cols = [
		"id" => null,
		"id_sap" => null,
		"nombre" => null,
		"desde" => "Util::change_date_dmy_ymd",
		"condicion" => null,
		"puesto" => null,
		"area" => null,
		"ceco" => null,
		"fecha_nac" => "Util::change_date_dmy_ymd",
		"cel" => null
	];
	public static function list(Req $req) {
		$list = DB::lot("SELECT a.id,id_empresa,apellidos, nombres, dni, status, edad, cargo, area, categoria, zona, b.empresa FROM postulante AS a LEFT JOIN pos_empresa AS b ON b.id=a.id_empresa");
		go(true,[
			"postulantes" => $list
		]);
	}
	public static function ver(Req $req) {
		$id = $req->param('id',null);
		if ($id==null) go(false,"No se ingreso el id");
		$pos = DB::me("SELECT a.id,id_empresa,apellidos, nombres, dni, edad, cargo, area, categoria, zona, b.empresa, status, info, uri_foto, uri_policial, uri_penal, uri_emo FROM postulante AS a LEFT JOIN pos_empresa AS b ON b.id=a.id_empresa WHERE a.id=?",[$id]);
		$pos['uri_foto'] = Storage::url($pos['uri_foto']);
		$pos['uri_policial'] = Storage::url($pos['uri_policial']);
		$pos['uri_penal'] = Storage::url($pos['uri_penal']);
		$pos['uri_emo'] = Storage::url($pos['uri_emo']);
		$exp = DB::lot("SELECT * FROM pos_experiencia WHERE pos_id=?",[$id]);
		go(true,[
			"postulante" => $pos,
			"experiencia" => $exp
		]);
	}
	public static function empresas(Req $req) {
		go(true,[
			"empresas" => DB::lot("SELECT id, empresa FROM pos_empresa")
		]);
	}
	public static function experiencia(Req $req) {
		$in = $req->filter([
			'desde' => v::alnum('/'),
			'hasta' => v::alnum('/'),
			'cargo' => v::alnum(' '),
			'empresa' => v::alnum(' '),
			'pos_id' => v::intVal(),
			'id' => v::optional(v::intVal())
		]);
		if (isset($in->id)){
			DB::query("UPDATE pos_experiencia SET pos_id=:pos_id, desde=:desde, hasta=:hasta, cargo=:cargo, empresa=:empresa WHERE id=:id",$in);
		} else {
			unset($in->id);
			DB::query("INSERT INTO pos_experiencia (pos_id, desde, hasta, cargo, empresa)
				VALUES (:pos_id, :desde, :hasta, :cargo, :empresa)",$in);
		}

		$exp = DB::lot("SELECT * FROM pos_experiencia WHERE pos_id=?",[$in->pos_id]);
		go(true,[
			"experiencia" => $exp
		]);
	}
	public static function del_exp(Req $req) {
		$in = $req->filter([
			'id' => v::intVal(),
			'pos_id' => v::intVal()
		]);
		DB::query("DELETE FROM pos_experiencia WHERE id=?",[$in->id]);

		$exp = DB::lot("SELECT * FROM pos_experiencia WHERE pos_id=?",[$in->pos_id]);
		go(true,[
			"experiencia" => $exp
		]);
	}
	public static function revision(Req $req) {
		$in = $req->filter([
			'info' => v::length(2),
			'status' => v::in(['APROBADO','RECHAZADO','OBSERVADO']),
			'id' => v::intVal()
		]);
		$us = Postulante::find($in->id);
		$us->info = $in->info;
		$us->status = $in->status;
		$us->save();
		go(true,$us->id());
	}
	public static function upload_adjunto(Req $req) {
		$in = $req->filter([
			'pos_id' => v::intVal(),
			'prefix' => v::in(['foto','policial','penal','emo'])
		]);
		$fu = $req->file('file');
		$uid = uniqid();
		$path = "pos/$in->pos_id/$in->prefix-$uid";
		$fu->store($path);
		$us = DB::me("SELECT * FROM postulante WHERE id=?",[$in->pos_id]);
		$label = 'uri_'.$in->prefix;
		if ($us[$label]) {
			Storage::delete($us[$label]);
		}
		DB::query("UPDATE postulante SET $label=?",[$path]);

		$pos = DB::me("SELECT * FROM postulante WHERE id=?",[$in->pos_id]);
		$pos[$label] = Storage::url($pos[$label]);
		go(true,[
			"postulante" => $pos
		]);
	}
	public static function store(Req $req) {
		$in = $req->filter([
			'id_empresa' => v::intVal(),
			'apellidos' => v::alnum(' '),
			'nombres' => v::alnum(' '),
			'dni' => v::alnum(' '),
			'edad' => v::alnum(' '),
			'cargo' => v::alnum(' '),
			'area' => v::alnum(' '),
			'categoria' => v::alnum(' '),
			'zona' => v::alnum(' ')
		]);
		$id = intval($req->param('id',null));
		if ($id) {
			$in->id = $id;
			DB::query("UPDATE postulante SET id_empresa=:id_empresa, apellidos=:apellidos,
				nombres=:nombres, dni=:dni, edad=:edad, cargo=:cargo, area=:area,
				categoria=:categoria, zona=:zona WHERE id=:id",(array)$in);
		} else {
			DB::query("INSERT INTO postulante (id_empresa,apellidos, nombres, dni, edad, cargo, area, categoria, zona)
				VALUES (:id_empresa, :apellidos,:nombres,:dni,:edad,:cargo,:area,:categoria,:zona)",(array)$in);
			$id = DB::last_insert_id();
		}
		go(true,$id);
	}


	public static function subir(Req $req) {
		$req->file('file')->store('persona/input.csv');
		$path = Storage::path_local('persona/input.csv');

		DB::query("TRUNCATE persona");

		foreach(Util::import_csv($path,self::$global_cols,";") as $values) {
			$keys = array_keys($values);
			$fields = implode(",",$keys);
			$pfields = implode(",", array_map(function($v){return ":$v";}, $keys) );
			try{
				DB::query("INSERT INTO persona ($fields) VALUES ($pfields)",$values);
			} catch (Exception $e) {
				go(false,$values);
			}
		}

		go(true,"OK:");
	}
	public static function subir_anterior(Req $req) {
		go(false,"Aqui nomas");
		$req->file('file')->store('rrhh/personal_db.csv');
		$path = Storage::path_local('rrhh/personal_db.csv');
		go(false,$path);
		
		DB::query("TRUNCATE personal");
		DB::query("LOAD DATA LOCAL INFILE '$path' INTO TABLE personal
					FIELDS TERMINATED BY ';'
					LINES TERMINATED BY '\n'
					IGNORE 1 ROWS 
					(empresa,n_pers,id,nombre,@desde,tipo,puesto,area,ceco,centro_costo,
					situacion,cel1,cel2,cel3,cel4,@fnac,recojo,sindicato,cumple,correo,
					guardia,estado_civil,clinica,turno,genero)
					SET @fnac = STR_TO_DATE(@fnac,'%d/%m/%y'),
					@desde = STR_TO_DATE(@desde,'%d/%m/%y')");

		go(true,"OK:");
	}
}