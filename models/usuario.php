<?php
// use \Session;
// use \Util;
// use \Req;
// use \Model;

class Usuario extends Model {
	static $table = "usuario";
	protected static $guarded = ["password","token"];
	private $_roles = null;
	public static function login($mail,$pwd, &$token = null) {
		$us = Usuario::where('EMAIL = :email AND PASSWORD = SHA1(:pwd)',[
				'email' => $mail, 'pwd' => $pwd
			])->first();
		if ($us){
			$token = Util::gen_token();
			$id = Session::create([
				'id' => $token,
				'user_id' => $us->id(),
				'payload' => 'value',
				'ip_address' => Req::ip(),
				'last_activity' => time()
			]);
			Req::set_cookie(Req::$session_name,$token,3*24*60*60/*seg*/,'/','.gmi.gd.pe',true,true);
		}
		return $us;
	}
	public function rolesUpdate(array $roles){
		$id = $this->id();
		DB::query("DELETE FROM model_has_roles WHERE model_id=?",$id);
		foreach($roles as $v) {
			DB::query("INSERT INTO model_has_roles (role_id,model_id) VALUES (?,?)",[$v, $id]);
		}
	}
	public function roles(){
		if ( $this->_roles === null ) {
			$this->_roles = self::rolesFromId($this->id());
		}
		return $this->_roles;
		//return count(array_intersect($this->_roles,func_get_args()))>0;
	}
	public static function rolesFromId($id){
		$res = DB::lot("SELECT roles.id AS id, roles.name as name FROM model_has_roles LEFT JOIN roles ON roles.id=model_has_roles.role_id WHERE model_has_roles.model_id=?",[intVal($id)]);
		return $res;
	}
	public function session(){
		return Session::ref("user_id",$this);
	}
	public static function validator(){
		return [
			'id' => v::alnum(),
			'user_id' => v::intVal(),
			'ip' => v::ip(),
			'last_activity' => v::intVal()
		];
	}
}
