<?php

namespace Lib;

use Respect\Validation\Validator as v;
class Validator {
	static $rules = [
		"nombre" => [
			'rule' => ["texto", fn()=>v::length(3)],
			'message' => 'El nombre debe tener al menos 3 caracteres'
		],
		"texto" => [
            'rule' => fn()=>v::alnum(' ','ñ'),
            'message' => 'El texto solo puede contener letras, números y espacios'
        ],
		"json" => [
            'rule' => fn($e)=>$e->filter([
                "nombre" => "texto",
                "fecha" => ["optional","date"]
            ], $e->value()),
            'message' => 'El valor debe ser un objeto JSON válido'
        ],
		"@number" => fn($e)=>$e->set(floatval($e->val())),
	];
	private mixed $_value = null;
	private $_result = null;
	public function __construct(mixed $value) {
		$this->_value = $value;
		$this->_result = v::create();
	}
	public function rule(string $name) : mixed {
		return self::$rules[$name]['rule']($this);
	}
	public function result() : mixed {
		return $this->_result;
	}
	public function val() : mixed {
		return $this->_value;
	}
	public function set(mixed $value) : void {
		$this->_value = $value;
	}
	public static function filter($rules, $input) {
		
		$filtrado = [];
		foreach($rules as $key=>$value) {
			$x = $input[$key];
			$rul = new Validator($x);
			$rul->assertRules($value);
			$filtrado[$key] = $x;
		}
		return $filtrado;
	}
	private function assertRules(mixed $ru_val=null)  {
        try{
            if (is_array($ru_val)) {
                foreach($ru_val as $rule) {
                    $this->assertRules($rule);
                }
            } else if (is_string($ru_val) and isset(self::$rules[$ru_val])) {
                $this->assertRules(self::$rules[$ru_val]['rule']);
            } else if (is_callable($ru_val)) {
                $fn_res = $ru_val($this);
                if ($fn_res instanceof \Respect\Validation\Rules\AbstractRule) {
                    $fn_res->assert($this->_value);
                    $this->_result = $fn_res;
                }
            } else if ($ru_val instanceof \Respect\Validation\Rules\AbstractRule) {
                $ru_val->assert($this->_value);
                $this->_result = $ru_val;
            } else {
                throw new \Exception("No se reconoce el tipo de regla");
            }
        } catch (\Respect\Validation\Exceptions\ValidationException $e) {
            $msg = [];
            $get = $e->getMessages();
            $msg[] = is_array($get) ? $get : [$get];
            if (isset(self::$rules[$ru_val]))
                $msg[] = self::$rules[$ru_val]['message'];
            throw new \Exception(print_r($msg, true));
        }
	}
}
