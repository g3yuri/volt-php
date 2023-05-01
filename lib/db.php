<?php
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class DBCollect implements Iterator
{
    private Result $result;
    private $data = [];
    private $key = 0;
    private $reg;
    public function __construct(Result $res, array $reg)
    {
        $this->result = $res;
        $this->reg = $reg;
    }
    public function rewind() : void
    {
        $this->data[] = $this->result->fetchAssociative();
        $this->key=0;
    }

    public function current() : mixed
    {
        return $this->data[$this->key];
    }

    public function key() : mixed
    {
        return $this->key;
    }

    public function next(): void
    {
        $this->data[] = $c = $this->result->fetchAssociative();
        $this->key++;
        //return $c;
    }

    public function valid() : bool
    {
        return is_array($this->current());
    }
    public function deleteAll()
    {
        $all = $this->result->fetchAllAssociative();
        $pk = $this->reg["primary_key"];
        $ids = [];
        foreach ($all as $row) {
            if (!isset($row[$pk])) {
                print_r($this->reg);
                throw new Exception("No esta presenta la clave primaria '$pk'");
            }
            $ids[] = $row[$pk];
        }
        $query = new DB($this->reg);
        return $query->deleteId($ids);
    }
    public function count() : int
    {
        return $this->result->rowCount();
    }
}

class DB
{
    public static $connectionParams = array(
        'url' => 'mysql://sql_slim_gmi_gd_:86842020f@localhost:3306/sql_slim_gmi_gd_'
    );
    public static $_conn = null;
    public static $_sm = null;
    public static $_tables = [];
    public static $_models = [];
    private $_table;
    private $_reg = null;
    private $_builder;
    private $_iarg;
    private $_pk;
    private $_class;
    public function __construct($schema)
    {
        $table = $schema["table"];
        DB::connect();
        $this->_builder = DB::$_conn->createQueryBuilder();
        $this->_table = $table;
        $this->_reg = $schema;
        $this->_iarg = 0;
        $this->_class = $schema["class"];
        $this->_pk = $schema["primary_key"];
    }
    public static function connection()
    {
        DB::connect();
        return self::$_conn;
    }
    public function keyName()
    {
        return $this->_pk;
    }
    public function builder()
    {
        return $this->_builder;
    }
    public static function me(string $sql, $params = [])
    {
        $stmt = DB::connection()->prepare($sql);
        $result = $stmt->executeQuery((array)$params);
        return $result->fetchAssociative();
    }
    public static function lot(string $sql, $params = [])
    {
        $stmt = DB::connection()->prepare($sql);
        $result = $stmt->executeQuery((array)$params);
        return $result->fetchAllAssociative();
    }
    public static function query(string $sql, $params = [])
    {
        $stmt = DB::connection()->prepare($sql);
        try {
            $ret =  $stmt->executeStatement((array)$params);
        } catch (Exception $e) {
            throw new Exception("Error en la consulta ({$e->getMessage()}): $sql");
        }
        return $ret;
    }
    /*
    commit(nota):
    ====
    Debe estar beginTransaction, si no esta en go() arrojara error
    */
    public static function commit() : void
    {
        self::$_conn->commit();
        self::$_conn->beginTransaction();
    }

