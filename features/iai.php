<?php
namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;

class Iai {
	static private $tipos = [
		"leve" => "Accidente Leve",
		"incapacitante" => "Accidente Incapacitante",
		"propiedad" => "Accidente con daño a la propiedad",
		"incidente" => "Incidente"
	];
	static private $danios = [
		"persona" => "Persona",
		"equipo" => "Equipo",
		"proceso" => "Proceso",
		"ambiente" => "Medio Ambiente"
	];
	static private $potencial = ["alto", "medio", "bajo"];
	static private $areas = [
		"MINA" => [
			"intermedia" => "Cuerpos Intermedia",
			"baja" => "Cuerpos Baja",
			"profundizacion" => "Cuerpos Profundización",
			"nivel-21" => "Vetas Nivel 21",
			"oroya-alta" => "Vetas Oroya Alta",
			"oroya-baja" => "Vetas Oroya Baja",
			"taladro-largo" => "Taladro Largo",
			"servicios" => "Servicios Mina",
			"productividad" => "Productividad"
		],
		"RRHH" => [
			"rrhh" => "Recursos Humanos",
			"bbss" => "Bienestar Social"
		],
		"MANTENIMIENTO" => [
			"garage" => "Garage",
			"maestranza" => "Maestranza",
			"electrico" => "Taller Electrico",
			"trackles" => "Trackles",
			"piques" => "Piques y Chancadora"
		],
		"SEGURIDAD" => [
			"ventilacion" => "Ventilacion",
			"geomecanica" => "Geomecanica",
			"seguridad" => "Seguridad",
			"mambiente" => "Medio Ambiente"
		],
		"ALMACEN" => ["almacen" => "Almacen"]
		
	];
	public static function list_ultimos() {
		$lot = DB::lot("SELECT *, DATE(fecha) AS fecha_corta FROM accidente ORDER BY fecha DESC LIMIT 10");
		foreach ($lot as &$el) {
			if ($el['rel']) {
				$el['rel'] = Storage::url($el['rel']);
			}
		}
		go(true,[ "list" => $lot ]);
	}
	public static function list_mes() {
		$lot = DB::lot("
					SELECT COUNT(id) AS cant, MONTH(fecha) AS mes, danio 
					FROM accidente 
					WHERE YEAR(fecha)=YEAR(NOW()) 
					GROUP BY MONTH(fecha), danio");
		go(true,[ "list" => $lot ]);
	}
	public static function list() {
		$lot = DB::lot("SELECT *, DATE(fecha) AS fecha_corta FROM accidente ORDER BY fecha DESC");
		foreach ($lot as &$el) {
			if ($el['rel']) {
				$el['rel'] = Storage::url($el['rel']);
			}
		}
		go(true,[
			"accidentes" => $lot
		]);
	}
	public static function helper() {
		go(true,[
			"tipos" => self::$tipos,
			"danios" => self::$danios,
			"potencial" => self::$potencial,
			"areas" => self::$areas
		]);
	}
	public static function ver(Req $req) {
		$in = $req->filter([
			'id' => v::intVal()
		]);
		$me = DB::me("SELECT * FROM accidente WHERE id=:id",$in);
		go(true,$me);
	}
	public static function store(Req $req) {
		$in = $req->filter([
			'tipo' => v::in(array_keys(self::$tipos),true),
			'danio' => v::in(array_keys(self::$danios),true),
			'descripcion' => v::length(2),
			'diagnostico' => v::length(2),
			'potencial' => v::in(self::$potencial,true),
			'fecha' => v::dateTime('Y-m-d H:i:s'),
			'lugar' => v::length(2),
			'accidentado' => v::length(2),
			'acc_dni' => v::length(8,8),
			'comentario' => v::optional(v::length(2)),
			'area' => v::length(2)
		]);
		$id = intval($req->param('id',null));
		if ($id) {
			$in->id = $id;
			DB::query("UPDATE accidente SET tipo=:tipo, danio=:danio, descripcion=:descripcion, diagnostico=:diagnostico, potencial=:potencial, fecha=:fecha, lugar=:lugar, accidentado=:accidentado, acc_dni=:acc_dni, comentario=:comentario, area=:area WHERE id=:id",(array)$in);
		} else {
			DB::query("INSERT INTO accidente (tipo, danio, descripcion, diagnostico, potencial, fecha, lugar, accidentado, acc_dni, comentario, area) VALUES (:tipo, :danio, :descripcion, :diagnostico, :potencial, :fecha, :lugar, :accidentado, :acc_dni, :comentario, :area)",(array)$in);
			$id = DB::last_insert_id();
		}

		$fs = $req->file("file");
		if ($fs) {
			$rel = "/iai/$id";
			$fs->store($rel);
			DB::query("UPDATE accidente SET rel=? WHERE id=?",[$rel,$id]);
		}
		go(true,$id);
	}
}