<?php

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\EventListener\MessageListener;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\SentMessage;
use Google\Service\Gmail\Message;
use Lib\Storage;

final class GmailTransport extends AbstractTransport
{
	private static $client = null;
	private static $path_credential = "setting/gmailer/credentials.json";
	private static $path_token = "setting/gmailer/token";

    protected function doSend(SentMessage $message): void
    {
		$client = self::getClientOrFail();
		$service = new Google_Service_Gmail($client);
		$raw = $message->toString();
		$message = new Message;
		$message->setRaw(base64_encode($raw));
		$results = $service->users_messages->send('me',$message);
		print_r($results);
    }

	private static function getUrlAuth() {
		return self::boot()->createAuthUrl();
	}

	private static function setCode($authCode) {
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
        $client->setAccessToken($accessToken);
        if (array_key_exists('error', $accessToken)) {
            throw new Exception(join(', ', $accessToken));
        }
	}
	private static function boot() : Google_Client{
		if (self::$client===null){
		    self::$client = new Google_Client();
		    self::$client->setApplicationName('Gmail API PHP Quickstart');
		    self::$client->setScopes(Google_Service_Gmail::GMAIL_COMPOSE);
		    self::$client->setAuthConfig(Storage::path_local(self::$path_credential));
		    self::$client->setAccessType('offline');
		    self::$client->setPrompt('select_account consent');
		}
		return self::$client;
	}
	private static function getClientOrFail() {
		$client = self::boot();

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
	        	throw new Exception("El token es invalido, necesita recrearlo");
	        }
	        if (!file_exists(dirname($tokenPath))) {
	            mkdir(dirname($tokenPath), 0700, true);
	        }
	        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
	    }
	    return $client;
	}

    public function __toString(): string {
        return 'gmail-transport://';
    }
}

class Email extends TemplatedEmail {
	public function __construct() {
		parent::__construct();
	}
	public function send() {
		Sender::$mailer->send($this);
	}
}

class Sender {
	public static $mailer = null;
	protected $_tpl = null;
	public function __construct($transport=null){
		self::boot();
		$this->_tpl = new Email();
	}
	public function __call($name,$arguments){
		return $this->_tpl->{$name}(...$arguments);
	}
	public function instance() {
		return $this->_tpl;
	}
	public static function __callStatic($name,$arguments){
		return (new static)->$name(...$arguments);
	}
	public static function boot() : Mailer{
		if (self::$mailer==null){
			$loader = new \Twig\Loader\FilesystemLoader(__DIR__ .'/../templates');
			$dir_cache = __DIR__.'/../storage/cache';
			$twig = new \Twig\Environment($loader,[
				// 'cache' => $dir_cache
			]);

			$twig->addExtension(new \Twig\Extra\Markdown\MarkdownExtension());

			$twig->addRuntimeLoader(new class implements \Twig\RuntimeLoader\RuntimeLoaderInterface {
			    public function load($class) {
			        if (\Twig\Extra\Markdown\MarkdownRuntime::class === $class) {
			            return new \Twig\Extra\Markdown\MarkdownRuntime(
			            	new \Twig\Extra\Markdown\DefaultMarkdown()
			            );
			        }
			    }
			});

			$messageListener = new MessageListener(null, new BodyRenderer($twig));
			$eventDispatcher = new EventDispatcher();
			$eventDispatcher->addSubscriber($messageListener);
			//$transport = Transport::fromDsn('smtp://hola@gd.pe:04140089eE@mail.gd.pe:587', $eventDispatcher);
			$transport = new GmailTransport($eventDispatcher);

			self::$mailer = new Mailer($transport, null, $eventDispatcher);
		}
		return self::$mailer;
	}
}