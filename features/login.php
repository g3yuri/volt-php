<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \Util;
use \Session;
use Slim\Models\PasswordReset;
use \Sender;
use \lib\App;
use \DB;
use Respect\Validation\Validator as v;

class Login {
	public static function login(Req $req) : void {
		$ins = $req->filter([
			'usuario' => v::email(),
			'pwd' => v::length(4)
		]);
		if ($res = App::attemp($ins->usuario,$ins->pwd,true)) {
			go(true,$res);
		}

		go(false,"El usuario o la contraseña estan incorrectos");
	}
	public static function middlelware(Req $req, callable $next) : void {
		$user = Req::user();
		if ($user===null) {
			go(false,["require_login"=>true,"msg"=>"require logearse"]);
		}
		$next();
	}
	public static function logout(Req $req) : void {
		$us = $req->user();
		if ($us instanceof Usuario){
			Session::where("user_id=:a")->get(['a'=>$us->id()])->deleteAll();
			setcookie(Req::$session_name,null,-1,'/');
			go(true,"Logout");
		}
		go(false,"No estas logeado");
	}
	public static function rescue(Req $req) : void {
		$in = $req->filter([
			'email' => v::email(),
			'test' => v::optional(v::boolVal())
		]);
		$email = strtolower(trim($in->email));
		$us = Usuario::where("email=:email")->first($in);
		if ($us instanceof Usuario){
			$codigo = rand(1000,9999);
			$id = PasswordReset::create([
				'email' => $email,
				'token' => $codigo,
				'created_at' => new \DateTime()
			]);
			if (!isset($in->test)) {
				(new Sender())
				    ->from('intellicorp.app@gmail.com')
				    ->to($in->email)
				    ->subject('Recuperacion de contraseña')
				    ->htmlTemplate('login/rescue.html.twig')
				    ->context([
					        'codigo' => $codigo,
					        'nombre' => $us->nombres
					   ])->send($email);
			}
			go(true,"Se ha enviado un correo");
		}
		go(false,"Algo paso");
	}
	public static function verify(Req $req) : void {
		$in = $req->filter([
			'email' => v::email(),
			'code' => v::intVal()->length(4)
		]);
		self::codeValidOrFail($in);
		go(true,"Cambia el password");
	}
	private static function codeValidOrFail(object $in) : void {
		$pw = PasswordReset::where("email = :email AND token = :code",$in)->first();
		if ($pw instanceof PasswordReset){
			$dd = new \DateTime($pw->created_at);
			$diff = $dd->diff(new \DateTime());
			if ($diff->days>0 or ($diff->days==0 and $diff->h>1)) {
				go(false,"Codigo Caducado");
			}
			return;
		}
		go(false,"Codigo invalido");
	}

	public static function pwd_update(Req $req) : void {
		$in = $req->filter([
			'pwd_lost' => v::length(),
			'pwd_now' => v::length(4),
			'pwd_rep' => v::length(4)
		]);
		if ($in->pwd_now != $in->pwd_rep) {
			go(false,"Debe reingresar el nuevo password");
		}
		$us = $req->user();
		$par = (object)[
			'email' => $us->email,
			'pwd' => $in->pwd_lost
		];
		$res = DB::me("SELECT COUNT(*) AS cant FROM usuario WHERE PASSWORD=sha1(:pwd) and LOWER(EMAIL)=LOWER(:email)",$par);
		if ($res["cant"]<=0) {
			go(false,"El password ingresado no es correcto");
		}
		PasswordReset::where("email = ?")->deleteAll([$us->email]);
		$par->pwd = $in->pwd_now;
		Usuario::where("email = :email")
					->set("password","sha1(:pwd)")
					->updateNow($par);
		go(true,$us->id());
	}
	public static function passchange(Req $req) : void {
		$in = $req->filter([
			'email' => v::email(),
			'code' => v::intVal()->length(4),
			'now' => v::length(4)
		]);
		self::codeValidOrFail($in);
		PasswordReset::where("email = :email")->deleteAll($in);
		Usuario::where("email = :email")
					->set("password","sha1(:now)")
					->updateNow($in);
		go(true,"Password cambiado");
	}
	public static function boot() : void {
		$us = Req::user();
		if ($us instanceof Usuario){
			go(!!$us,["token"=>$us->session()->id(),"user"=>$us, "roles"=>$us->roles()]);
		}
		go(true,[]);
	}
	public static function registro(Req $req) : void {
		$in = $req->filter([
			'nombres' => v::length(4),
			'carrera' => v::length(4),
			'apellidos' => v::length(4),
			'ciudad' => v::length(4),
			'email' => v::email(),
		]);
		$us = Usuario::where("email=:email")->first($in);
		if ($us instanceof Usuario){
			go(false,"Ya existe un usuario con ese correo");
		}
		$in->codigo = rand(1000,9999);
		$in->password = sha1(rand(1000,9999));
		DB::query("INSERT INTO usuario (email, nombres,apellidos,ciudad,password, carrera, VALID,VALID_TOKEN) VALUES (:email, :nombres,:apellidos,:ciudad,SHA1(:password), :carrera,'0',:codigo)",$in);

		(new Sender())
		    ->from('intellicorp.app@gmail.com')
		    ->to($in->email)
		    ->subject("Registro de $in->email")
		    ->htmlTemplate('login/registro.html.twig')
		    ->context([
			        'CODIGO' => $in->codigo,
			        'NAME' => $in->nombres
			   ])->send($in->email);
		$us = Usuario::where("email=:email")->first($in);
		if ($us instanceof Usuario)
			go(true,"Se envio al correo $in->email un enlace, ingrese a su correo para finalizar el registro");
		go(false,$in);
	}
	public static function registro_verify(Req $req) : void {
	}
}