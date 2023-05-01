<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Lib\Rope;
use Respect\Validation\Validator as v;

class Role extends \Lib\Entity {
	static ?string $table = "roles";
}

class Admin {
	public static $roles = ['admin'];
	public static function roles(Req $req) : void {
		$us = DB::lot("SELECT r.id, r.name, COUNT(u.id_usuario) AS cantidad 
			FROM usuario AS u 
			LEFT JOIN model_has_roles AS mr ON u.id_usuario = mr.model_id 
			LEFT JOIN roles AS r ON mr.role_id = r.id GROUP BY r.id");
		go(true, [ "roles" => $us ]);
	}
	public static function usuarios(Req $req) : void {
		$us = Usuario::interval(0,100);
		foreach($us as &$one) {
			$one['roles'] = Usuario::rolesFromId($one['id_usuario']);
		}
		$roles = DB::lot("SELECT id, name FROM roles");
		go(true, [
			"usuarios" => $us,
			"roles" => $roles
		]);
	}
	public static function usuario_store(Req $req) : void {
		$in = $req->filter([
			'nombres' => v::optional(v::alnum(' ','ñ')),
			'apellidos' => v::optional(v::alnum(' ','ñ')),
			'password' => v::optional(v::length(4)),
			'area' => v::optional(v::alnum(' ')),
			'email' => V::email(),
			'roles' => v::each(v::intVal())
		]);
		$id = intval($req->param('id_usuario',null));
		$roles = $in->roles;
		if ($id) {
			$us = Usuario::find($id);
			$us->nombres = $in->nombres;
			$us->apellidos = $in->apellidos;
			$us->area = $in->area;
			$us->save();
		} else {
			unset($in->roles);
			DB::query("INSERT INTO usuario (email, nombres,apellidos,password, area, VALID) VALUES (:email, :nombres,:apellidos,SHA1(:password),:area,'1')",$in);
			$id = DB::last_insert_id();
			$us = Usuario::find($id);
		}
		$us->rolesUpdate($roles);
		
		go(true,$us);
	}
}