    public static function last_insert_id()
    {
        return DB::connection()->lastInsertId();
    }
    public function where(string $field, $value=null) : self
    {
        $b = $this->_builder;
        if ($value===null) {
            $b->where($field);
            return $this;
        } elseif (is_object($value)) {
            $b->where($field)->setParameters((array)$value);
            return $this;
        } elseif (is_array($value)) {
            $b->where($field)->setParameters($value);
            return $this;
        }
        $b->where("$field = :p".$this->_iarg)->setParameter("p".$this->_iarg, $value);
        $this->_iarg++;
        return $this;
    }
    public function param(array $array) : self
    {
        $this->_builder->setParameters($array);
        return $this;
    }
    public function set($field, $value) : self
    {
        //$this->_builder->set($field,":p".$this->_iarg)->setParameter("p".$this->_iarg,$value);
        //$this->_iarg++;
        $this->_builder->set($field, $value);
        return $this;
    }
    public function whereKey($id) : self
    {
        return $this->where($this->_pk, $id);
    }
    private static function _select_alias(array $columns, string $alias) : array
    {
        $sels = [];
        foreach ($columns as $col) {
            $sels[] = "a.".$col;
        }
        return $sels;
    }
    public function back(Model $model, $ref_id) : Model
    {
        $b = $this->_builder;
        $sels = self::_select_alias($this->_reg["columns"], 'a');
        $b->select(...$sels)
            ->from($this->_table, 'a')
            ->leftJoin('a', $model::$table, 'b', "b.$ref_id = a.$this->_pk");
        $res = $b->executeQuery();
        return new $this->_class($res->fetchAssociative());
    }
    public function ref($ref_id, Model $model) : Model
    {
        $b = $this->_builder;
        $sels = self::_select_alias($this->_reg["columns"], 'a');
        $reg = self::$_models[get_class($model)];
        $b->select(...$sels)
            ->from($this->_table, 'a')
            ->leftJoin('a', $model::$table, 'p', "p.".$reg["primary_key"]." = a.$this->_pk");
        $res = $b->executeQuery();
        return new $this->_class($res->fetchAssociative());
    }
    public function select(array $cols=null) : self
    {
        //print_r($cols);
        $this->_builder->select(...($cols ?? $this->_reg["fillable"]))
        //$this->_builder->add('select',$cols ?? $this->_reg["fillable"])
            ->from($this->_table);
        return $this;
    }
    public function insert($table_name = null) : self
    {
        if ($table_name===null) {
            $table_name = $this->_reg["table"];
        }
        $this->_builder->insert($table_name);
        return $this;
    }
    public function update($table = null, $alias = null) : self
    {
        $this->_builder->update($table ?? $this->_table, $alias);
        return $this;
    }
    public function values($data) : self
    {
        foreach ($data as $key => $value) {
            $this->_builder->setValue($key, ":p".$this->_iarg);
            $this->_builder->setParameter("p".$this->_iarg, $value);
            $this->_iarg++;
        }
        return $this;
    }
    public function create(array $data)
    {
        $b = $this->_builder;
        $fillable = $this->_reg["fillable"];
        $tyn = $this->_reg["type_names"];
        $b->insert($this->_table);
        $id = null;

        foreach ($data as $key => $value) {
            if (!in_array($key, $fillable)) {
                if (!in_array($key, $this->_reg["columns"])) {
                    throw new ModelSetInvalid("La clave '$key', no pertenece a la tabla '$this->_table' ");
                }
                throw new ModelSetInvalid("La clave '$key', no esta permitido la asignacion por create");
            }
            if (strcasecmp($key, $this->_pk) == 0) {
                $id = $value;
            }
            $b->setValue($key, ":p".$this->_iarg);
            $b->setParameter("p".$this->_iarg, $value, $tyn[$key]);
            $this->_iarg++;
        }
        $affectedRows = $b->executeStatement();
        if ($affectedRows > 0) {
            if ($id==null) {
                $id = self::$_conn->lastInsertId();
            }
            if ($id==null) {
                throw new Exception("No se puede recuperar el id de la ultima insersion");
            }
            return $id;
        }
        return null;
    }
    public function __call($name, $arguments) : self
    {
        $this->_builder->{$name}(...$arguments);
        return $this;
    }
    public function fetchData() : array
    {
        return $this->_builder->fetchAssociative();
    }
    public function first($values = null) : ?Model
    {
        $b = $this->_builder;
        $b->select($this->_reg["fillable"])
            ->from($this->_table);
        if ($values!==null) {
            $b->setParameters((array)$values);
        }
        try {
            $data = $b->fetchAssociative();
        } catch (Exception $e) {
            print("=begin=[".$b->getSQL()."]");
            print_r($b->getParameters());
            print_r($data);
            print("=END=");
            throw $e;
        }
        if ($data === false) {
            return null;
        }
        return new $this->_class($data);
    }

