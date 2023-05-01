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

final class LaborTest extends TestCase
{
    use Test\Assertions\AssertGo;

    public function testCrearCliente() : vtClient
    {
        $client = new vtClient($this);
        $client->login('g3yuri@gmail.com', '123456');
        return $client;
    }
}
