<?php

use \Klein\Klein;

//use function \Slim\Features\go;

class Router
{
    public static ?Klein $router = null;
    private static $middleware_curr = null;
    private static $middleware_alias = [];
    private static $client_ip = null;
    public static function get_client_ip() : ?string
    {
        if (self::$client_ip !== null) {
            return self::$client_ip;
        }
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        }
        
        self::$client_ip = $ipaddress;
        return $ipaddress;
    }
    public static function default() : Klein
    {
        if (self::$router===null) {
            self::$router = new Klein();
            // self::$router->respond(function ($request, $response) {
            // 	echo "onInit:";
            // });
            self::$router->onError(function ($klein, $msg, $type, $err) {
                //go(false,["msg" => "No se encontro ninguna coincidencia", "_SERVER"=> $_SERVER]);
                //go(false,["klein"=>$klein, "msg" => "OnError","o"=>$err_msg]);
                throw $err;

                //go(false,"onError:".print_r($msg,true).ob_get_clean());
                //go(false,get_class($err));
            });
            self::$router->onHttpError(function (
                $status,
                $klein,
                $matched,
                $methods_matched
            ) {
                //go(false,["msg" => "No se encontro ninguna coincidencia", "_SERVER"=> $_SERVER]);
                //go(false,["klein"=>$klein, "msg" => "OnHttpError","o"=>$err_msg]);
                $url = $klein->request()->uri();
                switch ($status) {
                    case 400:
                        go(false, "HttpError $status: ($url)");
                        break;
                    case 404:
                        go(false, "HttpError $status: ($url) No se encontro la ruta");
                        break;
                    case 405:
                        go(false, "HttpError $status: ($url) El metodo (".implode(",", $methods_matched).") de la solicitud no es permitido");
                        break;
                    case 500:
                        go(false, "HttpError $status: ($url)");
                        break;
                    default:
                        go(false, "HttpError $status: ($url) Algun error no conocido");
                }
            });

            // function manejador_excepciones($excepcion) {
            //   go(false,"Error no manejado (${gettype($excepcion)})".$excepción->getMessage());
            // }
            // set_exception_handler('manejador_excepciones');

            self::$router->afterDispatch(function () {
                echo "iniciando middlelware";
            });
        }
        return self::$router;
    }
    public static function middlelware($mids, callable $call) : void
    {
        $previo = self::$middleware_curr;
        self::$middleware_curr = $mids;
        $call();
        self::$middleware_curr = $previo;
    }
    public static function run()
    {
        $router = self::default();

        try {
            $router->dispatch();
        } catch (Exception $e) {
            //throw $e;
            go(false, "XTerm:".$e->getMessage());
        }
    }
    public static function get($path, $callback) : void
    {
        self::route("GET", $path, $callback);
    }
    public static function post($path, $callback) : void
    {
        self::route("POST", $path, $callback);
    }
    public static function put($path, $callback) : void
    {
        self::route("PUT", $path, $callback);
    }
    public static function delete($path, $callback) : void
    {
        self::route("DELETE", $path, $callback);
    }
    public static function route($method, $path, $callback) : void
    {
        $router = self::default();
        if (!is_callable($callback)) {
            throw new Exception("No se puede llamar a la funcion".print_r($callback, true));
        }
        $middlelware = self::$middleware_curr;
        // if ($path=='/test') {
        // 	print_r($middlelware);
        // }
        $router->respond($method, $path, function ($request, $response) use ($callback, $middlelware) {
            $req = new Req($request);
            if ($middlelware==null) {
                call_user_func($callback, $req);
            }
            if (is_callable($middlelware)) {
                $middlelware($req, function () use ($callback, $req) {
                    call_user_func($callback, $req);
                });
            } elseif (is_array($middlelware)) {
                $reversed = array_reverse($middlelware);
                $call_iterator = null; #ES NECESARIO ASIGNARLO ANTES CON NULL PARA QUE RECONOSCA LA VARIABLE
                $call_iterator = function () use (&$reversed, $req, &$call_iterator) {
                    $mid = array_pop($reversed);
                    if ($mid!==null) {
                        if (is_string($mid)) {
                            if (!isset(self::$middleware_alias[$mid])) {
                                throw new Exception("No se encontro el alias del middlelware: '$mid'");
                            }
                            $mid = self::$middleware_alias[$mid];
                        }
                        if (is_callable($mid)) {
                            call_user_func($mid, $req, $call_iterator);
                        } else {
                            throw new Exception("No se encontro el middlelware");
                        }
                    }
                };
                $call_iterator();
            }
        });
    }
    public static function mount($path, $callback) : void
    {
        $router = self::default();
        $router->with($path, $callback);
    }
}
