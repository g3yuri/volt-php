<?php

use PHPUnit\Framework\TestCase;

use Respect\Validation\Validator as v;

final class SessionTest extends TestCase
{
    static $validator = null;

    public function testCreaValidador(): void {
        self::$validator = v::length(20)->alnum();
        $this->assertFalse(self::$validator->validate(''));
        $this->assertTrue(self::$validator->validate(Util::gen_token()));
    }
    /**
     * @depends testCreaValidador
     */
    public function testGeneraTokenValidos(): string {
        $store = [];
        for($i=0;$i<20;$i++){
            $token = Util::gen_token();
            $this->assertTrue(self::$validator->validate($token));
            $this->assertFalse(in_array($token,$store));
            $store[] = $token;
        }
        return $store[0];
    }
    /**
     * @depends testGeneraTokenValidos
     */
    public function testCreaSession(string $token): void {
        $this->assertTrue(self::$validator->validate($token));
        $user = Usuario::find(1);
        $this->assertInstanceOf(Usuario::class,$user);
        $id = Session::create([
            'id' => $token,
            'user_id' => $user->id(),
            'payload' => 'value',
            'ip_address' => '127.0.0.1',
            'last_activity' => time()
        ]);
        $this->assertTrue($id==$token);
        $ses = Session::find($token);
        $this->assertInstanceOf(Session::class,$ses);
        $this->assertTrue($ses->id==$token);
    }
}