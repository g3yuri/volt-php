<?php

namespace Slim\Features;

use \Req;
use \Util;
use \DB;
use Respect\Validation\Validator as v;
use \Lib\Storage;
use \Lib\Entity;

class Obser extends Entity
{
    static ?string $table = "observacion";
    public function createValidator() : array
    {
        return [
            'id' => v::optional(v::intVal()),
            "nivel" => v::optional(v::alnum(' .')),
            "labor" => v::alnum(" "),
            "zona" => v::in(['cuerpos','vetas','nivel23','superficie']),
            "area" => v::optional(v::alnum(' .')),
            "area_rep" => v::optional(v::alnum(' .')),
            "riesgo" => v::alnum(" "),
            "causa_id" => v::alnum(" "),
            "fecha" => v::date(),
            "turno" => v::in(['dia','noche']),
            "tipo" => v::alnum(" "),
            "info" => v::length(2),
            "fecha_accion" => v::alnum(" ","-"),
            "accion" => v::length(2),
            "user_id" => v::intVal()
        ];
    }
    public function store_before() : bool {
        if (!$this->id()) {
            $user = Req::user();
            if (!$user) {
                throw new \Exception("No se puede guardar la observación sin usuario");
            }
            $this->user_id = $user->id();
            $this->area_rep = $user->area;
        }
        return false;
    }
}
class Labor
{
    public static function obs_save_v2(Req $req) : void
    {
        $js = json_decode($req->param("data", null));
        $obs = new Obser();
        $obs->merge((array)$js);
        //go(false, $obs->log());
        $obs->store();
        $fs = $req->file("file");
        if ($fs) {
            $rel = "/obs/$obs->id";
            $fs->store($rel);
            $obs->rel = $rel;
            $obs->store();
        }
        $acfs = $req->file("accion_file");
        if ($acfs) {
            $rel = "/obs/$obs->id-levantado";
            $acfs->store($rel);
            $obs->accion_rel = $rel;
            $obs->store();
        }
        $obs->fetch();
        go(true, $obs);
    }
    public static function obs_save(Req $req)
    {
        $js = json_decode($req->param("data", null));

        unset($js->updated_at);
        unset($js->created_at);
        unset($js->is_trash);

        if (isset($js->id)) {
            $id = $js->id;
            //go(false, $js);
            self::_store_obs($js);
        } else {
            unset($js->id);
            $js->user_id = $req->user()->id();
            $js->area_rep = $req->user()->area;

            self::_store_obs($js);
            $id = DB::last_insert_id();
        }
        $me = DB::me("SELECT * FROM observacion WHERE id=?", [$id]);

        $fs = $req->file("file");
        if ($fs) {
            $rel = "/obs/$id";
            $fs->store($rel);
            DB::query("UPDATE observacion SET rel=? WHERE id=?", [$rel,$id]);
        }
        $acfs = $req->file("accion_file");
        if ($acfs) {
            $rel = "/obs/$id-levantado";
            $acfs->store($rel);
            DB::query("UPDATE observacion SET accion_rel=? WHERE id=?", [$rel,$id]);
        }
        go(true, $me);
    }
    public static function _store_obs($in)
    {
        /*
        {"id":"3753","[nivel]":"20A","[labor]":"BP 100","[zona]":"vetas","[riesgo]":"alto","[fecha]":"2023-03-16","turno":"noche","tipo":null,"info":"Se evidencia roca suelta en labor, se realizó una campaña de desate de rocas","fecha_accion":null,"accion":null,"area":"VETAS 20 ESPERANZA"}
         */
        // las siguientes lineas contienen un null a las variables que no esten seteadas
        $props = ["nivel", "labor", "zona", "area", "area_rep", "riesgo", "causa_id", "fecha", "turno", "tipo", "info", "fecha_accion", "accion", "user_id"];
        foreach ($props as $pr) {
            if (!isset($in->{$pr})) {
                $in->{$pr} = null;
            }
        }
        if ($in->id>0) {
            DB::query("UPDATE observacion SET nivel=:nivel, labor=:labor, zona=:zona, area=:area, riesgo=:riesgo, causa_id=:causa_id, fecha=:fecha, turno=:turno, tipo=:tipo, info=:info, fecha_accion=:fecha_accion, accion=:accion WHERE id=:id", $in);
        } else {
            unset($in->id);
            DB::query("INSERT INTO observacion (nivel, labor, zona, area, area_rep, riesgo, causa_id, fecha, turno, tipo, info, fecha_accion, accion, user_id) VALUES (:nivel, :labor, :zona, :area, :area_rep, :riesgo, :causa_id, :fecha, :turno, :tipo, :info, :fecha_accion, :accion, :user_id)", $in);
        }
    }
    public static function obs_drop(Req $req)
    {
        $in = $req->filter([
            "id" => v::intVal()
        ]);
        DB::query("DELETE FROM observacion WHERE id=:id", $in);
        go(true, "OK");
    }
    public static function obs(Req $req)
    {
        $in = $req->filter([
            "id" => v::intVal()
        ]);
        $me = DB::me("SELECT A.*, B.NOMBRES, B.APELLIDOS FROM observacion A LEFT JOIN usuario AS B ON A.user_id=B.ID_USUARIO WHERE id=:id", $in);
        
        if ($me['rel']) {
            $me['rel'] = Storage::url($me['rel']);
        }
        go(true, $me);
    }
    public static function day(Req $req)
    {
        $in = $req->filter([
            "zona" => v::in(['cuerpos','vetas','nivel23','superficie']),
            "turno" => v::in(['dia','noche']),
            "fecha" => v::date()
        ]);
        $lot = DB::lot("SELECT A.*, B.NOMBRES, B.APELLIDOS FROM observacion AS A LEFT JOIN usuario AS B ON A.user_id=B.ID_USUARIO WHERE A.zona=:zona AND A.turno=:turno AND A.fecha=:fecha", $in);
        foreach ($lot as &$el) {
            if ($el['rel']) {
                $el['rel'] = Storage::url($el['rel']);
            }
        }
        go(true, $lot);
    }
    public static function query(Req $req)
    {
        $in = $req->filter([
            "nivel" => v::optional(v::alnum(' .')),
            "area" => v::optional(v::alnum(' .')),
            "area_rep" => v::optional(v::alnum(' .')),
            "fecha" => v::optional(v::date()),
            "zona" => v::optional(v::alnum(' .'))
        ]);
        
        $props = ["nivel", "area", "area_rep", "fecha", "zona"];
        $where = [];
        foreach ($props as $pp) {
            if ($in->{$pp}) {
                $where[] = "A.$pp=:$pp";
            } else {
                unset($in->{$pp});
            }
        }
        if (count($where)) {
            $where = implode(" AND ", $where);
        } else {
            $where = "1";
        }
        
        //go(false,$where);

        $lot = DB::lot("SELECT A.*, B.NOMBRES, B.APELLIDOS FROM observacion AS A LEFT JOIN usuario AS B ON A.user_id=B.ID_USUARIO WHERE $where ORDER BY A.fecha DESC LIMIT 30", $in);
        foreach ($lot as &$el) {
            if ($el['rel']) {
                $el['rel'] = Storage::url($el['rel']);
            }
        }
        go(true, $lot);
    }
    public static function racs(Req $req)
    {
        $in = $req->filter([
            "tipo" => v::in(['seguimiento','pendientes','levantados'])
        ]);
        $q = (object)[];
        if ($in->tipo==='seguimiento') {
            $q->user_id = $req->user()->id();
            $lot = DB::lot("SELECT A.*, B.NOMBRES, B.APELLIDOS FROM observacion AS A LEFT JOIN usuario AS B ON A.user_id=B.ID_USUARIO WHERE A.user_id=:user_id ORDER BY A.fecha DESC, A.accion DESC LIMIT 30", $q);
        } elseif ($in->tipo==='pendientes') {
            $q->area = $req->user()->area;
            $lot = DB::lot("SELECT A.*, B.NOMBRES, B.APELLIDOS FROM observacion AS A LEFT JOIN usuario AS B ON A.user_id=B.ID_USUARIO WHERE A.area=:area AND (A.accion IS NULL OR A.accion = '') ORDER BY A.fecha DESC LIMIT 30", $q);
        } elseif ($in->tipo==='levantados') {
            $q->area = $req->user()->area;
            $lot = DB::lot("SELECT A.*, B.NOMBRES, B.APELLIDOS FROM observacion AS A LEFT JOIN usuario AS B ON A.user_id=B.ID_USUARIO WHERE A.area=:area AND A.accion IS NOT NULL ORDER BY A.fecha DESC LIMIT 30", $q);
        }

        foreach ($lot as &$el) {
            if ($el['rel']) {
                $el['rel'] = Storage::url($el['rel']);
            }
        }
        go(true, $lot);
    }
    public static function super_pendiente(Req $req)
    {
        $area = $req->user()->area;
        $lot = DB::lot("SELECT A.*, B.NOMBRES, B.APELLIDOS FROM observacion AS A LEFT JOIN usuario AS B ON A.user_id=B.ID_USUARIO WHERE A.area=? AND A.fecha_accion IS NULL AND (A.accion IS NULL OR LENGTH(A.accion)=0) ORDER BY A.fecha DESC", [$area]);
        foreach ($lot as &$el) {
            if ($el['rel']) {
                $el['rel'] = Storage::url($el['rel']);
            }
        }
        go(true, [
            "pendiente" => $lot
        ]);
    }
    public static function super_levantado(Req $req)
    {
        $area = $req->user()->area;
        $lot = DB::lot("SELECT A.*, B.NOMBRES, B.APELLIDOS FROM observacion AS A LEFT JOIN usuario AS B ON A.user_id=B.ID_USUARIO WHERE A.area=? AND A.fecha_accion IS NOT NULL AND LENGTH(A.accion)>0 ORDER BY A.fecha DESC LIMIT 30", [$area]);
        foreach ($lot as &$el) {
            if ($el['rel']) {
                $el['rel'] = Storage::url($el['rel']);
            }
        }
        go(true, [
            "levantado" => $lot
        ]);
    }
    public static function super_resumen(Req $req)
    {
        list($rango, $periodo) = Util::days_interval(-2, 0);
        $days = new \StdClass();
        foreach ($periodo as $val) {
            $fecha = $val->format('Y-m-d');
            $days->{$fecha} = ["fecha" => $fecha];
        }
        $areas = [];
        foreach (Plano::SECCIONES as $ar) {
            $areas[$ar] = clone $days;
            $areas[$ar]->area = $ar;
        }
        $lot = DB::lot("
				SELECT COUNT(*) AS cant, A.area_rep, fecha, zona, turno
				FROM observacion A
				WHERE :inicio <= DATE(A.fecha) AND DATE(A.fecha) <= :final
				GROUP BY A.area_rep, A.fecha", $rango);
        foreach ($lot as $el) {
            $areas[$el['area_rep']]->{$el['fecha']} = $el;
        }
        $areas = array_values($areas);
        $days = array_reverse(array_keys((array)$days));
        go(true, [
            "dias" => $days,
            "areas" => $areas
        ]);
    }
    public static function resumen(Req $req)
    {
        go(true, self::ext_resumen());
    }
    public static function ext_resumen($days_after = -29)
    {
        $lot = [];
        list($rango, $periodo) = Util::days_interval($days_after, 0);
        foreach ($periodo as $val) {
            $fecha = $val->format('Y-m-d');
            
            $lot[$fecha] = (object)[
                "day" => $fecha,
                "cuerpos_dia" => null,
                "cuerpos_noche" => null,
                "vetas_dia" => null,
                "vetas_noche" => null,
                "nivel23_dia" => null,
                "nivel23_noche" => null,
                "superficie_dia" => null
            ];
        }

        $resumen = DB::lot("
				SELECT COUNT(*) AS cant, fecha, zona, turno 
				FROM observacion 
				WHERE :inicio <= fecha AND fecha <= :final AND area_rep='SEGURIDAD'
				GROUP BY fecha, zona, turno 
				ORDER BY fecha DESC, zona, turno", $rango);

        foreach ($resumen as $re) {
            if ($re['zona']!='vetas' and $re['zona']!='cuerpos') {
                continue;
            }
            $zona = $re['zona'];
            $turno = "dia";
            if ($re['turno']=='dia' or $re['turno']=='noche') {
                $turno = $re['turno'];
            }
            $lot[$re['fecha']]->{$zona."_".$turno} = $re;
        }
        $niveles = [
            "cuerpos" => DB::lot("SELECT nivel, COUNT(*) AS cant 
							FROM observacion 
							WHERE nivel IS NOT NULL AND zona='cuerpos'
							GROUP BY nivel ORDER BY nivel"),
            
            "vetas" => DB::lot("SELECT nivel, COUNT(*) AS cant 
							FROM observacion 
							WHERE nivel IS NOT NULL AND zona='vetas'
							GROUP BY nivel ORDER BY nivel")
        ];

        $areas_raw = DB::lot("SELECT area, zona, COUNT(*) AS cant 
							FROM observacion
							WHERE area IS NOT NULL AND zona IS NOT NULL
							GROUP BY area, zona ORDER BY area, zona");
        $areas_ob = [];
        foreach ($areas_raw as $ar) {
            if (!$areas_ob[$ar['area']]) {
                $areas_ob[$ar['area']] = [];
            }

            $areas_ob[$ar['area']][$ar['zona']] = $ar['cant'];
        }
        $areas = [];
        foreach ($areas_ob as $key => $value) {
            $areas[] = [
                "area" => $key,
                "cuerpos" => $value['cuerpos'],
                "vetas" => $value['vetas']
            ];
        }
        //nivel 23
        $nivel23 = DB::lot("
				SELECT COUNT(*) AS cant, fecha, turno 
				FROM observacion 
				WHERE :inicio <= fecha AND fecha <= :final AND zona='nivel23'
				GROUP BY fecha, turno 
				ORDER BY fecha DESC, turno", $rango);

        foreach ($nivel23 as $re) {
            $turno = "dia";
            if ($re['turno']=='dia' or $re['turno']=='noche') {
                $turno = $re['turno'];
            }
            $lot[$re['fecha']]->{"nivel23_".$turno} = $re;
        }

        //superficie
        $superficie = DB::lot("
				SELECT COUNT(*) AS cant, fecha
				FROM observacion 
				WHERE :inicio <= fecha AND fecha <= :final AND zona='superficie' AND turno='dia'
				GROUP BY fecha 
				ORDER BY fecha DESC", $rango);

        foreach ($superficie as $re) {
            $lot[$re['fecha']]->{"superficie_dia"} = $re;
        }
        return (object)[
            "reporte" => array_reverse(array_values($lot)),
            "niveles" => $niveles,
            "areas" => $areas,
            "periodo" => $periodo
        ];
    }
    public static function list(Req $req)
    {
        go(true, [
            "cuerpos" => DB::lot("SELECT * FROM labores WHERE zona='cuerpos' AND is_trash=0"),
            "vetas" => DB::lot("SELECT * FROM labores WHERE zona='vetas' AND is_trash=0")
        ]);
    }
    public static function info(Req $req)
    {
        $in = $req->filter([
            'id' => v::intVal()
        ]);
        go(true, [
            "labor" => DB::me("SELECT * FROM labores WHERE id=:id", $in),
            "observacion" => DB::lot("SELECT * FROM observacion WHERE id_labor=:id AND is_trash=0", $in)
        ]);
    }
    public static function sync(Req $req)
    {
        $js = $req->json();
        if ($js->pends) {
            foreach ($js->pends->observacion as $el) {
                if ($el->op=='insert') {
                    unset($el->op);
                    unset($el->id);
                    unset($el->updated_at);
                    self::_store_obs($el);
                } elseif ($el->op=='update') {
                    unset($el->op);
                    unset($el->updated_at);
                    self::_store_obs($el);
                } elseif ($el->op=='delete') {
                    self::_trash_tbl('observacion', $el->id);
                }
            }
        }
        $obs = DB::lot("SELECT * FROM observacion WHERE updated_at>? AND is_trash=0", [$js->last->observacion]);
        go(true, [
            "observacion" => $obs
        ]);
    }
    public static function _trash_tbl($tbl, $id)
    {
        DB::query("UPDATE $tbl SET is_trash=1 WHERE id=?", [$id]);
    }
    public static function obs_store(Req $req)
    {
        $in = $req->filter([
            'nivel' => v::length(2),
            'labor' => v::length(2),
            'zona' => v::length(2),
            'riesgo' => v::length(2),
            'info' => v::length(2),
            'fecha' => v::date(),
            'fecha_accion' => v::optional(v::date()),
            'accion' => v::optional(v::length(2))
        ]);
        $in->id = $req->param('id', null);
        self::_store_obs($in);
        go(true, [
            "observacion" => DB::lot("SELECT * FROM observacion WHERE nivel=? AND labor=? AND is_trash=0", [$in->nivel, $in->labor])
        ]);
    }
}
