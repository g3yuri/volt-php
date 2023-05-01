<?php

namespace Model;

use Respect\Validation\Validator as v;

class Postulante extends \Model {
	static $table = "postulante";
	public static function validator(){
		return [
			'EMAIL' => [v::email(),function ($value){}],
			'PASSWORD' => v::alnum()->length(4)
		];
	}
}
