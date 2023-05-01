<?php
use \Klein\Request;
use \Lib\Storage;
use \Lib\Validator;

class FileUpload
{
    private $file;
    public $type;
    public $size;
    public function __construct($file)
    {
        $this->file = $file;
    }
    public function path() : ?string
    {
        return $this->file['tmp_name'];
    }
    public function name() : ?string
    {
        return $this->file['name'];
    }
    public function md5() : ?string
    {
        return md5_file($this->file['tmp_name']);
    }
    public function size() : int
    {
        return $this->file['size'];
    }
    public function type() : ?string
    {
        return $this->file['type'];
    }
    public function ext() : ?string
    {
        $type = $this->file['type'];
        $trans = [
            "image/png" => "png",
            "image/jpeg" => "jpg",
            "image/gif" => "gif"
        ];
        if (array_key_exists($type, $trans)) {
            return $trans[$type];
        }
        return "";
    }
    public function store($path) : void
    {
        $file = $this->file;
        $name = $file['name'];
        $this->type = $file['type']; // mimetype
        $tmp = $file['tmp_name'];
        $error = $file['error']; // 0 if not problem
        if ($error!==0) {
            throw new Exception("Error Storage.store $error");
        }
        $this->size = $file['size']; // int
        $res = fopen($tmp, 'rb');
        if ($res) {
            Storage::putStream($path, $res);
            fclose($res);
        }
    }
}

class Req
{
    public static $json = null;
    public static $session_name = "session_name";
    private $request;
    private static $user = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    public static function json()
    {
        if (self::$json !== null) {
            return self::$json;
        }
        if (isset($_SERVER["CONTENT_TYPE"])) {
            if (false !== stripos($_SERVER["CONTENT_TYPE"], "application/json")) {
                self::$json = json_decode(file_get_contents('php://input')) ?? (object)[];
            }
        }
        return self::$json;
    }
    public static function ip() : string
    {
        return $_SERVER['REMOTE_ADDR'];
    }
    public function cookie($name) : ?string
    {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }
    public function method() :?string
    {
        $re = $this->instance();
        if ($re) {
            return $re->method();
        }
        return null;
    }
    public static function user() : ?Usuario
    {
        if (self::$user !== null) {
            return self::$user;
        }
        // go(false,[
        // 	"name" => self::$session_name,
        // 	"cookie" => $_COOKIE[self::$session_name]
        // ]);
        if (isset($_COOKIE[self::$session_name])) {
            $token = $_COOKIE[self::$session_name];
            $ses = Session::find($token);
            if ($ses instanceof Session) {
                self::$user = $ses->user();
                return self::$user;
            }
        }
        return null;
    }
    public function instance()
    {
        return $this->request;
    }
    public function file($name) : ?FileUpload
    {
        return isset($_FILES[$name])?new FileUpload($_FILES[$name]):null;
    }
    public function param($key, $default = null)
    {
        //NO CONSIDERA EL JSON
        //return $this->request->param($key,$default);
        $val_and_json = $this->params();
        return isset($val_and_json[$key]) ? $val_and_json[$key]:$default;
    }
    public function params()
    {
        return array_merge(
            (array)self::json(),
            $this->request->paramsGet()->all(),
            $this->request->paramsPost()->all(),
            $this->request->paramsNamed()->all()
        );
    }
    public function filter(array $cons, ?array $input = null) : object
    {
        $res = [];
        if ($input === null) {
            $input = $this->params();
        }
        try {
            foreach ($cons as $key => $valuer) {
                // if (!isset($input[$key])) {
                // 	go(false,"No se tiene el parametro '$key'");
                // }
                $x = $input[$key];
                $valuer->assert($x);
                $res[$key] = $x;
            }
        } catch (\Respect\Validation\Exceptions\ValidationException $e) {
            throw new Exception($e->getFullMessage());
        }
        return (object)$res;
    }
    public function filterRules(array $cons, ?array $input = null) : object
    {
        $res = [];
        if ($input === null) {
            $input = $this->params();
        }
        try {
            $res = Validator::filter($cons, $input);
        } catch (\Respect\Validation\Exceptions\ValidationException $e) {
            throw new Exception($e->getFullMessage());
        }
        return (object)$res;
    }
    public function check(array $cons, ?array $input = null) : object
    {
        $res = [];
        if ($input === null) {
            $input = $this->params();
        }
        try {
            foreach ($input as $key => $val) {
                if (isset($cons[$key])) {
                    $val->assert($val);
                }
                $res[$key] = $val;
            }
        } catch (\Respect\Validation\Exceptions\ValidationException $e) {
            throw new Exception($e->getFullMessage());
        }
        return (object)$res;
    }
    public static function set_cookie($name, $value=null, ?array $info = [])
    {
        $cookie = rawurlencode($name) . '=' . rawurlencode($value);
        $attributes = array();
        if (isset($info["max-age"])) {
            $maxage = intval($info["max-age"]);
            $attributes[] = 'Expires='.date(DATE_COOKIE, $maxage > 0 ? time()+$maxage : 0);
            $attributes[] = 'Max-Age='.$maxage;
        }
        if (isset($info["path"])) {
            $attributes[] = 'Path='.$info["path"];
        }
        if (isset($info["domain"])) {
            $attributes[] = 'Domain='.rawurlencode($info["domain"]);
        }
        if (isset($info["secure"]) and $info["secure"] === true) {
            $attributes[] = 'Secure';
        }
        if (isset($info["samesite"])) {
            $attributes[] = 'SameSite='.ucfirst(strtolower($info["samesite"]));
        }

        if (isset($info["httponly"]) and $info["httponly"] === true) {
            $attributes[] = 'HttpOnly';
        }
        header('Set-Cookie: '.implode('; ', array_merge(array($cookie), $attributes)), false);
    }
}
