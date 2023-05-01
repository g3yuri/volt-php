<?php

namespace Slim\Features;

use \Req;
use \Usuario;
use \DB;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;
use \DateTime;
use \Util;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Colaborador
{
    private static $global_cols = [
            "id" => null,
            "nombre" => null,
            "apellidos" => null,
            "fecha_ingreso" => "Util::change_date_dmy_ymd",
            "area" => null,
            "empresa" => null,
            "cargo" => null,
            "lugar" => null,
            "gp" => null,
            "cr" => null,
            "sq" => null,
            "ec" => null,
            "vm" => null,
            "be" => null,
            "hm" => null,
            "ex" => null,
            "ta" => null,
            "manipula" => null,
            "fecha_emision" => "Util::change_date_dmy_ymd",
            "fecha_caducidad" => "Util::change_date_dmy_ymd",
            "fecha_nac" => "Util::change_date_dmy_ymd",
            "fecha_dni_emision" => "Util::change_date_dmy_ymd",
            "sangre" => null,
            "fecha_emo" => "Util::change_date_dmy_ymd",
            "fecha_emo_venc" => "Util::change_date_dmy_ymd",
            "sctr_pension" => null,
            "sctr_salud" => null,
            "fecha_sctr_venc" => "Util::change_date_dmy_ymd",
            "vacuna_dosis" => null,
            "vacuna_fecha" => "Util::change_date_dmy_ymd",
            "status" => null,
            "unidad" => null,
            "observacion" => null,
            "responsable" => null
        ];
    public static function list()
    {
        $lot = DB::lot("SELECT * FROM colaborador ORDER BY created_at DESC");
        go(true, [
            "alertas" => $lot
        ]);
    }
    public static function diff_days(&$fecha, $label="FECHA")
    {
        strftime();
        if (!$fecha) {
            return array(0,"red","Sin ".$label);
        }
        $hoy = new DateTime("now");
        $ob_fecha = new DateTime($fecha);
        $diff = $hoy->diff($ob_fecha);
        $dias = $diff->days*($diff->invert?-1:1);
        $color = null;
        if ($dias>1 and $dias <=5) {
            $color = "yellow";
        } elseif ($dias <=1) {
            $color = "red";
        } elseif ($dias > 5) {
            $color = "green";
        }
        setlocale(LC_TIME, "es_PE");
        $fecha = strftime("%d %b %Y", $ob_fecha->getTimestamp());
        return array($dias,$color,$dias<=0?"vencido, $dias dias": "$dias dias para vencer");
    }
    public static function venc(Req $req)
    {
        $lot = DB::lot("SELECT COUNT(*) AS CANT, CONCAT(YEAR(fecha_emo_venc),'-',MONTH(fecha_emo_venc)) AS VENC FROM colaborador GROUP BY VENC ORDER BY VENC");
        go(true, $lot);
    }
    public static function registro(Req $req)
    {
        $uuid = $req->param('uuid', null);
        if (!$uuid) {
            go(false, "No hay un uuid, identificado");
        }
        $me = DB::me("SELECT * FROM col_permiso WHERE id=?", [$uuid]);
        $lot = DB::lot("SELECT * FROM col_registro WHERE uuid=? ORDER BY created_at DESC LIMIT 100", [$uuid]);
        go(true, [
            "list" => $lot,
            "me" => $me
        ]);
    }
    public static function query(Req $req)
    {
        $in = $req->filter([
            'from' => v::date(),
            'to' => v::date()
        ]);
        $days = round((strtotime($in->to) - strtotime($in->from))/(60*60*24));
        if ($days>=7) {
            go(false, "El intervalo de dias es mayor  7: $in->from - $in->to = $days dias");
        }
        $lot = DB::lot("SELECT P.*, R.uuid, CONCAT(C.nombre,' ',C.apellidos) AS nombre, R.* FROM col_registro AS R LEFT JOIN colaborador AS C ON R.query=C.id LEFT JOIN col_permiso AS P ON R.uuid=P.id WHERE :from<=DATE(R.created_at) AND DATE(R.created_at)<=:to ORDER BY R.created_at DESC", $in);
        $stat = DB::lot("SELECT DATE(R.created_at) AS fecha, COUNT(R.id) AS cant FROM col_registro AS R WHERE DATE_SUB(NOW(),INTERVAL 15 DAY)<=R.created_at GROUP BY DATE(R.created_at) ORDER BY DATE(R.created_at) DESC");
        go(true, [
            "list" => $lot,
            "from" => $in->from,
            "to" => $in->to,
            "stat" => $stat
        ]);
    }
    public static function permiso(Req $req)
    {
        $lot = DB::lot("SELECT R.*, P.*, R.uuid AS id, COUNT(R.id) AS cant FROM col_registro AS R LEFT JOIN col_permiso AS P ON R.uuid=P.id GROUP BY R.uuid");
        go(true, $lot);
    }
    public static function permiso_store(Req $req)
    {
        $in = $req->filter([
            "id" => v::alnum(5),
            "nombre" => v::optional(v::alnum(1))
        ]);
        DB::query("INSERT INTO col_permiso (id,nombre) VALUES (:id,:nombre)
				ON DUPLICATE KEY UPDATE id=:id, nombre=:nombre", $in);
        self::permiso($req);
    }
    public static function info(Req $req)
    {
        $in = $req->filter([
            'id' => v::alnum(),
            'uuid' => v::optional(v::alnum(5))
        ]);
        if (!isset($in->uuid)) {
            $in->uuid = '999999999';
        }
        $perm = DB::me("SELECT * FROM col_permiso WHERE id=?", [$in->uuid]);
        //if ($perm and $perm['status']=='normal') {
        $me = DB::me("SELECT * FROM colaborador WHERE id=?", [$in->id]);
            
        list($me["fecha_emo_dias"], $me["fecha_emo_color"], $me["fecha_emo_info"]) = self::diff_days($me["fecha_emo_venc"], "EMO");
        list($me["fecha_sctr_dias"], $me["fecha_sctr_color"], $me["fecha_sctr_info"]) = self::diff_days($me["fecha_sctr_venc"], "SCTR");

        $cols = array_flip(self::$global_cols);
        $labels = [];
        foreach ($me as $key => $value) {
            $labels[$key] = strtoupper($cols[$key]);
        }
        unset($me['created_at']);
        unset($me['updated_at']);
        DB::query("INSERT INTO col_registro (uuid, query) VALUES (?,?)", [$in->uuid,$in->id]);
        go(true, $me);
        // } else {
        // 	$tag = 'bloqueado';
        // 	DB::query("INSERT INTO col_registro (uuid, query,tag) VALUES (?,?,?)",[$in->uuid,$in->id,$tag]);
        // 	DB::commit(); //EL GO FALSE, HACE ROLLBACK
        // }
        //go(false,"No tienes permitido");
    }
    public static function upload(Req $req)
    {
        $req->file('file')->store('colaborador/input.csv');
        $path = Storage::path_local('colaborador/input.csv');
        
        DB::query("TRUNCATE colaborador");
        $count = 0;
        $fallos = 0;
        $line = 1; // comienza del head
        $fallos_all = [];
        $last_error = null;
        foreach (Util::import_csv($path, self::$global_cols, ";") as $values) {
            $line++;
            try {
                Util::import_db_insert("colaborador", $values);
                $count++;
            } catch (\Exception $e) {
                //go(false,"INSERT INTO colaborador ($fields) VALUES ($pfields)");
                $fallos++;
                $fallos_all[] = $line;
                $fallos_all = array_slice($fallos_all, -5);
                $last_error = $e;
            }
        }
        go(true, "Se ingreso: $count, fallo: $fallos = ".implode(",", $fallos_all));
    }
    public static function store(Req $req)
    {
        $in = $req->filter([
            'nivel' => v::intVal(),
            'info' => v::length(2)
        ]);
        $id = intval($req->param('id', null));
        if ($id) {
            $in->id = $id;
            DB::query("UPDATE colaborador SET nivel=:nivel, info=:info WHERE id=:id", (array)$in);
        } else {
            DB::query("INSERT INTO colaborador (nivel, info)
				VALUES (:nivel,:info)", (array)$in);
            $id = DB::last_insert_id();
        }
        go(true, $id);
    }
}
