<?php

use PHPUnit\Framework\TestCase;

final class DbCollectionTest extends TestCase
{
	private function create(string $id) : void {
		Session::create([
			'id' => $id,
			'user_id' => 7777777,
			'payload' => 'testing',
			'ip_address' => '127.0.0.1',
			'last_activity' => time()
		]);
	}
    public function testIngresandoDatos() : void
    {
    	Session::deleteId(['test-1','test-2']);
    	$this->create('test-1');
    	$this->create('test-2');
    	$s = Session::find('test-1');
    	$this->assertTrue($s->exists());
    	$s = Session::find('test-2');
    	$this->assertTrue($s->exists());
    	$coll = Session::where('user_id=:a')->get(['a'=>7777777]);
    	$this->assertTrue($coll->count()>=2);
    	$coll->deleteAll();
    	DB::connection()->commit(); DB::connection()->beginTransaction();
    	$coll = Session::where('user_id=:a')->get(['a'=>7777777]);
    	$this->assertTrue($coll->count()==0, "La cantidad es:".$coll->count());
    }
}