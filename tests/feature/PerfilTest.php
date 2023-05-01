<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;
use \GuzzleHttp\Cookie\CookieJar;
use Slim\Models\PasswordReset;

use Respect\Validation\Validator as v;

require_once("tests/share/Assert.php");

final class PerfilTest extends TestCase
{
    use Test\Assertions\AssertGo;

    public function testCrearCliente() : Client {
        $client = $this->createClient();
        $this->assertInstanceOf(Client::class,$client);
        return $client;
    }
    /**
     * @depends testCrearCliente
     */
    public function testLoginApi(Client $client) : string {
        return $this->assertGetLoginToken($client);
    }
    
    /**
     * @depends testCrearCliente
     * @depends testLoginApi
     */
    public function testPerfilUpdate(Client $client, string $token): Response {
        $jar = CookieJar::fromArray( [ Req::$session_name => $token ], 'slim.gmi.gd.pe' );

        $us = Usuario::where("email=:a",['a'=>"g3yuri@gmail.com"])->first();
        $us->nombres = "Cualquier cosa";
        $us->save();
        DB::connection()->commit(); DB::connection()->beginTransaction();
        $us = Usuario::where("email=:a",['a'=>"g3yuri@gmail.com"])->first();
        $us->nombres = $name = "Personalizado".rand(1,200);
        $pm = $us->toArray();
        $res = $client->post('/user/update',[
                'form_params' => $pm,
                'cookies' => $jar
            ]);
        $json = $this->assertGo($res);
        $this->assertTrue($json->ok===true,"Send=".print_r($json,true));
        DB::connection()->commit(); DB::connection()->beginTransaction();
        $us = Usuario::where("email=:a",['a'=>"g3yuri@gmail.com"])->first();
        $this->assertTrue($us->nombres===$name);
        return $res;
    }
}
