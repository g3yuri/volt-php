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

final class LoginTest extends TestCase
{
    use Test\Assertions\AssertGo;
    public function testCrearCliente() : Client
    {
        $client = $this->createClient();
        return $client;
    }
    /**
     * @depends testCrearCliente
     */
    public function testBoot(Client $client): Response
    {
        $res = $client->get('/boot');
        $this->assertInstanceOf(Response::class, $res);
        return $res;
    }
    /**
     * @depends testCrearCliente
     */
    public function testLoginFallo(Client $client): void
    {
        $res = $client->post('/login', [
            'form_params' => [
                'usuario' => 'abc',
                'pwd' => '123'
            ]
        ]);
        $json = $this->assertGo($res, print_r($res, true)."Ass");
        $this->assertEquals($json->ok, false);
        $this->assertTrue(is_string($json->body), "El mensaje debe ser string.".print_r($json, true));
    }
    /**
     * @depends testCrearCliente
     */
    public function testLoginCorrecto(Client $client): string
    {
        return $this->assertGetLoginToken($client);
    }
    /**
     * @depends testCrearCliente
     * @depends testLoginCorrecto
     */
    public function testVerificaSessionExistente(Client $client, $token): void
    {
        $ses = Session::find($token);
        $this->assertTrue($ses->exists(), "token=".$token);
        $us = $ses->user();
        $this->assertInstanceOf(Usuario::class, $us);
        $all = Session::where("user_id=:id")->get(['id'=>$us->id()]);
        $this->assertTrue($all->count()==1, "Solo debe haber uno, hay:".$all->count());
    }
    /**
     * @depends testCrearCliente
     * @depends testLoginCorrecto
     */
    public function testLogoutCookieFalso(Client $client, $token): void
    {
        #SE DEBE ACTUALIZAR PARA QUE PUEDA LEER DATOS VERDADEROS
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $jar = CookieJar::fromArray([ Req::$session_name => 'otro-token' ], 'slim.gmi.gd.pe');
        $res = $client->get('/logout', ['cookies' => $jar]);
        $json = $this->assertGo($res);
        $this->assertTrue($json->body->require_login===true, "No debe deslogearse:".print_r($json, true));
        $ses = Session::find($token);
        $this->assertTrue($ses->exists(), "Debe existir el TOKEN=$token; ses=".print_r($ses, true));
    }
    /**
     * @depends testCrearCliente
     * @depends testLoginCorrecto
     */
    public function testLogoutSinCookie(Client $client, $token): void
    {
        #SE DEBE ACTUALIZAR PARA QUE PUEDA LEER DATOS VERDADEROS
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        #SINCOOKIE
        $res = $client->get('/logout', ['cookies' => null]);
        $json = $this->assertGo($res);
        $this->assertTrue($json->ok===false, "D:");
        $this->assertTrue($json->body->require_login===true, "No debe deslogearse:".print_r($json, true));
        $ses = Session::find($token);
        $this->assertTrue($ses instanceof Session, "Debe existir el TOKEN=$token; ses=".print_r($ses, true));
        $this->assertTrue($ses->exists(), "Debe existir el TOKEN=$token; ses=".print_r($ses, true));
    }
    /**
     * @depends testCrearCliente
     * @depends testLoginCorrecto
     */
    public function testLogout(Client $client, $token): void
    {
        $jar = CookieJar::fromArray([ Req::$session_name => $token ], 'slim.gmi.gd.pe');
        $res = $client->get('/logout', ['cookies' => $jar]);

        $json = $this->assertGo($res);
        $this->assertTrue($json->ok===true);
        $this->assertTrue(strcasecmp($json->body, "Logout")==0, "Debe devolver Logout, devolvio:".print_r($json, true));
        #SE DEBE ACTUALIZAR PARA QUE PUEDA LEER DATOS VERDADEROS
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $ses = Session::find($token);
        $this->assertTrue($ses===null, "Existe el TOKEN=$token; ses=".print_r($ses, true));
    }
    /**
     * @depends testCrearCliente
     * @depends testLogout
     */
    public function testRecuperarContrasenaFallido(Client $client, $token): void
    {
        $res = $client->post('/code/rescue', [
            'form_params' => [
                'email' => 'no-email'
            ]
        ]);
        $json = $this->assertGo($res);
        $this->assertTrue($json->ok===false);
        $res = $client->post('/code/rescue', [
            'form_params' => [
                'email' => 'email@inexistente.gob'
            ]
        ]);
        $json = $this->assertGo($res);
        $this->assertTrue($json->ok===false);
    }
    public function FaltatestRecuperarContrasenaFallidoTras5Intentos(Client $client, $token): void
    {
    }
    /**
     * @depends testCrearCliente
     * @depends testLogout
     */
    public function testRecuperarContrasena(Client $client, $token): object
    {
        $email = 'g3yuri@gmail.com';
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        PasswordReset::where("email=:email")->deleteAll(['email'=>$email]);
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $res = $client->post('/code/rescue', [
            'form_params' => [
                'email' => $email,
                'test' => true #modo testeo, no envia correo
            ]
        ]);
        $json = $this->assertGo($res);
        $this->assertTrue($json->ok===true, "Debe responser true: ".print_r($json, true));
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $pw = PasswordReset::where("email=:a")->first(['a'=>"g3yuri@gmail.com"]);
        $this->assertTrue($pw!==null, "Debe encontrar el password reset: ");
        $this->assertTrue($pw->email===$email, "Debe encontrar el password reset: ");
        $this->assertTrue(is_string($pw->token), " ");
        $this->assertTrue(v::length(4)->validate($pw->token), "token=".$pw->token);
        return $pw;
    }
    /**
     * @depends testCrearCliente
     * @depends testRecuperarContrasena
     */
    public function testValidarCodeVerify(Client $client, ?object $pw): object
    {
        $pm = [
            'email' => $pw->email,
            'code' => $pw->token
        ];
        $res = $client->post('/code/verify', [
            'form_params' => $pm
        ]);
        $json = $this->assertGo($res);
        $this->assertTrue($json->ok===true, "Debe responser true: ".print_r($json, true).print_r($pm, true));
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $all = PasswordReset::where("email=:email")->get($pm);
        $this->assertTrue($all->count()>0, "Debe haber codigos de recuperacion");
        return $pw;
    }

    /**
     * @depends testCrearCliente
     * @depends testValidarCodeVerify
     */
    public function testCambiarPassword(Client $client, ?object $pw): void
    {
        $email = $pw->email;
        $code = $pw->token;
        $pm = [ 'email' => $email,
                'code' => $code,
                'now' => "nuevopass"
            ];
        $res = $client->post('/code/passchange', [
            'form_params' => $pm
        ]);
        $json = $this->assertGo($res);
        $this->assertTrue($json->ok===true, "Debe responser true: ".print_r($json, true).print_r($pm, true));
        DB::connection()->commit();
        DB::connection()->beginTransaction();
        $us = Usuario::where("email=:email AND password=SHA1(:now)", $pm)->first();
        $this->assertTrue($us!==null, "Debe haberse cambiado el password:".print_r($pm, true));
    }
}
