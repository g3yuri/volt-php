<?php



function go($status,$body){
	if (DB::$_conn) {
		$status ? DB::$_conn->commit() : DB::$_conn->rollback();
	}
	header('Content-Type: application/json');
	die(json_encode((object) array("ok" => $status, "body" => $body, "output" => ob_get_clean())));
}
