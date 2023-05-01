<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Model\Postulante;
use Respect\Validation\Validator as v;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\EventListener\MessageListener;
use Symfony\Component\Mailer\Transport\AbstractTransport;

use \Lib\Storage;

class Gmailer {
	private static $path_credential = "setting/gmailer/credentials.json";
	private static $path_token = "setting/gmailer/token";
	private static $client;

	private static function _client() : \Google_Client{
		if (self::$client===null){
		    self::$client = new \Google_Client();
		    self::$client->setApplicationName('Gmail API PHP Quickstart');
		    self::$client->setScopes(\Google_Service_Gmail::GMAIL_COMPOSE);
		    self::$client->addScope(\Google_Service_Oauth2::USERINFO_EMAIL);
		    $path = Storage::path_local(self::$path_credential);
		    self::$client->setAuthConfig($path);
		    self::$client->setAccessType('offline');
		    self::$client->setPrompt('select_account consent');
		}
		return self::$client;
	}
	public static function redirect(Req $req) {
		$code = $req->param('code',null);
		if (!$code) {
			go(false,"No existe codigo");
		}
		$client = self::_client();
		$client->authenticate($code);
		$access_token = $client->getAccessToken();
		file_put_contents(Storage::path_local(self::$path_token), json_encode($access_token));
		go(true,$access_token);
	}
	public static function _get_token() {
		$client = self::_client();
		$tokenPath = Storage::path_local(self::$path_token);

	    if (file_exists($tokenPath)) {
    		$token_string = file_get_contents($tokenPath);
    		if ($token_string) {
		        $accessToken = @json_decode($token_string, true);
		        $client->setAccessToken($accessToken);
    		}
	    }

	    if ($client->isAccessTokenExpired()) {
	        if ($client->getRefreshToken()) {
	            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
	        } else {
	        	return null;
	        }
	        $access_token = ($client->getAccessToken());
	        file_put_contents($tokenPath, json_encode($access_token));
	    } else {
	    	$access_token = ($client->getAccessToken());
	    }
	    return $access_token;
	}
	public static function _get_auth_url($client = null) {
		if ($client === null) {
			$client = self::_client();
		}
		$client->setRedirectUri('https://slim.gmi.gd.pe/mailer/gmail/redirect');
		$auth_url = $client->createAuthUrl();
		return filter_var($auth_url, FILTER_SANITIZE_URL);
	}
	public static function google_uri(Req $req) {
		$force = $req->param("force", false);
		$access_token = self::_get_token();
		if ($force or !$access_token) {
			go(true, [
				"auth_url" => self::_get_auth_url()
			]);
	    }
	    go(true,[
	    	"token" => $access_token
	    ]);
	}
	public static function test_email(Req $req) {
		Storage::write("setting/task/output.log","");
		go(true,"Creado el archivo");
	   $ob = Labor::ext_resumen(-3);
	   array_shift($ob->reporte);
	   $lot = $ob->reporte;

	   foreach($lot as &$el) {
			$day = strtotime($el->day);
			$el->dia = date("d",$day);
			$el->mes = strtoupper(date("M",$day));
			$el->cuerpos_dia = $el->cuerpos_dia ? $el->cuerpos_dia['cant'] : 0;
			$el->cuerpos_dia_color = $el->cuerpos_dia ? "#4ade80" : "#d1d5db";
			$el->cuerpos_noche = $el->cuerpos_noche ? $el->cuerpos_noche['cant'] : 0;
			$el->cuerpos_noche_color = $el->cuerpos_noche ? "#4ade80" : "#d1d5db";
			$el->vetas_dia = $el->vetas_dia ? $el->vetas_dia['cant'] : 0;
			$el->vetas_dia_color = $el->vetas_dia ? "#4ade80" : "#d1d5db";
			$el->vetas_noche = $el->vetas_noche ? $el->vetas_noche['cant'] : 0;
			$el->vetas_noche_color = $el->vetas_noche ? "#4ade80" : "#d1d5db";
	   }

	   $mail = (new \Sender())
	       ->from('intellicorp.app@gmail.com')
	       ->to('g3yuri@gmail.com')
	       ->subject('Resumen de reportes SSO')
	       ->htmlTemplate('gmi.sso.resumen.html.twig')
	       ->context([
	         "lot" => $lot,
	         "cant" => count($lot),
	         "text" => 'resumen'
	       ])
	       ->send();
	    go(true,$mail );
	}
	public static function init(Req $req) {
		$client = self::_client();
		$access_token = self::_get_token();
		$oauthService = new \Google_Service_Oauth2($client);
		$userInfo = null;
		try {
			$userInfo = $oauthService->userinfo_v2_me->get();
		} catch (\Exception $e) {
			go(true,[
				"auth_url" => self::_get_auth_url()
			]);
		}


		go(true,[
			"credential" => Storage::fileExists(self::$path_credential) ? self::$path_credential : false,
			"token" => $access_token,
			"user_info" => $userInfo
		]);
	}
	public static function store_credential(Req $req) {
		$fs = $req->file("file");
		if ($fs) {
			$fs->store(self::$path_credential);
			self::init($req);
		}
		go(false,"Failed");
	}
}