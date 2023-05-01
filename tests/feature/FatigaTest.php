<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;
use \GuzzleHttp\Cookie\CookieJar;
use Slim\Models\PasswordReset;
use Test\Assertions\vtClient;

use Respect\Validation\Validator as v;

require_once("tests/share/Assert.php");

final class FatigaTest extends TestCase
{
    use Test\Assertions\AssertGo;

    public function testCrearCliente() : vtClient
    {
        $client = new vtClient($this);
        $client->login('g3yuri@gmail.com', '123456');
        return $client;
    }
    
    /**
     * @depends testCrearCliente
     */
    public function testPerfilUpdate(vtClient $client): void
    {
        $prueba_email = 'prueba@gmail.com';

        DB::query("DELETE FROM fat_operador WHERE correo=?", [$prueba_email]);
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $payload = (object)[
            'dni' => '99999999',
            'nombres' => 'Nombre Operador',
            'area' => 'SERVICIOS',
            'guardia' => 'C',
            'celular' => '888888888',
            'correo' => $prueba_email
        ];
        $json = $client->post('/fatiga/admin/opers', $payload);
        $this->assertTrue($json->ok, var_export($json, true));
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $me_us = DB::me("SELECT * FROM fat_operador WHERE correo=?", [$prueba_email]);
        $this->assertTrue(!!$me_us, "me_us=".var_export($me_us, true));

        foreach ($payload as $key => $value) {
            $this->assertTrue($me_us[$key]==$value, "me_us=".var_export($me_us, true));
        }
        
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $json = $client->delete("/fatiga/admin/opers/$payload->dni");
        $this->assertTrue($json->ok, var_export($json, true));
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $me_us = DB::me("SELECT * FROM fat_operador WHERE correo=?", [$prueba_email]);
        $this->assertFalse(!!$me_us, var_export($me_us, true));
    }
}
