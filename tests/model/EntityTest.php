<?php

use PHPUnit\Framework\TestCase;
use Test\Assertions\vtClient;
use Respect\Validation\Validator as v;
use \Lib\Entity;

require_once("tests/share/Assert.php");

class Table extends Entity
{
    static ?string $table = "test_table";
    public function dropTestTable() : void
    {
        DB::connection()->beginTransaction();
        DB::query("DROP TABLE test_table");
        DB::connection()->commit();
    }
}

final class EntityTest extends TestCase
{
    use Test\Assertions\AssertGo;

    // public function testCrearCliente() : vtClient
    // {
    //     $client = new vtClient($this);
    //     $client->login('g3yuri@gmail.com', '123456');
    //     return $client;
    // }
    public function testBegin() : Table
    {
        DB::connection()->beginTransaction();
        DB::query("DROP TABLE IF EXISTS test_table;
          CREATE TABLE test_table (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))");
        DB::connection()->commit();
  
        $table = new Table();
        $this->assertTrue(null===null);
        return $table;
    }
    /**
     * @depends testBegin
     */
    public function testInsert(Table $table) : void
    {
        $table->name = "test";
        // el id debe dar null ya que no fue asignado
        $this->assertTrue($table->id()===null);
        $table->store();
        $id = $table->id();
        // debe haber sido seteado con id luego del store
        $this->assertTrue($id>0);
        $table->delete();
        // luego del delete se mantiene los valores, incluido el id
        $this->assertTrue($table->id()===$id and $table->name==='test');
        //print_r($table->log());
        $table->id=2;
        $table->name="test2";
        // los seting siguen funcionando normal
        $this->assertTrue($table->id()===2 && $table->name==="test2");
        $table->store();
        //cuando se realiza el store mantienen los valores seteados
        $this->assertTrue($table->id()===2 && $table->name==="test2");
    }
}