    public function interval(int $first, int $end=0) : array
    {
        $b = $this->_builder;
        $b->select($this->_reg["fillable"])
            ->from($this->_table)
            ->setFirstResult($first)
            ->setMaxResults($end);
        return $b->fetchAllAssociative();
    }
    public static function table($table) : DB
    {
        if (!isset(self::$_tables[$table])) {
            self::boot_table($table);
            if (!isset(self::$_tables[$table])) {
                throw new Exception("La table '$table' no existe");
            }
        }
        return new self(self::$_tables[$table]);
    }
    public function find($id) : ?Model
    {
        return $this->where($this->_pk."=:id")->first(["id"=>$id]);
    }
    public function updateNow($values)
    {
        $b = $this->_builder;
        if ($b->getQueryPart("where")===null) {
            throw new Exception("Necesita que tenga clausula where");
        }
        $sets = $b->getQueryPart("set");
        if (count($sets)==0) {
            throw new Exception("Debe haber clausulas set, previamente");
        }
        return $b->update($this->_table)
                ->setParameters((array)$values)
                ->executeStatement();
    }
    public function deleteAll($values = null)
    {
        $b = $this->_builder;
        if ($b->getQueryPart("where")===null) {
            throw new Exception("Necesita que tenga clausula where");
        }
        return $b->delete($this->_table)
                ->setParameters((array)$values)
                ->executeStatement();
    }
    public function deleteId($id) : int
    {
        $reg = $this->_reg;
        $b = $this->_builder;
        $pk = $reg["primary_key"];
        $ty = $reg["types"][$pk]->getName();

        if (is_array($id)) {
            $ty_id = Connection::PARAM_INT_ARRAY;
            if ($ty == 'string') {
                $ty_id = Connection::PARAM_STR_ARRAY;
            }
            $b->delete($this->_table)
                ->where("$pk IN (:ids)")
                ->setParameter('ids', $id, $ty_id);
        } else {
            $ty_id = ParameterType::INTEGER;
            if ($ty == 'string') {
                $ty_id = ParameterType::STRING;
            }
            $b->delete($this->_table)
                ->where("$pk = :id")
                ->setParameter('id', $id, $ty_id);
        }
        return $b->executeStatement(); #return num rows
    }
    public function get($params = [])
    {
        $b = $this->_builder;
        $b->select($this->_reg["fillable"])->from($this->_table)
            ->setParameters((array)$params);
        return new DBCollect($b->executeQuery(), $this->_reg);
    }
    public static function connect() : void
    {
        if (self::$_conn==null) {
            self::$_conn = \Doctrine\DBAL\DriverManager::getConnection(self::$connectionParams);
            self::$_conn->beginTransaction();
            self::$_sm = self::$_conn->createSchemaManager();
        }
    }
    public static function boot_table($table)
    {
        if (!isset(DB::$_tables[$table])) {
            DB::connect();
            $columns = [];
            $types = [];
            $type_names = [];
            $primary_key = null;
            $table = strtolower($table);

            foreach (DB::$_sm->listTableColumns($table) as $column) {
                $columns[] = $col = strtolower($column->getName());
                $types[$col] = $column->getType();
                $type_names[$col] = $column->getType()->getName();
            }
            foreach (DB::$_sm->listTableIndexes($table) as $ind) {
                if ($ind->isPrimary()) {
                    $cpks = $ind->getColumns();
                    if (count($cpks)>0) {
                        $primary_key = strtolower($cpks[0]);
                    }
                }
            }
            $reg =[
                "table" => $table,
                "class" => null,
                "columns" => $columns,
                "types" => $types,
                "type_names" => $type_names,
                "fillable" => [],
                "primary_key" => $primary_key
            ];
            DB::$_tables[$table] = $reg;
        }
        return DB::$_tables[$table];
    }
}
