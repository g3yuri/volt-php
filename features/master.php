<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \Util;
use \Session;
use Slim\Models\PasswordReset;
use \Sender;
use \Curso;
use \Prueba;
use \DB;

use Respect\Validation\Validator as v;

class Master {
	public static function cursos(Req $req) : void {
		go(true,[
			"cursos" => DB::lot("SELECT * FROM curso ORDER BY CREADO DESC")
		]);
	}
	public static function curso_id(Req $req) : void {
		$id = $req->instance()->id;
		$res = DB::lot("SELECT * FROM pregunta WHERE ID_CURSO=:id ORDER BY CREADO DESC",['id'=>$id]);
		foreach($res as $key => &$p) {
			try {
				$p["question"] = json_decode($p["question"]);
			} catch (Exception $e) { }
			try {
				$p["variant"] = json_decode($p["variant"]);
			} catch (Exception $e) { }
			$so = DB::me("SELECT COUNT(*) AS total, COUNT(CASE WHEN ANSWER=:answer THEN 1 END) AS bien FROM respuesta WHERE ID_PREGUNTA=:ID_PREGUNTA GROUP BY ID_PREGUNTA",['answer'=>$p['answer'], 'ID_PREGUNTA' => $p['ID_PREGUNTA']]);
			if ($so) {
				$p["total"] = $so["total"];
				$p["bien"] = $so["bien"];
			} else {
				$p["total"] = $p["bien"] = 0;
			}
		}
		$curso = DB::me("SELECT * FROM curso WHERE ID_CURSO=:id",['id'=>$id]);
		go(true,[
			"preguntas" => $res,
			"curso" => $curso,
			"id" => $id
		]);
	}
}