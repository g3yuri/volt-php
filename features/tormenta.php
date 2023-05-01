<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;

function FcmSend($payload) {
	$header = array();
	$header[] = 'Content-type: application/json';
	$header[] = 'Authorization: key=AAAAG_REf7Y:APA91bEg5vc0CGn_UxaCYiCI9mpp55iqWHFe7iLmGKkQcs3ONJP1RyLoDR0F_7YhyLRcxbdUXsQfiiVf9GNAkEGQxqW5SYkqd9ZfJHmfNUEE48xV8nN8rHbSKJ3x1Tl3dcZoqA3NBuRO';

	$crl = curl_init();
	curl_setopt($crl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($crl, CURLOPT_POST, true);
	curl_setopt($crl, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
	curl_setopt($crl, CURLOPT_POSTFIELDS, json_encode($payload));
	curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
	$rest = curl_exec($crl);
	curl_close($crl);
	return json_decode($rest);
}

class Tormenta {
	public static function list() {
		$lot = DB::lot("SELECT * FROM tormenta ORDER BY created_at DESC");
		go(true,[
			"alertas" => $lot
		]);
	}
	public static function _propagate($in) : void{
		$lot = DB::lot("SELECT * FROM tor_devices");
		$res=[];
		foreach($lot as $el) {
			$v = FcmSend([
				'to' => $el['token'],
				'android' => [
					'priority' => 'high',
					'channelId' => 'segundo',
				],
				'data' => [
					'tipo' => 'online',
				],
				'notification' => [
					'title' => "ALERTA NIVEL$in->nivel",
					'body' => "$in->info",
					'sound' => 'hollow',
					'color' => '#0086ec',
				],
			]);
			$res[] = $v;
		}
	}
	public static function store(Req $req) {
		$in = $req->filter([
			'nivel' => v::intVal(),
			'info' => v::length(2)
		]);
		$id = intval($req->param('id',null));
		if ($id) {
			$in->id = $id;
			DB::query("UPDATE tormenta SET nivel=:nivel, info=:info WHERE id=:id",(array)$in);
		} else {
			DB::query("INSERT INTO tormenta (nivel, info)
				VALUES (:nivel,:info)",(array)$in);
			$id = DB::last_insert_id();
		}
		self::_propagate($in);
		go(true,$id);
	}
	public static function sync(Req $req) {
		$in = $req->filter([
			'uuid' => v::length(4),
			'token' => v::length(4)
		]);
		date_default_timezone_set('America/Lima');
		DB::query("INSERT INTO tor_devices (id, token) VALUES(:uuid,:token) ON DUPLICATE KEY UPDATE token=:token",$in);
		$info = DB::me("SELECT * FROM tormenta ORDER BY created_at DESC LIMIT 1");
		$info->time = date("d/m/Y h:i:s a");
		$info->time_msg = date("d");
		go(true,$info);
	}
}