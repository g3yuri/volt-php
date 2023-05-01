<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;

class Plano {
	const AREAS = [
		"MIN-ZC" => "MINA CUERPOS",
		"MIN-ZV" => "MINA VETAS",
		"MIN-SE" => "SERVICIOS",
		"MIN-TL" => "TALADRO LARGO",
		"MAN-TR" => "TRACKLESS",
		"MAN-MT" => "MAESTRANZA",
		"MAN-TE" => "TALLER ELECTRICO",
		"MAN-GA" => "GARAGE",
		"MAN-PQ" => "PIQUES Y CHANCADORAS",
		"GEO-SH" => "SHOTCRETE",
		"VENT" => "VENTILACION"
	];
	const SECCIONES = [
		"ZC-INTERMEDIA" => "CUERPOS INTERMEDIA",
		"ZC-BAJA" => "CUERPOS BAJA",
		"ZC-PROFUNDIZACION" => "CUERPOS PROFUNDIZACION",
		"ZV-ALTA" => "VETAS ALTA", //16,17 20A, 21 OROYA
		"ZV-BAJA" => "VETAS BAJA", //18 20B
		"ZV-21-ESPERANZA" => "VETAS 21 ESPERANZA", // 21A Y 21 ESP
		"ZV-20-ESPERANZA" => "VETAS 20 ESPERANZA", // 20A - 20B ESP NELSEN
		"NV-23" => "NIVEL 23", // PROYECTO NIVEL 23
		"MIN-SE" => "SERVICIOS",
		"MIN-TL" => "TALADRO LARGO",
		"MAN-TR" => "TRACKLESS",
		"MAN-MT" => "MAESTRANZA",
		"MAN-TE" => "TALLER ELECTRICO",
		"MAN-GA" => "GARAGE",
		"MAN-PQ" => "PIQUES Y CHANCADORAS",
		"GEO-SH" => "SHOTCRETE",
		"GEO" => "GEOMECANICA",
		"VENT" => "VENTILACION",
		"MA" => "MEDIO AMBIENTE",
		"SSO" => "SEGURIDAD"
	];
	static $filter = [
		"plano" => ["vetas","cuerpos"],
		"sig" => ["msds","panel"],
		"pets" => self::AREAS,
		"estandar" => self::AREAS
	];
	/*
	* este endpoint lo usa la App y Web
	*/
	public static function areas() {
		//go(true,DB::lot());
		go(true,self::AREAS);
	}
	public static function secciones() {
		go(true,self::SECCIONES);
	}
	public static function ob_causas() {
		$list = DB::lot("SELECT * FROM ob_causa");
		go(true,$list);
	}
	public static function persona(Req $req) {
		$in = $req->filter([
			"query" => v::length(3)
		]);
		$list = DB::lot("SELECT id as value, CONCAT(id, ' ', nombre) AS label, nombre FROM persona WHERE nombre LIKE ? LIMIT 20",["%$in->query%"]);
		go(true,[
			"list" => $list
		]);
	}
	public static function go_list(Req $req) {
		$in = $req->filter([
			'base' => v::in(array_keys(self::$filter),true)
		]);
		self::_go_list($in->base);
	}
	public static function _go_list($base) {
		$list = [];
		foreach (array_values(self::$filter[$base]) as $tag) {
			$list[$tag] = DB::lot("SELECT * FROM file WHERE base=? AND tag=?",[$base,$tag]);
			foreach($list[$tag] as &$val) {
				$val['url'] = Storage::url($val['id']);
			}
		}
		go(true,$list);
	}
	public static function drop(Req $req) {
		$drops = [];
		foreach($req->json() as $el) {
			list($base,$tag) = self::_id_normalize($el->id);
			if (!isset($drops[$base]))
				$drops[$base] = array();
			$drops[$base][] = $tag;
			Storage::delete($el->id);
		}
		$one = 'plano';
		foreach($drops as $base => $values) {
			$one = $base;
			foreach (array_unique($values) as $v) {
				self::_realloc($base,$v);
			}
		}
		self::_go_list($one);
	}
	public static function _id_normalize($id) {
		$parts = @explode("/",ltrim($id,'/'));
		if (count($parts)<2) {
			throw new Exception("id de archivo con pocos campos");
		}
		if (!self::_check_base_tag($parts[0],$parts[1])) {
			throw new Exception("No se encuentra la base del id");
		}
		return [$parts[0], $parts[1]];
	}
	public static function _check_base_tag($base,$tag) : bool {
		if (!array_key_exists($base,self::$filter)) {
			return false;
		}
		if (!in_array($tag,array_values(self::$filter[$base]))) {
			//go(false,self::$filter[$base]);
			return false;
		}
		return true;
	}
	public static function realloc() {
		self::_realloc('plano','cuerpos');
		self::_realloc('plano','vetas');
		self::_go_list('plano');
	}
	public static function _realloc($base, $tag) : void {
		DB::query("DELETE FROM  file WHERE base=? AND tag=?",[$base, $tag]);
		$pls = Storage::ls("/$base/$tag");
		foreach($pls as $el) {
			$in = (object)[];
			$in->id = $el->path();
			$in->mimetype = Storage::mimeType($in->id);
			$in->md5 = md5_file(Storage::path_local($in->id));
			$in->size = Storage::fileSize($in->id);
			$in->last_modified = $el->lastModified();
			$in->base = $base;
			$in->tag = $tag;

			DB::query("INSERT INTO file (id, mimetype, md5, size, last_modified, base, tag) VALUES (:id, :mimetype, :md5, :size, :last_modified, :base, :tag)",$in);
		}
	}
	public static function upload(Req $req) {
		$in = $req->filter([
			'base' => v::alpha(' ','-'),
			'tag' => v::alpha(' ','-')
		]);
		if (!self::_check_base_tag($in->base,$in->tag)) {
			http_response_code(500); // esto es para que q-uploader lo interprete como error
			go(false,"base($in->base) y tag($in->tag) no son validos");
		}
		foreach($_FILES as $key => $val) {
			$fs = $req->file($key);
			$fs->store("/$in->base/$in->tag/{$fs->name()}");
		}
		self::_realloc($in->base,$in->tag);
		self::_go_list($in->base);
	}
	public static function sync(Req $req) {
		$in = $req->filter([
			'base' => v::alpha(1)
		]);
		$lot = DB::lot("SELECT * FROM file WHERE base=:base",$in);
		/* AGREGA LOS DATOS EN UN ARRAY CON KEY=ID*/
		$pls = [];
		foreach($lot as $el) {
			$pls[$el['id']] = $el;
		}

		$trash = [];
		foreach($req->json() as $el) {
			if (isset($pls[$el->id]) and $el->md5==$pls[$el->id]['md5']) {
				unset($pls[$el->id]);
			} else {
				$trash[]=$el;
			}
		}
		$files = array_values($pls);
		foreach($files as $k => $val) {
			$files[$k]['url'] = Storage::url($val['id']);
		}
		go(true,[
			"trash" => $trash,
			"files" => $files,
			"tags" => self::$filter[$in->base]
		]);
	}
}