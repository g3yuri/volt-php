<?php

class ModelSetInvalid extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}

abstract class Model implements JsonSerializable{
	//protected $table = null;
	private $_reg;
	private $_id = null;
	private $_original = [];
	private $_values = [];

	public function __construct(?array $data = null){
		$this->_reg = $reg = self::boot(static::class);
		$this->_pk = $reg["primary_key"];
		if (is_array($data)){
			self::_internal_set($this->_original,$data, $this->_reg["columns"]);
			if (isset($this->_original[$this->_pk])) {
				$this->_id = $this->_original[$this->_pk];
			}
		}
	}
	abstract public static function validator();
	public function __get($key) {
		if (!in_array($key,$this->_reg["columns"])) {
			throw new Exception("No hay una columna con el valor $key");
		}
		return $this->_values[$key] ?? $this->_original[$key];
	}
	public function __set($key,$value) : void {
		if (in_array($key,$this->_reg["columns"])){
			$this->_values[$key] = $value;
		} else {
			$this->{$key} = $value;
		}
	}
	public function exists() : bool {
		return $this->_id !== null;
	}
	public static function where(string $sql,$values = null) : DB{
		$query = self::query();
		return $query->select()->where($sql,$values);
	}
	private static function _internal_set(array &$store, array $data, array $fillable) : void {
		foreach($data as $key => $value){
			if (!in_array($key,$fillable)){
				throw new ModelSetInvalid("No se permite agregar esta clave: '$key' en ".json_encode($fillable));
			}
			$store[$key] = $value;
		}
	}
	public function save(){
		$reg = $this->_reg;
		$pk = $reg["primary_key"];
		$query = new DB($reg);
		$b = $query->update()->builder();
		if (null !== $this->_id){
			foreach($this->_values as $key => $value){
				$query->set($key,":$key");
			}
			$this->_values[$pk] = $this->_id;
			$b->where("$pk=:$pk");
			$b->setParameters($this->_values);
			//go(false,$query->builder()->getSQL());
			$query->executeQuery();
		} else {
			//go(false,$this->_values);
			$this->_id = $query->create($this->_values);
		}
		$b->resetQueryParts();
		$b->select($reg["fillable"])
			->from($reg["table"])
			->where("$pk=:id")->setParameters(['id'=>$this->_id]);

		$data = $query->fetchData();
		self::_internal_set($this->_original,$data,$this->_reg["columns"]);
	}
	public function delete() : void {
		if ( null === $this->_id )
			return;
		$query = new DB($this->_reg);
		$ok = $query->delete($this->_id);
		if ($ok) {
			$this->_id = null;
			$this->_original = [];
		}
	}
	public function getKey(){
		return $this->_pk;
	}
	public function id(){
		return $this->_id;
	}
	public static function query() : DB {
		return new DB(self::boot(static::class));
	}
	public static function __callStatic($name,$arguments){
		$query = new DB(self::boot(static::class));
		return $query->$name(...$arguments);
	}
    public function jsonSerialize() : mixed {
    	$vals = array_merge($this->_original,$this->_values);
    	$fill_key = array_flip(DB::$_models[static::class]["fillable"]);
        return array_intersect_key($vals,$fill_key);
    }
    public function toArray(){
    	return $this->jsonSerialize();
    }
	public function __toString(){
		return __CLASS__."(id=".$this->id().")";
	}
	public function hasMany($class_name, $col_join){
		$query = new DB(self::boot(static::class));
		return $query->where($col_join." =",$this->{$this->_pk});
	}
	public function hasOne($class_name,$col_join){
		$query = new DB(self::boot($class_name));
		if ($this->_id===null)
			return null;
		return $query->where($col_join,$this->_id);
	}

	public static function boot($model_name){
		if (!isset(DB::$_models[$model_name])){
	        $reflect = new ReflectionClass($model_name);
			$table = $model_name::$table;
			if ($table == null){
				DB::$_models[$model_name] = []; //para evitar la recursion
				$table = (new $model_name)->table;
				if ($table==null) {
					throw new Exception("No esta asignado a una tabla '$model_name'");
				}
			}
			$table = strtolower($table);
			$reg = DB::boot_table($table);

            //$prop->setAccessible(true);
            //$prop->getValue($user);

			$fillable = $reflect->hasProperty('fillable')
				 ? $reflect->getStaticPropertyValue('fillable') : [];
			$guarded = $reflect->hasProperty('guarded')
				? $reflect->getStaticPropertyValue('guarded') : [];
			$columns = $reg["columns"];
			$reg["fillable"] = array_diff(array_unique(array_merge($fillable,$columns)),$guarded);
			$reg["class"] = $model_name;
			DB::$_models[$model_name] = $reg;
		}
		return DB::$_models[$model_name];
	}
}
