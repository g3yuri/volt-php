<?php
namespace lib;

use \Util;
use \Session;
use \Req;
use \Usuario;
use \Lib\Storage;

class App
{
    private $cf_app;
    private $cf_session;
    private static $cf_session_domain;
    private static $cf_host;
    private $cf_storage;
    private $cf_db;
    private $cf_mail;
    private $config;
    //Al constructor lo llama lib/boot.php
    public function __construct($config)
    {
        /*
        Se esta ocultando los mensajes de deprecated que arroja el router Klein, esto se debe actualizar a otro router (posiblemente FastRouter)
        */
        error_reporting(E_ALL ^ E_DEPRECATED);
        /*
        HTTP_ORIGIN muestra el dominio en peticiones cruzadas, no siempre los muestra
        Aqui: https://stackoverflow.com/questions/10717249/get-current-domain
        indica que es mejor SERVER_NAME ó REQUEST_URI (esto muestra lo que esta despues del host)
        */
        self::$cf_host = $_SERVER['SERVER_NAME'];
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            self::$cf_host = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        }

        $this->config = $config;
        $this->cf_app = $config["app"];
        $this->cf_session = $config["session"];
        $dm = $config["session"]["domain"];
        if (str_contains($dm, '%')) {
            $dm = str_replace('%', self::$cf_host, $dm);
        }
        /*
        Cookies (para tener en cuenta):
        ========

        Cuando hay 2 cookies con los dominios:
        .slim.gmi.gd.pe   <----- este se toma como prioridad
        .gmi.gd.pe    <---- esto se necesita para el login
        */
        self::$cf_session_domain = $dm;

        $this->cf_storage = $config["storage"];
        $this->cf_db = $config["db"];
        $this->cf_mail = $config["mail"];
    }
    public static function host() : string
    {
        return self::$cf_host;
    }
    public static function session_domain() : string
    {
        return self::$cf_session_domain;
    }
    public static function attemp($email, $pwd, $remember = false)
    {
        $token = null;
        $us = Usuario::where('EMAIL = :email AND PASSWORD = SHA1(:pwd)')->first([
            'email' => $email,
            'pwd' => $pwd
        ]);
        if ($us instanceof Usuario) {
            if ($remember) {
                $token = Util::gen_token();
                Session::where("user_id=:a")->get(['a'=>$us->id()])->deleteAll();
                $id = Session::create([
                    'id' => $token,
                    'user_id' => $us->id(),
                    'payload' => 'value',
                    'ip_address' => Req::ip(),
                    'last_activity' => time()
                ]);

                $host = self::host();
                $domain = null;
                // CUANDO EL DOMAIN EN SET-COOKIE, ES LOCALHOST NO FUNCIONA
                if ($host != 'localhost') {
                    $domain = self::session_domain();
                }

                Req::set_cookie(Req::$session_name, $token, [
                    "max-age" => 3*24*60*60/*seg*/,
                    "path" => '/',
                    "domain" => $domain,
                    "secure" => true,
                    "samesite" => "None",
                    "httponly" => true]);
            }
            return ["token"=>$token,"user"=>$us, "roles" => $us->roles()];
        }
        return null;
    }
    public function terminate()
    {
        $allow = $this->cf_app['allow_hosts'];
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['SERVER_NAME'];
        $origin_host = parse_url($origin, PHP_URL_HOST);

        if (in_array($origin_host, $allow) or in_array('*', $allow)) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
            header('Access-Control-Allow-Headers: Content-Type, X-Xsrf-Token');
        }

        ob_start();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            //PREVIENE LAS SOLICITUDES CON METODO OPTIONS
            //YA QUE LLAMARIA 02 VECES
            header('Allow: GET, POST, PUT, DELETE');
            die();
        }
        $target = isset($_SERVER['REQUEST_URI'])?trim($_SERVER['REQUEST_URI']):'';
        if (substr($target, 0, 9)=='/storage/') {
            $path = substr($target, 9);
            Storage::go($path);
        }
        \Router::run();
    }
}
