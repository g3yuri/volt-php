<?php

use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testGuardedProtectedArray() : array
    {
        $user = new Usuario;
        $reflect = new ReflectionClass(Usuario::class);
        $this->assertInstanceOf(ReflectionClass::class,$reflect);
        if (!$reflect->hasProperty('guarded'))
            return [];
        $prop = $reflect->getProperty('guarded');
        $this->assertTrue($prop->isProtected());
        $this->assertTrue($prop->isStatic());
        $prop->setAccessible(true);
        $val = $reflect->getStaticPropertyValue('guarded');
        $guarded = $prop->getValue($user);
        $this->assertTrue(is_array($guarded));
        return $guarded;
    }

    public function testFillableProtectedArray() : array
    {
        $user = new Usuario;
        $reflect = new ReflectionClass(Usuario::class);
        $this->assertInstanceOf(ReflectionClass::class,$reflect);
        if (!$reflect->hasProperty('fillable'))
            return [];
        $prop = $reflect->getProperty('fillable');
        $this->assertTrue($prop->isProtected());
        $prop->setAccessible(true);
        $fillable = $prop->getValue($user);
        $this->assertTrue(is_array($fillable));
        return $fillable;
    }

    public function testSchemaUsuario():array
    {
        $reg = DB::$_models[Usuario::class];
        $this->assertTrue(is_array($reg["columns"]));
        foreach($reg["columns"] as $col){
            $this->assertTrue(strtolower($col)==$col);
        }
        $this->assertTrue(is_array($reg["fillable"]));
        foreach($reg["fillable"] as $col){
            $this->assertTrue(strtolower($col)==$col);
        }
        $this->assertTrue(strtolower($reg["table"])==$reg["table"]);
        $this->assertTrue(strtolower($reg["primary_key"])==$reg["primary_key"]);
        $this->assertTrue(Usuario::class==$reg["class"]);
        $this->assertTrue(in_array($reg["primary_key"],$reg["columns"]));
        return $reg;
    }

    /**
     * @depends testSchemaUsuario
     * @depends testGuardedProtectedArray
     * @depends testFillableProtectedArray
     */
    public function testFillableResult(array $reg, array $guarded, array $fillable) :void
    {
        $all = $reg["columns"];
        $this->assertTrue(is_array($reg["fillable"]));
        foreach($reg["fillable"] as $col) {
            $this->assertTrue(in_array($col,$all));
        }
    }

    public function testUsuarioFind(): Usuario {
		$data = Usuario::find(-1);
        $this->assertEquals(null,$data);
		$data = Usuario::find(1);
        $this->assertInstanceOf( Usuario::class,$data);
        return $data;
    }
    /**
     * @depends testUsuarioFind
     */
    public function testReferencias(Usuario $data): Usuario {
        $data = $data->session();
        $this->assertInstanceOf( Session::class,$data);
        $data = $data->user();
        $this->assertInstanceOf( Usuario::class,$data);
        $ses = Session::ref("user_id",$data);
        $this->assertInstanceOf( Session::class,$ses);
        $back = Usuario::back($ses,"user_id");
        $this->assertInstanceOf( Usuario::class,$back);
        return $back;
    }
    /**
     * @depends testReferencias
     */
    public function testModificarConSave(Usuario $data): void {
    	$token = 'Alguna cosa';
		$data->nombres = $token;
		$data->save();
		$this->assertEquals($token,$data->nombres);
    }

    public function testCrearModeloSinConstructor(): void {
        Session::deleteId('otro mas');
		$data = new Session;
		$this->assertEquals($data->exists(),false);
		$data->id='otro mas';
		$data->payload = 'pay';
		$data->user_id = 18782;
		$data->last_activity = time();
		$data->save();
		$this->assertEquals($data->exists(),true);
		$data = $data->user();
        $this->assertTrue($data==null,"deben ser nulos");
        //$this->assertInstanceOf( Usuario::class,$data);
    }
    public function testWhereArray(): void {
		$data = Usuario::where('EMAIL = :email',[ 'email' => 'g3yuri@gmail.com' ])->first();
        $this->assertInstanceOf( Usuario::class,$data);
    }
    public function testWhereValue(): void {
    	$query = Usuario::where('EMAIL','g3yuri@gmail.com');
        $this->assertInstanceOf( DB::class,$query);
		$data = $query->first();
        $this->assertInstanceOf( Usuario::class,$data);
    }
    public function testCreateRetornaID(): void {
        $token = Util::gen_token();
        $session_id = Session::create([
				'id' => $token,
				'user_id' => 18782,
				'payload' => 'value',
				'ip_address' => '127.0.0.1',
				'last_activity' => time()
			]);
        $this->assertEquals( $token, $session_id);

    }
    public function testCampoInvalido(): void
    {
    	$this->expectException(ModelSetInvalid::class);
        $session_id = Session::create([
				'id' => 'token',
				'user_id' => 18782,
				'payload' => 'value',
				'ip_address' => '127.0.0.1',
				'last_activity2' => time()
			]);
    }
}