<?php
namespace Slim\Features;

use Respect\Validation\Validator as v;
use \Req;
use \Usuario;

class Perfil {
	public static function update(Req $req) : void {
		$us = Req::user();
		if ($us==null) {
			go(false,"No esta logeado");
		}
		//go(false,Req::json());
		$in = $req->filter([
			'nombres' => v::optional(v::alnum(' ')),
			'apellidos' => v::optional(v::alnum(' ')),
			'ciudad' => v::optional(v::alnum(' ')),
			'ocupacion' => v::optional(v::alnum(' '))
		]);

		$us->nombres = $in->nombres;
		$us->apellidos = $in->apellidos;
		$us->ciudad = $in->ciudad;
		$us->ocupacion = $in->ocupacion;
		$us->save();
		go(true,$us);
	}
}