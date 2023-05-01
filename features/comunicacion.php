<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Drawing;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

class Comunicacion {
	public static function install(Req $req) {
		$file = $req->file("file");
		if ($file) {
			$file->store("masst/input.xlsx");
			go(true,"Se instalo");
		}
		go(false,"No se instalo");
	}
	public static function go_list() {
		$list = DB::lot("SELECT * FROM observacion WHERE 1 AND accion IS NULL");
		foreach($list as &$val) {
			$val['img'] = Storage::url($val['rel']);
		}
		go(true,$list);
	}
	public static function ppt(Req $req) {
		$in = $req->json();
		$user = Req::user();
		$area = $user->area;
		$inputFileName = Storage::path_local("ppt/input.pptx");
		//$presentation = \PhpOffice\PhpPresentation\IOFactory::load($inputFileName);
		$presentation = new PhpPresentation();
		$slide = $presentation->getActiveSlide();

		$ids = implode(",", array_map('intval', $in->ids));
		$lot = DB::lot("SELECT * FROM observacion WHERE id IN ($ids) ORDER BY fecha DESC",[]);

		foreach($lot as $ref) {
			$ob = (object)$ref;
			$slide = $presentation->createSlide();

			$shape = $slide->createRichTextShape()
	      ->setHeight(300)
	      ->setWidth(600)
	      ->setOffsetX(10)
	      ->setOffsetY(10);
			$shape->getActiveParagraph()->getAlignment()->setHorizontal( \PhpOffice\PhpPresentation\Style\Alignment::HORIZONTAL_LEFT );
			$textRun = $shape->createTextRun('OBSERVACIONES');
			$textRun->getFont()->setBold(true)
			                   ->setSize(30)
			                   ->setColor( new Color( 'FF006666' ) );

			$shape = $slide->createTableShape(5)
				->setHeight(100)
				->setWidth(940)
				->setOffsetX(10)
				->setOffsetY(80);

			$row = $shape->createRow();
			$row->getFill()
				->setFillType(Fill::FILL_SOLID)
		    ->setRotation(90)
		    ->setStartColor(new Color('FFAAAAAA'))
		    ->setEndColor(new Color('FFAAAAAA'));
			
			$row = $shape->createRow();

			if ($ob->rel){
				$path = Storage::path_local($ob->rel);
				if (file_exists($path)) {
					$im = imagecreatefromjpeg($path);
					$shape = new Drawing\Gd();
					$shape->setName('Image GD')
					    ->setDescription('Image GD')
					    ->setImageResource($im)
					    ->setMimeType(Drawing\Gd::MIMETYPE_DEFAULT)
					    ->setHeight(360)
					    ->setWidth(400)
					    ->setOffsetX(400)
					    ->setOffsetY(180);
					$slide->addShape($shape);
				}
			}
		}

		//$writer = new \PhpOffice\PhpPresentation\Writer\PowerPoint2007($presentation);
		$writer = \PhpOffice\PhpPresentation\IOFactory::createWriter($presentation, 'PowerPoint2007');
		$user_id = $user->id();
		Storage::createDirectory("ppt/user/$user_id");
		$url_result = "ppt/user/$user_id/ppt.pptx";
		$output = Storage::path_local($url_result);
		$writer->save($output);
		$filename = "$user_id-".date("His").".xlsx";

		go(true,[
			"url" => Storage::url($url_result),
			"timezone" => date_default_timezone_get(),
			"filename" => $filename,
			"now" => date("Y-m-d H:i:s"),
			"in" => $in
		]);
	}
	public static function masst(Req $req) {
		$in = $req->json();
		$user = Req::user();
		$area = $user->area;
		$reportante = "$user->nombres $user->apellidos";
		$hora = date('H:i');

		$inputFileName = Storage::path_local("masst/inputV4.xlsx");
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
		$sheet = $spreadsheet->getActiveSheet();
		$info = $in->info;

			/*

              info: {
                rep_area,
                rep,
                area_resp,
                participante,
                hora
              }
			*/

		$sheet->setCellValue('D11',$area); // Area del reportante
		$sheet->setCellValue('J13',$hora); // Hora
		$sheet->setCellValue('D17',$reportante); // Responsable de la inspeccion
		$sheet->setCellValue('D15',$info->area_resp); // Responsable del area inspeccionada
		$sheet->setCellValue('D20',"$reportante, $info->participante"); // Personal que participo en la inspeccion
		$sheet->setCellValue('D22','Realizar inspecciones preventivas de seguridad para mejorar las condiciones de trabajo'); // Objetivo de la inspeccion
		$sheet->getDefaultRowDimension()->setRowHeight(135, 'pt');

		$ids = implode(",", array_map('intval', $in->ids));
		$lot = DB::lot("SELECT * FROM observacion WHERE id IN ($ids) ORDER BY fecha DESC",[]);
		$row = 26;
		$fechas = null;
		$sheet->insertNewRowBefore($row,count($lot));

		for($item=0;$item<count($lot);$item++) {
			$ob = (object)$lot[$item];
			if ($item==0) {
				$sheet->setCellValue("N13",$ob->fecha);
				$fe_max = new \DateTime($ob->fecha);
				$fechas = [
					"alto" => $fe_max->modify('+1 day')->format('Y-m-d'),
					"medio" => $fe_max->modify('+2 day')->format('Y-m-d'),
					"bajo" => $fe_max->modify('+2 day')->format('Y-m-d')
				];
			}

			if ($item<count($lot)-1) {
				//$sheet->duplicateStyle($sheet->getStyle('A25:Q25'),"A{$row}:Q{$row}");
			}

			$sheet->mergeCells("L$row:O$row");
			$sheet->setCellValue("A$row",$item+1);
			$sheet->setCellValue("B$row",$ob->area);
			$sheet->setCellValue("C$row","$ob->nivel $ob->labor\n".strtoupper($ob->zona));
			$sheet->setCellValue("D$row",$ob->info);
			$sheet->setCellValue("E$row","SST");
			$sheet->setCellValue("F$row","FOTO");
			$sheet->setCellValue("G$row",strtoupper($ob->riesgo));
			// if ($ob->riesgo=='alto') {
			// 	$sheet->getStyle("G$row")->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);
			// }
			$color = [
				'alto' => 'FFFF0000',
				'medio' => 'FFFFFF00',
				'bajo' => 'FF0000FF'
			];
			if (isset($color[$ob->riesgo])) {
				$sheet->getStyle("G$row")->getFill()
					->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
					->getStartColor()->setARGB($color[$ob->riesgo]);
			}

			$sheet->setCellValue("H$row","Lesion Personal");
			$sheet->setCellValue("I$row","13 Golpes por herramientas");
			$sheet->setCellValue("J$row","Se comunia al supervisor del area ");
			$sheet->setCellValue("K$row",$ob->accion);
			$sheet->setCellValue("L$row",$info->area_resp);

			$sheet->setCellValue("P$row",$ob->fecha_accion ? $ob->fecha_accion : $fechas[$ob->riesgo] );
			$sheet->setCellValue("Q$row","FOTO");

			if ($ob->rel) {
				$drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
				$drawing->setName('Observacion');
				$drawing->setDescription($ob->info);

				$path = Storage::path_local($ob->rel);
				$drawing->setPath($path);
				$drawing->setResizeProportional(false);
				$drawing->setHeight(132);
				$drawing->setWidth(150);
				$drawing->setWorksheet($sheet);
				$drawing->setCoordinates("F$row");
				$drawing->getShadow()->setVisible(true);
				$drawing->getShadow()->setDirection(45);
			}

			$row++;
		}

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$user_id = $user->id();
		Storage::createDirectory("masst/user/$user_id");
		$url_result = "masst/user/$user_id/masst.xlsx";
		$output = Storage::path_local($url_result);
		$writer->save($output);
		$filename = "$user_id-".date("His").".xlsx";
		//header('Content-Disposition: attachment; filename="'.$filename.'"');
		//Storage::go("masst/output.xlsx");

		go(true,[
			"url" => Storage::url($url_result),
			"timezone" => date_default_timezone_get(),
			"filename" => $filename,
			"now" => date("Y-m-d H:i:s"),
			"in" => $in
		]);
	}
	public static function middlelware(Req $req, callable $next) : void {
		if (isset($_COOKIE["uuid"])) {
			$token = $_COOKIE["uuid"];
			if (!v::alnum()->validate($token)) {
				go(false,["require_device"=>true,"msg"=>"El uuid($token) no es valido"]);
			}
			$req->uuid = $token;
		} else {
			go(false,["require_device"=>true,"msg"=>"require conectarse por dispositivo"]);
		}
		$next();
	}
	public static function sync(Req $req) {
		/*
		PRIMERO SE SINCRONIZA LOS RACS DEL CELULAR, AL FINAL ES EL DOWNLOAD DEL SERVER
		*/
		$el = @json_decode($req->param('com',null));
		$uuid = $req->cookie("uuid");
		$user_id = $req->user()->id();
		$area = $req->user()->area;
		$inserts = null;
		$sync_id = null;

		if ($el) {
			//$el: Es un elemento para subir a la nube
			$in = $req->filter([
				"zona" => v::alnum(2)
			],(array)$el);

			$file = $req->file('file');
			$last = $el->updated_at;
			$op = $el->op;
			$el->uuid = $uuid;
			unset($el->op);
			unset($el->uri);
			unset($el->rel); // NO INTERESA EL rel
			unset($el->uri_local); // NO INTERESA EL URI_LOCAL
			unset($el->idl);
			unset($el->updated_at);

			if ($op=='insert') {
				unset($el->id);
				self::_store_com($el);
				$el->id = DB::last_insert_id();
			} else if ($op=='update') {
				self::_store_com($el);
			} else if ($op=='delete') {
				//DB::query("UPDATE observacion SET is_trash=1 WHERE id=?",[$el->id]);
				$med = DB::me("SELECT * FROM observacion WHERE id=? AND (area_rep=? OR user_id=?)",[$el->id, $area, $user_id]);
				if ($med and $med['rel']) {
					Storage::delete($med['rel']);
				}
				if ($med and $med['accion_rel']) {
					Storage::delete($med['accion_rel']);
				}
				DB::query("DELETE FROM observacion WHERE id=? AND (area_rep=? OR user_id=?)",[$el->id,$area, $user_id]);
			}
			// SI HAY EL ARCHIVO, INICIAR EL CAMBIO!
			if ($op!='delete' and $file) {
				$men = DB::me("SELECT * FROM observacion WHERE id=? AND (area_rep=? OR user_id=?)",[$el->id,$area, $user_id]);
				if ($men['rel']) {
					Storage::delete($men['rel']);
				}
				$info = pathinfo($file->name());
				$rel = "racs/{$el->id}-{$file->md5()}.{$info['extension']}";
				$file->store($rel);
				DB::query("UPDATE observacion SET rel=? WHERE id=? AND (area_rep=? OR user_id=?)",[$rel,$el->id,$area, $user_id]);
			}
			$sync_id = $el->id;
		} else {
			$last = $req->param('last',null);
			$inserts = DB::lot("SELECT * FROM observacion WHERE updated_at>? AND is_trash=0 AND (user_id=? OR area=?)",[$last, $user_id, $area]);
			foreach($inserts as &$val) {
				$val['img'] = Storage::url($val['rel']);
				$val['accion_img'] = Storage::url($val['accion_rel']);
			}
		}
		go(true,[
			"inserts" => $inserts, // Elementos para insertar
			"id" => $sync_id // elemento insertado
		]);
	}
	public static function _filter($filter, $in) {
		$out = (object) [];
		foreach($filter as $key) {
			$out->{$key} = isset($in->{$key}) ? $in->{$key} : null;
		}
		return $out;
	}
	public static function _store_com($in) {
			try{
				$in = self::_filter([
				"fecha","labor","nivel","info","fecha_accion","accion","riesgo","area","zona","zona","turno","tipo", "id"], $in);
				
				$in->user_id = Req::user()->id();
				$in->area_rep = Req::user()->area;

				if ($in->id) {
					/*
					{
					    "id": 1466,
					    "idl": 11,
					    "uri_local": "file:///storage/emulated/0/Android/data/com.riesgos/files/obs/1466",
					    "uri_local_new": null,
					    "fecha": "2022-11-05",
					    "labor": "LOCOMOTORA N9",
					    "nivel": "NV23",
					    "info": "Se observa locomotora n° 9 sin triángulos de seguridad reflectivo 2",
					    "fecha_accion": "2022-11-05",
					    "accion": "Se gestiona letreros reflectores \"triángulos de seguridad \"y se procede a colocar en la locomotora",
					    "riesgo": "medio",
					    "area": "SEGURIDAD",
					    "area_rep": "SEGURIDAD",
					    "zona": "cuerpos",
					    "turno": "dia",
					    "tipo": null
					}
					*/
					DB::query("UPDATE observacion SET fecha=:fecha, labor=:labor, nivel=:nivel, info=:info, fecha_accion=:fecha_accion, accion=:accion, riesgo=:riesgo, area=:area, zona=:zona, turno=:turno, tipo=:tipo
						WHERE id=:id AND (area_rep=:area_rep OR user_id=:user_id)",$in);
				} else {
					unset($in->id);
					DB::query("INSERT INTO
						observacion (fecha, labor, nivel, info, fecha_accion, accion, riesgo, area, zona, turno, tipo, area_rep, user_id ) 
						VALUES (:fecha, :labor, :nivel, :info, :fecha_accion, :accion, :riesgo, :area, :zona, :turno, :tipo, :area_rep, :user_id)",$in);
				}
			} catch (Exception $e) {
				go(false,$in);
			}
	}
	public static function store(Req $req) {
		$in = $req->filter([
			'dni_rep' => v::length(8,8),
			'nombre' => v::length(2),
			'fecha' => v::date(),
			'riesgo' => v::length(2),
			'area' => v::length(2),
			'seccion' => v::optional(v::length(2)),
			'nivel' => v::optional(v::length(2)),
			'lugar' => v::length(2),
			'observacion' => v::length(2)
		]);
		$id = $req->param('id',null);
		if ($in->id) {
			DB::query("UPDATE observacion SET dni_rep=:dni_rep, nombre=:nombre, fecha=:fecha, riesgo=:riesgo, area=:area, seccion=:seccion, nivel=:nivel, lugar=:lugar, observacion=:observacion)",$in);
		} else {
			unset($in->id);
			DB::query("INSERT INTO observacion (dni_rep, nombre, fecha, riesgo, area, seccion, nivel, lugar, observacion) VALUES (:dni_rep, :nombre, :fecha, :riesgo, :area, :seccion, :nivel, :lugar, :observacion)",$in);
		}
		self::go_list();
	}
}