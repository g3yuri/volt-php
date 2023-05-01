<?php

namespace Lib;

use \DB;

class EntityCollect implements \Iterator, \JsonSerializable
{
    private ?array $data = [];
    private int $key = 0;
    private $class = null;
    public function __construct(?array $data, ?string $class)
    {
        $this->data = $data;
        $this->class = $class;
    }
    public function rewind() : void
    {
        $this->key=0;
    }
    public function current() : Entity
    {
        $res = new $this->class;
         $res->merge($this->data[$this->key]);
         return $res;
    }
    public function key() : int
    {
        return $this->key;
    }
    public function next(): void
    {
        $this->key++;
        //return $c;
    }
    public function valid() : bool
    {
        return is_array($this->current());
    }
    public function count() : int
    {
        return count($this->data);
    }
    public function jsonSerialize() : array
    {
        return $this->data;
    }
}

abstract class Entity implements \JsonSerializable
{
    /*
        $reg =[
            "table" => $table,
            "columns" => $columns,
            "types" => $types,
            "primary_key" => $primary_key
        ];
    */
    private array $_reg;
    private ?array $_validate = null;
    private array $_values = []; // Valores originales de la columna
    private array $_accesible = []; // las columnas que se pueden settear estan en el KEY
    static ?array $attr_accessible = null; //Columnas que se pueden modificar
    static ?array $attr_protected = null; //Columnas que no se pueden ver
    static ?string $table = null;
    public function __construct()
    {
        $table = static::get_table_name();
        
        $this->_reg = DB::boot_table($table);
        // array remove items from columns
        $cols = self::$attr_accessible ?? array_diff($this->_reg["columns"], ["created_at", "updated_at"]);

        if (self::$attr_protected) {
            $cols = array_diff($cols, self::$attr_protected);
        }
        $this->_accesible = array_fill_keys($cols, true);
    }
    public function __get(mixed $key)
    {
        if (!in_array($key,$this->_reg["columns"]) ) {
            throw new \Exception("No hay una columna con el valor $key");
        }
        return $this->_values[$key] ?? null;
    }
    public function __set(string $key, mixed $value) : void
    {
        if (!isset($this->_accesible[$key])) {
            throw new \Exception("No se puede settear en la clave $key");
        }
        $this->_values[$key] = $value;
    }
    public function __isset($key) : bool
    {
        return isset($this->_values[$key]);
    }
    public function __unset($key) : void
    {
        unset($this->_values[$key]);
    }
    public function jsonSerialize() : string
    {
        return json_encode($this->_values);
    }
    public function log() : array
    {
        return [
            '_reg' => $this->_reg,
            '_values' => $this->_values,
            '_accesible' => $this->_accesible
        ];
    }
    public function id() : mixed
    {
        return $this->_values[$this->_reg["primary_key"]] ?? null;
    }
    public function store() : void
    {
        if ($this->store_before()) {
            return;
        }
        $keys = array_keys($this->_values);

        $pk = $this->_reg["primary_key"];

        if (count($keys)==0 or !$pk) {
            $cc = count($keys);
            throw new \Exception("No se puede guardar el registro (pk: $pk, count: {$cc} )");
        }
        
        if ($this->id()) {
            $set = implode(", ", array_map(fn ($ky) => "$ky=:$ky", $keys));
            DB::query("UPDATE {$this->_reg["table"]} SET {$set} WHERE {$pk}=:{$pk}", $this->_values);
        } else {
            // remove item from array
            $values = implode(", ", array_map(fn ($key) => ":$key", $keys));
            $cols = implode(", ", $keys);
            //echo "INSERT INTO {$this->_reg["table"]} ({$cols}) VALUES ({$values})";
            DB::query("INSERT INTO {$this->_reg["table"]} ({$cols}) VALUES ({$values})", $this->_values);
            $this->_values[$pk] = DB::last_insert_id();
        }
    }
    public function delete() : void
    {
        $pk = $this->_reg["primary_key"];
        DB::query("DELETE FROM {$this->_reg['table']} WHERE {$pk}=?", [$this->id()]);
    }
    protected function createValidator():array
    {
        return [];
    }
    public function merge(?array $data) : void
    {
        if (!$this->_validate) {
            $this->_validate = $this->createValidator();
        }
        $cons = $this->_validate;

        try {
            foreach ($this->_accesible as $column => $col_val) {
                if (isset($data[$column])) {
                    if ($cons[$column]) {
                        $cons[$column]->assert($data[$column]);
                    }
                    $this->_values[$column] = $data[$column];
                }
            }
        } catch (\Respect\Validation\Exceptions\ValidationException $e) {
            throw new \Exception($e->getFullMessage());
        }
    }
    protected function store_before() : bool
    {
        return false;
    }
    final public function fetch(mixed $id = null) : self
    {
        $pk = $this->_reg["primary_key"];
        if ($id === null) {
            $id = $this->_values[$pk];
        }
        $this->_values = DB::me("SELECT * FROM {$this->_reg['table']} WHERE {$pk}=?", [$id]);
        return $this;
    }
    public static function get_table_name() : string {
        return isset(static::$table) ? static::$table : strtolower(static::class);
    }
    public static function find(mixed $id) : ?self
    {
        $table = static::get_table_name();
        $reg = DB::boot_table($table);
        $pk = $reg["primary_key"];
        $data = DB::me("SELECT * FROM {$reg['table']} WHERE {$pk}=?", [$id]);
        if (!$data) {
            return null;
        }
        $entity = new static();
        $entity->_values = $data;
        return $entity;
    }
    public static function all() : EntityCollect
    {
        $table = static::get_table_name();
        $reg = DB::boot_table($table);
        $data = DB::lot("SELECT * FROM {$reg['table']}");
        return new EntityCollect($data, static::class);
    }
    public static function where(string $where, array $params = []) : array
    {
        $table = static::get_table_name();
        $reg = DB::boot_table($table);
        $data = DB::lot("SELECT * FROM {$reg['table']} WHERE {$where}", $params);
        $entities = [];
        foreach ($data as $row) {
            $entity = new static();
            $entity->_values = $row;
            $entities[] = $entity;
        }
        return $entities;
    }
    public static function where_first(string $where, array $params = []) : ?self
    {
        $table = static::get_table_name();
        $reg = DB::boot_table($table);
        $data = DB::me("SELECT * FROM {$reg['table']} WHERE {$where}", $params);
        if (!$data) {
            return null;
        }
        $entity = new static();
        $entity->_values = $data;
        return $entity;
    }
    public static function count(string $where = "", array $params = []) : int
    {
        $table = static::get_table_name();
        $reg = DB::boot_table($table);
        $data = DB::me("SELECT COUNT(*) as count FROM {$reg['table']} {$where}", $params);
        return $data["count"];
    }
    public static function paginate(int $page, int $limit = 10, string $where = "", array $params = []) : array
    {
        $table = static::get_table_name();
        $reg = DB::boot_table($table);
        $offset = ($page - 1) * $limit;
        $data = DB::lot("SELECT * FROM {$reg['table']} {$where} LIMIT {$limit} OFFSET {$offset}", $params);
        $entities = [];
        foreach ($data as $row) {
            $entity = new static();
            $entity->_values = $row;
            $entities[] = $entity;
        }
        return $entities;
    }
    public static function create(array $data) : self
    {
        $entity = new static();
        $entity->merge($data);
        $entity->store();
        return $entity;
    }
    public static function create_or_update(array $data) : self
    {
        $table = static::get_table_name();
        $reg = DB::boot_table($table);
        $pk = $reg["primary_key"];
        $id = $data[$pk];
        if ($id) {
            $entity = static::find($id);
            if ($entity) {
                $entity->merge($data);
                $entity->store();
                return $entity;
            }
        }
        return static::create($data);
    }
    public static function create_or_update_by(array $data, string $by) : self
    {
        $table = static::get_table_name();
        $reg = DB::boot_table($table);
        $pk = $reg["primary_key"];
        $id = $data[$by];
        if ($id) {
            $entity = static::where_first("WHERE {$by}=?", [$id]);
            if ($entity) {
                $entity->merge($data);
                $entity->store();
                return $entity;
            }
        }
        return static::create($data);
    }
}
