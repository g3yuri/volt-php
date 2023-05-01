<?php

use Respect\Validation\Validator as v;
//use \Usuario;

class Session extends Model {
	static $table = "sessions";
	function user() {
		return Usuario::find($this->user_id);
	}
	public static function validator(){
		return [
			'EMAIL' => [v::email(),function ($value){}],
			'PASSWORD' => v::alnum()->length(4)
		];
	}
}
