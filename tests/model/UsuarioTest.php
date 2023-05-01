<?php

use PHPUnit\Framework\TestCase;

final class UsuarioTest extends TestCase
{
    public function testCreacionUsuario(): Usuario {
        
		$data = Usuario::find(-1);
        $this->assertEquals(null,$data);
		$data = Usuario::find(1);
        $this->assertInstanceOf(Usuario::class,$data);
        return $data;
    }
    /**
     * @depends testCreacionUsuario
     */
    public function testGuarded(Usuario $user): void {
        $reg = DB::$_models[Usuario::class];
        $this->assertTrue(is_array($reg));
        $fill = $reg["fillable"];
        $this->assertTrue(is_array($fill));
        $this->assertFalse(isset($fill["password"]));
        $this->assertFalse(isset($fill["token"]));
        $json = json_encode($user);
        $json = (array)json_decode($json);
        $this->assertTrue(is_array($json));
        $this->assertFalse(isset($json["password"]));
        $this->assertFalse(isset($json["token"]));
        $json = json_encode($user->session()->user());
        $json = (array)json_decode($json);
        $this->assertFalse(isset($fill["password"]));
        $this->assertFalse(isset($fill["token"]));
        $this->assertTrue(is_array($json));
        $this->assertFalse(isset($json["password"]));
        $this->assertFalse(isset($json["token"]));
        // foreach($guarded as $guard){
        //     $this->assertFalse(array_key_exists($guard,$json));
        // }
    }
}