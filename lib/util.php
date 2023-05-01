<?php

class Util{
	private static $base32 = null;
	public static function gen_token($key1 = "", $key2 = "") {
		$ip = \Router::get_client_ip();
		$ms = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$sid = "";
		for ($i = 0; $i < 25; $i++) {
			$sid .= substr($ms, rand(0, strlen($ms) - 1), 1);
		}
		return md5($key1 . $key2 . $ip . $sid);
	}
	/*
	Retorna el tipo de imagen del archivo sin ver la extension
	retorna la extencion o null
	*/
	public static function get_img_type($filename) : ?string {
	    $handle = @fopen($filename, 'r');
	    if (!$handle)
	        throw new Exception('Util.get_img_type: File Open Error');

	    $types = [
	    	'jpg' => "\xFF\xD8\xFF",
	    	'gif' => 'GIF',
	    	'png' => "\x89\x50\x4e\x47\x0d\x0a",
	    	'bmp' => 'BM',
	    	'psd' => '8BPS',
	    	'swf' => 'FWS'
	    ];

	    $bytes = fgets($handle, 8);
	    $found = null;

	    foreach ($types as $type => $header) {
	        if (strpos($bytes, $header) === 0) {
	            $found = $type;
	            break;
	        }
	    }
	    fclose($handle);
	    return $found;
	}
	public static function base32() : Base2n {
		if (self::$base32==null)
			self::$base32 = new Base2n(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', FALSE, TRUE);
		return self::$base32;
	}
	/*
	* path: es la ruta del archivo csv
	* cols: es un array asociativo, que significa:
			keys:	son las columnas que debe tener la tabla de la base de datos
			value:	puede ser de 02 tipos:
				null:		no se realiza nada
				callable:	se ejecuta la funcion -> function (&$field, &$value)

	 	$global_cols = [
			"id" => null,
			"nombre" => null,
			"fecha" => function (&$col,&$value) {
				$n = sscanf($value,"%d/%d/%d",$dia, $mes, $anio);
				$value = sprintf("%d-%d-%d",$anio,$mes,$dia);
			}
		];
	*/
	public static function import_csv($path,$global_cols, $delimiter=",") {
		if (($handle = fopen($path, "r")) === FALSE)
			throw new Exception("No se puede abrir el archivo en: ".$path);

		$head = fgetcsv($handle,0,$delimiter);
		foreach($head as $key => &$v) {
			$v = trim(preg_replace('/\W+/',' ',$v));//los caracteres que no se pueden leer lo convierte en espacio
			if (!array_key_exists($v,$global_cols)) { // si no exite la columna del csv
				$head[$key] = null;
				continue;
			}
			$head[$key] = $v;
		}

		while (($data = fgetcsv($handle,0,$delimiter)) !== FALSE) {
			$sol = [];
			foreach($data as $col => $value) {
				if (!isset($head[$col]) or $head[$col]===null) {
					continue;
				}
				$field = $head[$col];
				$call = $global_cols[$field];
				if (is_callable($call)) {
					$call($field,$value);
				}
				$sol[$field] = $value;
			}
			if (count($sol)>0)
				yield $sol;
		}
		fclose($handle);
	}
	public static function import_db_insert($db,$values) {
			$keys = array_keys($values);
			$fields = implode(",",$keys);
			$pfields = implode(",", array_map(function($v){ return is_null($v)?"NULL":":$v"; }, $keys) );
			\DB::query("INSERT INTO $db ($fields) VALUES ($pfields)",$values);
	}
	public static function change_date_dmy_ymd($field,&$value) {
		$value = trim($value);
		if ($value==""){
			$value = null;
			return;
		}
		$n = sscanf($value,"%d/%d/%d",$dia, $mes, $anio);
		$value = date("Y-m-d",mktime(0,0,0,$mes,$dia,$anio));
		//$value = sprintf("%d-%d-%d",$anio,$mes,$dia);
	}
	public static function days_interval(int $from, int $to) : ?array {
		$hoy = new \DateTime();
		$inicio = (clone $hoy)->modify("$from day");
		$final = (clone $hoy)->modify("$to day");

		$in = (object)[
			"inicio" => $inicio->format("Y-m-d"),
			"final" => $final->format("Y-m-d")
		];
		$periodo = new \DatePeriod($inicio,new \DateInterval('P1D'),$to-$from);
		return array($in, $periodo);
	}
}
