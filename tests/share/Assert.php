<?php

namespace Test\Assertions;

use PHPUnit\Framework\TestCase;
use \GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;
use \Util;
use \Usuario;
use \DB;
use \Req;

use GuzzleHttp\Cookie\SetCookie;

use Respect\Validation\Validator as v;

class vtClient
{
    private $base_url = '';
    private $res = null; // ultimo resultado de la solicitud del cliente
    private $token = null;
    private $login_user = null;
    private TestCase $unit;
    private Client $client;
    public function __construct(TestCase $unit)
    {
        $this->unit = $unit;
        $this->client = new Client([
            'base_uri' => 'https://slim.gmi.gd.pe',
            'verify' => false, #para que no verifice certificados
            'cookies' => true, #para que comparta las cookies
            'timeout'  => 2.0,
            'headers' => ['Accept' => 'application/json']
        ]);
        $this->unit->assertInstanceOf(Client::class, $this->client);
    }
    public function login(string $user, string $pwd) : void
    {
        $pm = [
            'usuario' => $user,
            'pwd' => $pwd
        ];
        $json = $this->post('/login', $pm);
        $res = $this->res;

        $this->unit->assertTrue($json->ok==true, print_r($pm, true).print_r($json, true).print_r($user, true));
        $this->unit->assertTrue($res->hasHeader('Set-Cookie'), print_r($json, true));
        $parser = new SetCookie;
        $cok = $parser->fromString($res->getHeader('Set-Cookie')[0]);
        $this->unit->assertInstanceOf(SetCookie::class, $cok);
        $this->unit->assertTrue($cok->getName()==Req::$session_name);
        $this->unit->assertTrue($cok->getHttpOnly());


        $body = $json->body;
        $this->unit->assertTrue(isset($body->token), "Fallo=".print_r($body, true));
        $this->unit->assertTrue(isset($body->user));
        $user = $body->user;
        $this->unit->assertTrue(isset($user->email));
        $this->unit->assertTrue(isset($user->id_usuario));
        $token = $body->token;
        $email = $user->email;
        $id_usuario = $user->id_usuario;

        $this->unit->assertTrue($cok->getValue()==$token, "{$cok->getValue()} es diferente de $token");
        $this->unit->assertTrue(v::email()->validate($email));
        $this->unit->assertTrue(v::alnum()->length(4)->validate($token));
        $this->unit->assertTrue(v::intVal()->min(1)->validate($id_usuario));
        $this->token = $token;
        $this->login_user = $user;
    }
    public function post(string $uri, array|object $payload) : object
    {
        $form = [
            'form_params' => $payload
        ];
        if ($this->token!==null) {
            $form['cookies'] = CookieJar::fromArray([ Req::$session_name => $this->token ], 'slim.gmi.gd.pe');
        }
        
        $this->res = $res = $this->client->post($uri, $form);
        $json = $this->unit->assertGo($res);
        return $json;
    }
    public function get(string $uri) : object
    {
        $form = [];
        if ($this->token!==null) {
            $form['cookies'] = CookieJar::fromArray([ Req::$session_name => $this->token ], 'slim.gmi.gd.pe');
        }
        
        $this->res = $res = $this->client->get($uri, $form);
        $json = $this->unit->assertGo($res);
        return $json;
    }
    public function delete(string $uri) : object
    {
        $form = [];
        if ($this->token!==null) {
            $form['cookies'] = CookieJar::fromArray([ Req::$session_name => $this->token ], 'slim.gmi.gd.pe');
        }
        
        $this->res = $res = $this->client->delete($uri, $form);
        $json = $this->unit->assertGo($res);
        return $json;
    }
}

/**
 * Trait AssertSJson
 * @package Test\Assertions
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait AssertGo
{
    /**
     * Assert that the JSON data complies with our custom structure
     *
     * @param Response $res
     * @param string $msg
     */
    public function assertGo(Response $res, ?string $msg = "ASSERT GO") : object
    {
        self::assertTrue($res->getStatusCode()==200, $msg);
        self::assertTrue($res->hasHeader('Content-Type'), $msg);
        self::assertTrue($res->getHeader('Content-Type')[0]=='application/json', $res->getHeader('Content-Type')[0].": Esto fallo, Body=".$res->getBody());
        $json = json_decode($res->getBody());
        self::assertEquals(json_last_error(), JSON_ERROR_NONE, $msg.":".$res->getBody());
        self::assertTrue(isset($json->ok), "Json=".json_encode($json));
        self::assertTrue(isset($json->body), "Json=".json_encode($json));
        self::assertTrue($json->ok === true or $json->ok===false, "Json=".json_encode($json));
        return $json;
    }

    public function assertGetLoginToken(Client $client, ?string $msg = "ASSERT GO") : string
    {
        $pm = [
            'usuario' => 'g3yuri@gmail.com',
            'pwd' => "123456"
        ];
        $us = Usuario::where("email=:usuario")->set("password", "SHA1(:pwd)")->updateNow($pm);
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $res = $client->post('/login', [
            'form_params' => $pm
        ]);
        $json = self::assertGo($res);
        self::assertTrue($json->ok==true, print_r($pm, true).print_r($json, true).print_r($us, true));
        self::assertTrue($res->hasHeader('Set-Cookie'), print_r($json, true));
        $parser = new SetCookie;
        $cok = $parser->fromString($res->getHeader('Set-Cookie')[0]);
        self::assertInstanceOf(SetCookie::class, $cok);
        self::assertTrue($cok->getName()==Req::$session_name);
        self::assertTrue($cok->getHttpOnly());
        
        $body = $json->body;
        self::assertTrue(isset($body->token), "Fallo=".print_r($body, true));
        self::assertTrue(isset($body->user));
        $user = $body->user;
        self::assertTrue(isset($user->email));
        self::assertTrue(isset($user->id_usuario));
        $token = $body->token;
        $email = $user->email;
        $id_usuario = $user->id_usuario;
        self::assertTrue($cok->getValue()==$token, "{$cok->getValue()} es diferente de $token");
        self::assertTrue(v::email()->validate($email));
        self::assertTrue(v::alnum()->length(4)->validate($token));
        self::assertTrue(v::intVal()->min(1)->validate($id_usuario));
        return $token;
    }
    public function createClient() : Client
    {
        $client = new Client([
            'base_uri' => 'https://slim.gmi.gd.pe',
            'verify' => false, #para que no verifice certificados
            'cookies' => true, #para que comparta las cookies
            'timeout'  => 2.0,
            'headers' => ['Accept' => 'application/json']
        ]);
        $this->assertInstanceOf(Client::class, $client);
        return $client;
    }
}
