<?php

namespace Slim\Features;

use \Req;
use SebastianBergmann\Type\NeverType;
use \Usuario;
use \DB;
use \Util;
use \Model\Postulante;
use Respect\Validation\Validator as v;
use \Lib\Storage;
use Google\Client;
use Google\Service\Fitness;
use Google\Service\PeopleService;
use GuzzleHttp\Client as Guzzle_Client;

class Fatiga
{
    private static $trans = [
        1 => 'despierto',
        2 => 'dormido',
        3 => 'fuera',
        4 => 'ligero',
        5 => 'profundo',
        6 => 'rem'
    ];
    public static function report_download(Req $req) : void
    {
        $in = $req->filter([
            "from" => v::date(),
            "to" => v::date()
        ]);
        $filename = "reporte-bandas-$in->from-$in->to.csv";
        ob_clean();
        $f = fopen('php://memory', 'w');

        $lineData = [];
        $list = DB::lot("SELECT D.area, C.fecha, D.dni, D.nombres, D.celular, D.correo, C.turno, D.guardia, C.equipo, C.inicio, C.final, C.descanso, C.tprofundo, C.tligero, C.trem, C.observacion
			FROM fat_operador D 
			LEFT JOIN 
				(SELECT 
					B.area,
					DATE(A.final) AS fecha,
					A.dni, B.nombres, A.turno, B.guardia, A.equipo, A.inicio, A.final,
					A.tprofundo, A.tligero, A.trem, A.observacion,
					TIME_FORMAT(SUM(descanso),'%H:%i') descanso,
					GROUP_CONCAT(A.rel SEPARATOR '@@') rel
					FROM fatiga A 
					LEFT JOIN fat_operador B ON A.dni=B.dni 
					WHERE :from <= DATE(A.final) AND DATE(A.final) <= :to 
					GROUP BY B.area, B.dni, DATE(A.final)) C 
			ON D.dni=C.dni
			ORDER BY D.area, C.fecha", $in);

        fputcsv($f, array_values(["AREA", "FECHA", "DNI", "NOMBRES Y APELLIDOS", "CELULAR", "CORREO", "TURNO", "GUARDIA", "EQUIPO", "INICIO", "FINAL", "TIEMPO", "T. PROFUNDO", "T. LIGERO", "T. REM", "OBSERVACION"]), ';');
        foreach ($list as $li) {
            //https://slim.gmi.gd.pe/fatiga/admin/report/download/2022-08-01/2022-08-05
            fputcsv($f, array_values($li), ';');
        }
        fseek($f, 0);
        // header('Content-Type: text/csv');
        //   	header('Content-Disposition: attachment; filename="' . $filename . '";');
        //   	fpassthru($f);
        //   	die();
        go(true, [
            "filename" => $filename,
            "content" => stream_get_contents($f),
            "mimeType" => "text/csv"
        ]);
    }
    public static function super_list(Req $req)
    {
        $in = $req->filter([
            "from" => v::optional(v::date()),
            "to" => v::optional(v::date())
        ]);
        $hoy = (new \DateTime())->format('Y-m-d');
        $in->area = $req->user()->area;
        $in->from = $req->param("from", $hoy);
        $in->to = $req->param("to", $hoy);

        //$lot = DB::lot("SELECT * FROM fat_operador WHERE area=?",[$area]);
        $lot = DB::lot("
			SELECT 
				D.area, C.fecha, D.dni, D.nombres, D.correo, D.celular, C.turno,
				D.guardia, C.equipo, C.descanso, C.tprofundo, C.tligero, C.trem
			FROM fat_operador D 
			LEFT JOIN 
				(SELECT 
					B.area,
					DATE(A.final) AS fecha,
					A.dni, B.nombres, A.turno, B.guardia, A.equipo,
					A.tprofundo, A.tligero, A.trem,
					TIME_FORMAT(SUM(descanso),'%H:%i') descanso,
					GROUP_CONCAT(A.rel SEPARATOR '@@') rel
					FROM fatiga A 
					LEFT JOIN fat_operador B ON A.dni=B.dni 
					WHERE B.area=:area AND :from <= DATE(A.final) AND DATE(A.final) <= :to
					GROUP BY B.area, B.dni, DATE(A.final)) C 
			ON D.dni=C.dni
			WHERE D.area=:area
			ORDER BY D.area, C.fecha", $in);
        go(true, [
            "list" => $lot,
            "range" => $in
        ]);
    }
    public static function admin_list(Req $req)
    {
        $lot = DB::lot("SELECT * FROM fat_operador");
        $sin = DB::lot("SELECT A.* FROM fat_oauth A LEFT JOIN fat_operador B ON A.dni=B.dni WHERE B.area IS NULL");
        go(true, [
            "list" => $lot,
            "sin" => $sin
        ]);
    }
    public static function admin_guardia(Req $req)
    {
        $in = $req->filter([
            "guardia" => v::in(['A', 'B', 'C'])
        ]);

        list($rango, $periodo) = Util::days_interval(-2, 0);

        $days = new \StdClass();
        foreach ($periodo as $val) {
            $fecha = $val->format('Y-m-d');
            $days->{$fecha} = ["fecha" => $fecha];
        }
        $rango->guardia = $in->guardia;

        $fat_list = DB::lot("SELECT C.descanso, C.fecha, C.rel, D.dni, D.nombres, D.area
			FROM fat_operador D 
			LEFT JOIN 
				(SELECT 
					A.dni, B.area, B.nombres,
					GROUP_CONCAT(A.rel SEPARATOR '@@') rel,
					DATE(A.final) AS fecha, 
					SUM(HOUR(A.descanso)*60+MINUTE(A.descanso)) AS descanso
					FROM fatiga A 
					LEFT JOIN fat_operador B ON A.dni=B.dni 
					WHERE :inicio <= DATE(A.final) AND DATE(A.final) <= :final AND B.guardia=:guardia 
					GROUP BY B.area, B.dni, DATE(A.final)) C 
			ON D.dni=C.dni
			WHERE D.guardia=:guardia", $rango);

        $lot = [];
        foreach ($fat_list as $fa) {
            $rel_arr = explode("@@", $fa['rel']);
            foreach ($rel_arr as &$re) {
                $re = Storage::url($re);
            }
            $fa['link'] = implode("@@", $rel_arr);
            $a = $fa['area'];
            if (!$lot[$a]) {
                $lot[$a] = [
                    "area" => $a,
                    "datos" => []
                ];
            }
            $area = &$lot[$a]["datos"];
            $dni = $fa['dni'];
            if (!$area[$dni]) {
                $area[$dni] = clone $days;
                $area[$dni]->persona = [
                    "dni" => $fa["dni"],
                    "nombres" => $fa["nombres"]
                ];
            }
            $area[$dni]->{$fa["fecha"]} = $fa;
        }
        foreach ($lot as &$li) {
            $li["datos"] = array_values($li["datos"]);
        }
        $lot = array_values($lot);

        $days = array_reverse(array_keys((array)$days));

        go(true, [
            "dias" => $days,
            "datos" => $lot,
            "guardia" => $in->guardia
        ]);
    }
    public static function admin_dashboard(Req $req) : void
    {
        $area = DB::lot("SELECT C.area,
                            AVG(TIME_TO_SEC(F.descanso)) / 3600 AS media,
                            COUNT(F.descanso) AS cant,
                            CASE
                                WHEN descanso < '04:30:00' THEN 'alto'
                                WHEN descanso < '05:30:00' THEN 'medio'
                                ELSE 'bajo'
                            END AS riesgo
                        FROM fatiga AS F
                            LEFT JOIN fat_operador AS C ON C.dni = F.dni
                        WHERE F.final >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            AND C.area IS NOT NULL
                        GROUP BY C.area,
                            riesgo");
        
        $cargo = DB::lot("SELECT C.cargo,
                            AVG(TIME_TO_SEC(F.descanso)) / 3600 AS media,
                            COUNT(F.descanso) AS cant,
                            CASE
                                WHEN descanso < '04:30:00' THEN 'alto'
                                WHEN descanso < '05:30:00' THEN 'medio'
                                ELSE 'bajo'
                            END AS riesgo
                            FROM fatiga AS F
                                LEFT JOIN fat_operador AS C ON C.dni = F.dni
                            WHERE F.final >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                AND C.area IS NOT NULL
                            GROUP BY C.cargo,
                                riesgo");

        $repor = DB::lot("SELECT P.area, IFNULL(C.cant,0) AS cant, P.total FROM (SELECT Y.area, COUNT(Y.dni) AS total FROM fat_operador AS Y GROUP BY Y.area) AS P LEFT JOIN (SELECT F.area, IFNULL(COUNT(X.dni),0) AS cant FROM fatiga AS X LEFT JOIN fat_operador AS F ON X.dni=F.dni  WHERE X.final >= DATE_SUB(NOW(), INTERVAL 30 DAY)  AND F.area IS NOT NULL GROUP BY F.area) AS C ON P.area=C.area");
        go(true, [
            "area" => $area,
            "cargo" => $cargo,
            "repor" => $repor
        ]);
    }
    public static function super_dashboard(Req $req) : void
    {
        $owner = $req->user()->area;
        $area = DB::lot("SELECT S.guardia,
                            R.media,
                            R.cant,
                            R.riesgo
                        FROM (
                            SELECT 'A' AS guardia
                            UNION SELECT 'B' AS guardia
                            UNION SELECT 'C' AS guardia
                        ) AS S
                        LEFT JOIN (
                            SELECT 
                                C.guardia, 
                                AVG(TIME_TO_SEC(F.descanso))/3600 AS media,
                                COUNT(F.descanso) AS cant, 
                                CASE WHEN descanso<'04:30:00' THEN 'alto' WHEN descanso<'05:30:00' THEN 'medio' ELSE 'bajo' END AS riesgo
                            FROM fatiga AS F 
                            LEFT JOIN fat_operador AS C ON C.dni=F.dni 
                            WHERE F.final >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                AND C.guardia IS NOT NULL
                                AND C.area=?
                                GROUP BY C.guardia, riesgo
                        ) AS R ON R.guardia=S.guardia", [$owner]);
        
        $operador = DB::lot("SELECT
                                C.dni, C.nombres,
                                AVG(TIME_TO_SEC(F.descanso)) / 3600 AS media,
                                COUNT(F.descanso) AS cant,
                                CASE
                                    WHEN descanso < '04:30:00' THEN 'alto'
                                    WHEN descanso < '05:30:00' THEN 'medio'
                                    ELSE 'bajo'
                                END AS riesgo
                            FROM
                                fatiga AS F
                                LEFT JOIN fat_operador AS C ON C.dni = F.dni
                            WHERE
                                F.final >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                AND C.area IS NOT NULL
                            GROUP BY
                                C.dni,
                                riesgo");


        $repor = DB::lot("SELECT P.area, IFNULL(C.cant,0) AS cant, P.total FROM (SELECT Y.area, COUNT(Y.dni) AS total FROM fat_operador AS Y GROUP BY Y.area) AS P LEFT JOIN (SELECT F.area, IFNULL(COUNT(X.dni),0) AS cant FROM fatiga AS X LEFT JOIN fat_operador AS F ON X.dni=F.dni  WHERE X.final >= DATE_SUB(NOW(), INTERVAL 30 DAY)  AND F.area IS NOT NULL GROUP BY F.area) AS C ON P.area=C.area");
        go(true, [
            "guardia" => $area,
            "operador" => $operador,
            "repor" => $repor,
            "ownerArea" => $owner
        ]);
    }
    public static function super_dashboard_operador(Req $req) : void
    {
        $in = $req->filter([
            "area" => v::optional(v::alpha(" ")),
            "guardia" => v::optional(v::alpha()),
            "cargo" => v::optional(v::alpha(" ")),
            "riesgo" => v::optional(v::alpha())
        ]);
        $str = "";
        if ($in->area) {
            $str .= " AND C.area='$in->area'";
        }
        if ($in->guardia) {
            $str .= " AND C.guardia='$in->guardia'";
        }
        if ($in->cargo) {
            $str .= " AND C.cargo='$in->cargo'";
        }
        if ($in->riesgo) {
            if ($in->riesgo == "alto") {
                $str .= " AND F.descanso<'04:00:00'";
            } elseif ($in->riesgo == "medio") {
                $str .= " AND F.descanso<'05:30:00' AND F.descanso>='04:00:00'";
            } elseif ($in->riesgo == "bajo") {
                $str .= " AND F.descanso>='05:30:00'";
            }
        }

        $owner = $req->user()->area;
        $operador = DB::lot("SELECT
                                C.dni, C.nombres,
                                AVG(TIME_TO_SEC(F.descanso)) / 3600 AS media,
                                COUNT(F.descanso) AS cant
                            FROM
                                fatiga AS F
                                INNER JOIN fat_operador AS C ON C.dni = F.dni
                            WHERE
                                F.final >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                $str
                            GROUP BY
                                C.dni
                            ORDER BY
                                media DESC");

        $in->{'data'} = $operador;
        go(true, $in);
    }
    public static function super_guardia(Req $req)
    {
        $in = $req->filter([
            "guardia" => v::in(['A', 'B', 'C'])
        ]);
        $in->area = $req->user()->area;
        
        $lot = [];

        list($rango, $periodo) = Util::days_interval(-2, 0);

        $days = [];
        foreach ($periodo as $val) {
            $fecha = $val->format('Y-m-d');
            $days[$fecha] = ["fecha" => $fecha];
        }

        $operadores = DB::lot("SELECT * FROM fat_operador WHERE area=:area AND guardia=:guardia ORDER BY nombres", $in);

        foreach ($operadores as $op) {
            $rango->dni = $op['dni'];
            $fat_list = DB::lot("SELECT *,
					DATE(final) AS fecha, 
					GROUP_CONCAT(rel SEPARATOR '@@') rel,
					SUM(HOUR(descanso)*60+MINUTE(descanso)) AS descanso 
					FROM fatiga 
					WHERE :inicio <= DATE(final) AND DATE(final) <= :final AND dni=:dni
					GROUP BY dni, DATE(final)", $rango);

            $datos = [];
            foreach ($fat_list as &$fa) {
                $rel_arr = explode("@@", $fa['rel']);
                foreach ($rel_arr as &$re) {
                    $re = Storage::url($re);
                }
                $fa['link'] = implode($rel_arr, "@@");

                $datos[$fa['fecha']] = $fa;
            }
            $lot[] = [
                "persona" => $op,
                "datos" => $datos
            ];
        }

        $days = array_reverse(array_keys($days));
        
        go(true, [
            "dias" => $days,
            "datos" => $lot,
            "area" => $in->area,
            "guardia" => $in->guardia,
            "rango" => $rango
        ]);
    }
    public static function super_oper_store(Req $req)
    {
        go(true, "lot");
    }
    public static function admin_oper_delete(Req $req)
    {
        $in = $req->filter([
            "dni" => v::digit(8),
        ]);
        DB::query("DELETE FROM fat_operador WHERE dni=?", [$in->dni]);
        go(true, []);
    }
    public static function admin_oper_store(Req $req)
    {
        $in = $req->filter([
            "dni" => v::digit(8),
            "nombres" => v::alnum(' '),
            "area" => v::alnum(' '),
            "guardia" => v::alpha(),
            "celular" => v::digit(9),
            "correo" => v::email()
        ]);
        $me = DB::me("SELECT * FROM fat_operador WHERE dni=?", [$in->dni]);
        if ($me) {
            DB::query("UPDATE fat_operador SET nombres=:nombres, area=:area, guardia=:guardia, celular=:celular, correo=:correo WHERE dni=:dni", $in);
        } else {
            DB::query("INSERT INTO fat_operador (dni, nombres, area, guardia, celular, correo) VALUES (:dni, :nombres, :area, :guardia, :celular, :correo)", $in);
        }
        $oper = DB::me("SELECT * FROM fat_operador WHERE dni=?", [$in->dni]);
        go(true, [
            "oper" => $oper
        ]);
    }


    /*
    INFORMACION:

    Para permisos oauth2 google en web-server: https://developers.google.com/identity/protocols/oauth2/web-server

    Referencia Google Fit: https://developers.google.com/fit/rest/v1/reference/users/dataset/aggregate
    */

    //id cliente = 293066239423-3rd9usb0rtl27puo70044k7uusr6lju8.apps.googleusercontent.com
    //secret = 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
    public static function datos_lectura(Req $req)
    {
        $in = $req->filter([
            "dni" => v::alnum(8)
        ]);
        $dni = $in->dni;
        $service = self::_service($dni);
        $response = $service->users_sessions->listUsersSessions('me', [
            'activityType' => 72,
            'includeDeleted' => true
        ]);
        foreach ($response->getSession() as $ses) {
            $total = 0;
            $in = (object)[
                'id' => $ses->id,
                'dni' => $dni,
                'total' => $total,
                'startTimeMillis' => $ses->startTimeMillis,
                'endTimeMillis' => $ses->endTimeMillis,
                'modifiedTimeMillis' => $ses->modifiedTimeMillis,
                'name' => $ses->application->name,
                'packageName' => $ses->application->packageName
            ];
            DB::query("INSERT INTO fat_session (id, dni, startTimeMillis, endTimeMillis, modifiedTimeMillis, name, packageName) VALUES (:id, :dni, :startTimeMillis, :endTimeMillis, :modifiedTimeMillis, :name, :packageName) ON DUPLICATE KEY UPDATE id=:id, dni=:dni, startTimeMillis=:startTimeMillis, endTimeMillis=:endTimeMillis, modifiedTimeMillis=:modifiedTimeMillis, name=:name, packageName=:packageName", $in);
            $ag = new Fitness\AggregateRequest();
            $by = new Fitness\AggregateBy();
            $by->setDataTypeName('com.google.sleep.segment');
            $ag->setAggregateBy([$by]);
            $ag->setStartTimeMillis($ses->startTimeMillis);
            $ag->setEndTimeMillis($ses->endTimeMillis);
            $res_ag = $service->users_dataset->aggregate('me', $ag);
            $bucket = $res_ag->getBucket();
            
            DB::query("DELETE FROM fat_point WHERE session_id=?", [$ses->id]);
            $tiempo = (object)[
                'despierto' => 0,
                'dormido' => 0,
                'fuera' => 0,
                'ligero' => 0,
                'profundo' => 0,
                'rem' => 0,
                'id' => $ses->id
            ];

            foreach ($bucket as $bkt) {
                foreach ($bkt->dataset as $set) {
                    foreach ($set->point as $point) {
                        $value = $point->value[0]->intVal;
                        $ds = (object)[
                            'session_id' => $ses->id,
                            'dataTypeName' => $point->dataTypeName,
                            'endTimeNanos' => $point->endTimeNanos,
                            'startTimeNanos' => $point->startTimeNanos,
                            'originDataSourceId' => $point->originDataSourceId,
                            'value' => $value
                        ];
                        DB::query("INSERT INTO fat_point (session_id, dataTypeName, endTimeNanos, startTimeNanos, originDataSourceId, value) VALUES (:session_id, :dataTypeName, :endTimeNanos, :startTimeNanos, :originDataSourceId, :value)", $ds);
                        if (isset($tiempo->{self::$trans[$value]})) {
                            $tiempo->{self::$trans[$value]} += $point->endTimeNanos - $point->startTimeNanos;
                        }
                    }
                }
            }
            DB::query("UPDATE fat_session SET despierto=:despierto, dormido=:dormido, fuera=:fuera, ligero=:ligero, profundo=:profundo, rem=:rem, total=:ligero+:profundo+:rem WHERE id=:id", $tiempo);
        }

        $list = DB::lot("SELECT * FROM fat_session WHERE dni=?", [$dni]);
        go(true, [
            "list" => $list,
            "response" => $response
        ]);
        //go(true,$response);
        // $http = self::_http($in->dni);
        // $res = $http->get("https://www.googleapis.com/fitness/v1/users/me/sessions?startTime=2022-04-20T00:00:00.000Z&endTime=2022-04-25T23:59:59.999Z&activityType=72");
        // if ($res->getStatusCode()!=200) {
        // 	go(false,$res->getReasonPhrase());
        // }
        // $data = $res->getBody();
        // if (str_contains($res->getHeader('Content-Type')[0],'application/json')) {
        // 	$data = json_decode($res->getBody());
        // }
        // go(true,$data);
    }
    public static function _http($dni)
    {
        $http = null;
        $client = self::_create_client();

        // $httpClient = new Guzzle_Client();
        // $client->setHttpClient($httpClient);

        $el = self::_refresh_token_and_update($dni, $client);
        if ($el->access_token) {
            $client->setAccessToken($el->access_token);
            $http = $client->authorize();
        }
        return $http;
    }
    public static function _service($dni)
    {
        $service = null;
        $client = self::_create_client();
        $el = self::_refresh_token_and_update($dni, $client);
        if ($el->access_token) {
            $client->setAccessToken($el->access_token);
            //$httpClient = $client->authorize();
            $service = new Fitness($client);
        }
        return $service;
    }
    public static function datos_list(Req $req)
    {
        $in = $req->filter([
            "dni" => v::alnum(8)
        ]);
        //$list = DB::lot("SELECT P.*, S.dni FROM fat_point AS P LEFT JOIN fat_session AS S ON S.id=P.session_id  WHERE S.dni=?",[$in->dni]);
        $list = DB::lot("SELECT * FROM fat_session WHERE dni=?", [$in->dni]);
        go(true, [
            "list" => $list
        ]);
    }
    public static function middlelware(Req &$req, callable $next) : void
    {
        $oper = $req->cookie("oper_token"); // operador token
        if ($oper) {
            $me = (object)DB::me("SELECT * FROM fat_oauth WHERE oper_token=? AND LENGTH(oper_token) > 5", [$oper]);
            if ($me->id) {
                $req->{'oper'} = $me;
            } else {
                $oper = null;
            }
        }
        if ($oper===null) {
            go(true, ["oper_require_login"=>true,"msg"=>"require logearse"]);
        }
        $next();
    }
    public static function boot(Req $req) : void
    {
        go(true, $req->{'oper'});
    }
    public static function _go_item_id_dni($id, $dni) : void
    {
        $me = DB::me("SELECT *,
					TIME_FORMAT(descanso,'%H:%i') descanso,
					TIME_FORMAT(tprofundo,'%H:%i') tprofundo,
					TIME_FORMAT(tligero,'%H:%i') tligero,
					TIME_FORMAT(trem,'%H:%i') trem
					FROM fatiga WHERE id=? AND dni=?", [$id, $dni]);
        $me['link'] = Storage::url($me['rel']);
        go(true, $me);
    }
    public static function item(Req $req) : void
    {
        $in = $req->filter([
            "id" => v::digit()
        ]);
        $in->dni = $req->{'oper'}->dni;
        self::_go_item_id_dni($in->id, $in->dni);
    }
    public static function s_item(Req $req) : void
    {
        $in = $req->filter([
            "id" => v::digit(),
            "dni" => v::digit()
        ]);
        self::_go_item_id_dni($in->id, $in->dni);
    }
    public static function _go_day($dni, $fecha) : void
    {
        $list = DB::lot("SELECT *,
						TIME_FORMAT(descanso,'%H:%i') descanso,
						IF( descanso < '04:30:00', 'red', IF( descanso < '06:00:00', 'yellow', 'green' )) AS color,
						DATE(final) as fecha,
						TIME_FORMAT(TIME(final),'%H:%i') as final_hora
						FROM fatiga WHERE dni=? AND DATE(final)=? ", [$dni, $fecha]);
        go(true, $list);
    }
    public static function day(Req $req) : void
    {
        $in = $req->filter([
            "fecha" => v::date()
        ]);
        self::_go_day($req->{'oper'}->dni, $in->fecha);
    }
    public static function s_day(Req $req) : void
    {
        $in = $req->filter([
            "fecha" => v::date(),
            "dni" => v::digit()
        ]);
        self::_go_day($in->dni, $in->fecha);
    }
    public static function _oper_resumen_list($dni) : ?array
    {
        $interval = new \DateInterval('P1D');
        $hoy = (new \DateTime())->modify('+1 day');
        $prev = (clone $hoy)->modify('-50 day');
        $period = new \DatePeriod($prev, $interval, $hoy);

        $in = [
            "inicio" => $prev->format("Y-m-d"),
            "final" => $hoy->format("Y-m-d"),
            "dni" => $dni
        ];
        
        $list = DB::lot("SELECT *, 
							IF( descanso_total < '04:30', 'red', IF( descanso_total < '06:00', 'yellow', 'green' )) AS color
						FROM ( 
							SELECT *, 
							DATE(final) AS fecha, 
							TIME_FORMAT(SEC_TO_TIME(SUM(TIME_TO_SEC(descanso))),'%H:%i') descanso_total
					 		FROM fatiga 
					 		WHERE dni=:dni
					 		AND DATE(final) >= :inicio
					 		AND DATE(final) <= :final
					 		GROUP BY DATE(final) 
					 		ORDER BY final DESC 
					 		LIMIT 50
					 	) tb", $in);

        $res = [];
        foreach ($period as $val) {
            $fecha = $val->format('Y-m-d');
            $res[$fecha] = ["fecha" => $fecha];
        }
        foreach ($list as $ob) {
            $res[$ob['fecha']] = $ob;
        }
        return array_reverse(array_values($res));
    }

    public static function oper_list(Req $req) : void
    {
        $oper = $req->{'oper'};
        go(true, self::_oper_resumen_list($oper->dni));
    }
    public static function oper_s_list(Req $req) : void
    {
        $in = $req->filter([
            "dni" => v::digit()
        ]);
        $oper = DB::me("SELECT * FROM fat_operador WHERE dni=?", [$in->dni]);
        go(true, [
            "list" => self::_oper_resumen_list($in->dni),
            "oper" => $oper
        ]);
    }
    public static function login_google(Req $req) : void
    {
        $client = self::_create_client();
        $client->setApprovalPrompt("force");
        go(true, [
            "op" => "redirect",
            "auth_url" => $client->createAuthUrl()
        ]);
    }
    public static function manual(Req $req) : void
    {
        $in = $req->filter([
            "dni" => v::digit(),
            "correo" => v::email(),
            "celular" => v::digit()
        ]);
        $me = DB::me("SELECT * FROM fat_oauth WHERE dni=?", $in->dni);
        if (isset($me['id'])) {
            $bcel = false;
            if ($me['celular']) {
                $bcel = $me['celular']!=$in->celular;
            }
            if ($me['dni']!=$in->dni or $bcel) {
                go(false, "No coincide el dni o celular");
            }
        }
        
        $in->oper_token = Util::gen_token();

        Req::set_cookie("oper_token", $in->oper_token, [
            "domain" => ".gmi.gd.pe",
            "max-age" => 30*24*60*60,
            "path" => "/",
            "secure" => true,
            "samesite" => "None",
            "httponly" => true
        ]);
        DB::query("INSERT INTO fat_oauth (dni, correo, celular, oper_token) VALUES (:dni, :correo, :celular, :oper_token) ON DUPLICATE KEY UPDATE dni=:dni, correo=:correo, celular=:celular, oper_token=:oper_token", $in);
        DB::commit();
        $me = DB::me("SELECT * FROM fat_oauth WHERE oper_token=?", [$in->oper_token]);
        go(true, $me);
    }
    public static function oauth_refresh(Req $req)
    {
        $in = $req->filter([
            "dni" => v::alnum(8)
        ]);
        $res = self::_refresh_token_and_update($in->dni);
        $list = DB::lot("SELECT * FROM fat_oauth");
        go(true, [
            "list" => $list,
            "res" => $res
        ]);
    }
    public static function oauth_list(Req $req)
    {
        $list = DB::lot("SELECT * FROM fat_oauth");
        go(true, [
            "list" => $list
        ]);
    }
    public static function _auth_store($in)
    {
        if (!isset($in->celular)) {
            $in->celular = "";
        }
        DB::query("INSERT INTO fat_oauth (dni, correo, celular, code,access_token, refresh_token, oper_token, created) VALUES (:dni, :correo, :celular, :code, :access_token, :refresh_token, :oper_token, :created) ON DUPLICATE KEY UPDATE dni=:dni, correo=:correo, celular=:celular, code=:code, access_token=:access_token, refresh_token=:refresh_token, oper_token=:oper_token, created=:created", $in);
    }
    public static function oauth_verificar(Req $req)
    {
        $in = $req->filter([
            "dni" => v::digit()->stringType()->length(8)
        ]);
        $dni = $in->dni;
        $code = $req->param('code', null);
        $ob = self::_create_client();
        $people_res = null;

        if ($code) {
            $res = (object)$ob->fetchAccessTokenWithAuthCode($code);
            if (isset($res->access_token) and $res->access_token) {
                $people_service = new PeopleService($ob);
                $people_res = $people_service->people->get('people/me', ['personFields'=>'emailAddresses']);
                $adresses = $people_res->getEmailAddresses();
                $correo = count($adresses)>0?$adresses[0]->getValue():null;
                $oper_token = Util::gen_token();

                $in = (object)[
                    "access_token" => $res->access_token,
                    "created" => $res->created,
                    "code" => $code,
                    "refresh_token" => $res->refresh_token,
                    "oper_token" => $oper_token,
                    "correo" => $correo,
                    "dni" => $dni
                ];

                Req::set_cookie("oper_token", $oper_token, [
                    "domain" => ".gmi.gd.pe",
                    "max-age" => 30*24*60*60,
                    "path" => "/",
                    "secure" => true,
                    "samesite" => "None",
                    "httponly" => true
                ]);
                self::_auth_store($in);
                DB::commit();
                $me = DB::me("SELECT * FROM fat_oauth WHERE oper_token=?", [$oper_token]);
                go(true, $me);
            }
        }
        $el = null;
        go(false, [
            "res" => $res,
            "code" => $code,
            "el" => $el,
            "people" => $people_res
        ]);
    }
    /*
    ENDPOINT: /fatiga/oauth
    */
    public static function oauth_token(Req $req)
    {
        $code = $req->param('code', null);
        // $state = $req->param('state',null);
        // if (!$code or !$state)
        // 	go(false,"No regreso el codigo de acceso($code) o estado ($state)");
        // $json = (object)$ob->fetchAccessTokenWithAuthCode($code);
        // if (isset($json->access_token)) {
        // 	DB::query("INSERT INTO fat_oauth (access_token,created) VALUES (?,?) WHERE dni=? ON DUPLICATE KEY UPDATE access_token=?, created=?",[$json->access_token,$json->created,$state, $json->access_token,$json->created]);
        // }
        header('Location: http://gmi.gd.pe/f/auth/check?code='.$code);
        die();
    }
    public static function _create_client()
    {
        $ob = new Client();
        $ob->setClientId("293066239423-3rd9usb0rtl27puo70044k7uusr6lju8.apps.googleusercontent.com");
        $ob->setClientSecret("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");
        //$ob->setAuthConfig(__DIR__ . '/../client_secret.json');
        $ob->setRedirectUri("https://slim.gmi.gd.pe/fatiga/oauth");
        $ob->setAccessType("offline"); //?? como funconara esto de offlinee
        $ob->addScope(Fitness::FITNESS_SLEEP_READ);
        $ob->addScope(PeopleService::USERINFO_PROFILE);
        $ob->addScope(PeopleService::USERINFO_EMAIL);
        
        return $ob;
    }
    public static function _refresh_token_and_update($dni, $client = null)
    {
        if ($client === null) {
            $client = self::_create_client();
        }

        $el = (object)DB::me("SELECT * FROM fat_oauth WHERE dni=?", [$dni]);

        if ($el and isset($el->refresh_token)) {
            $refreshing = true;
            if (isset($el->access_token) and $el->access_token) {
                $client->setAccessToken($el->access_token);
                //$client->refreshToken($el->refresh_token);
                $refreshing = $client->isAccessTokenExpired(); // nunca se vio que diera falso
            }
            if ($refreshing) {
                $res = (object)$client->fetchAccessTokenWithRefreshToken($el->refresh_token);
                if (isset($res->access_token) and $res->access_token) {
                    $el->access_token = $res->access_token;
                }
                if (isset($res->refresh_token) and $res->refresh_token) {
                    $el->refresh_token = $res->refresh_token;
                }
                self::_auth_store($el);
            }
            $el->refreshing = $refreshing;
            return $el;
        }
        return null;
    }
    public static function oauth_registro(Req $req)
    {
        $in = $req->filter([
            "dni" => v::alnum(8),
            "nombres" => v::alpha(' ')
        ]);
        $ob = self::_create_client();
        // $el = self::_refresh_token_and_update($in->dni, $ob);
        // if ($el) {
        // 	go(true,[
        // 		"op" => "registrado",
        // 		"el" => $el
        // 	]);
        // }
        $ob->setApprovalPrompt("force");
        //$ob->setLoginHint($in->correo);
        go(true, [
            "op" => "redirect",
            "auth_url" => $ob->createAuthUrl()
        ]);
    }

    public static function _gethour($float)
    {
        $v = explode(":", $float);
        $m = 0;
        $s = 0;
        $h = 0;
        if (count($v) > 0) {
            $h = intval($v[0]);
        }
        if (count($v) > 1) {
            $m = intval($v[1]);
        }
        if (count($v) > 2) {
            $s = intval($v[2]);
        }
        if (count($v) > 3) {
            go(false, "Mal formato de tiempo");
        }
        return sprintf("%02d:%02d:%02d", $h, $m, $s);
    }
    public static function grupos(Req $req)
    {
        $grupos = DB::lot("SELECT seccion, guardia, CONCAT(seccion,'-',guardia) AS grupo FROM fat_operador GROUP BY SECCION, GUARDIA");
        go(true, [
            "grupos" => $grupos
        ]);
    }
    public static function reporteV2(Req $req)
    {
        $in = $req->filter([
            "seccion" => v::alnum(),
            "guardia" => V::alnum()
        ]);
        
        $lot = [];
        $days = [];

        $rango = DB::me("SELECT DATE(DATE_ADD(NOW(), INTERVAL -3 DAY)) AS desde, DATE(DATE_ADD(NOW(), INTERVAL 1 DAY)) AS hasta");
        $operadores = DB::lot("SELECT * FROM fat_operador WHERE seccion=:seccion AND guardia=:guardia ORDER BY nombres", $in);

        foreach ($operadores as $op) {
            $datos = [];
            $fat_list = DB::lot("SELECT *, DATE(final) AS fecha, HOUR(descanso)*60+MINUTE(descanso) AS descanso FROM fatiga WHERE ? <= DATE(final) AND DATE(final) < ? AND dni=?", [$rango['desde'], $rango['hasta'],$op['dni'] ]);

            foreach ($fat_list as $fa) {
                $fa['link'] = Storage::url($fa['rel']);
                $datos[$fa['fecha']] = $fa;
                $days[$fa['fecha']] = true;
            }
            $lot[] = [
                "persona" => $op,
                "datos" => $datos
            ];
        }

        $days = array_reverse(array_keys($days));
        
        go(true, [
            "dias" => $days,
            "datos" => $lot,
            "seccion" => $in->seccion,
            "guardia" => $in->guardia
        ]);
    }
    public static function reporte(Req $req)
    {
        // go(true,[
        // 	["dni"=>"42923248", "nombre"=>"GIANMARCO ROJAS FELIX", "tiempo"=> "2022-03-20#04:00:00,2022-03-21#05:57:00,2022-03-22#05:57:00"],
        // 	["dni"=>"62534536", "nombre"=>"ANGEL BISALAYA MAYTA", "tiempo"=> "2022-03-22#05:46:00"],
        // 	["dni"=>"42995248", "nombre"=>"EDUARDO CANO TRUJILLO", "tiempo"=> "2022-03-20#05:11:00,2022-03-21#63:17:00,2022-03-22#07:11:00"]
        // ]);

        // $lot = DB::lot("SELECT F.dni, P.nombre GROUP_CONCAT(CONCAT(F.fecha,'#',ADDTIME(F.tprofundo,ADDTIME(F.tligero,F.trem))) SEPARATOR ',') AS tiempo FROM fatiga AS F LEFT JOIN persona AS P ON P.id=F.dni WHERE DATEDIFF(NOW(),F.fecha)<30 GROUP BY F.dni ORDER BY F.fecha");

        $rango = DB::me("SELECT DATE(DATE_ADD(NOW(), INTERVAL -3 DAY)) AS desde, DATE(DATE_ADD(NOW(), INTERVAL 1 DAY)) AS hasta");

        $operadores = DB::lot("SELECT * FROM fatiga GROUP BY dni");
        $lot = [];
        $days = [];

        foreach ($operadores as $op) {
            $datos = [];
            $fat_list = DB::lot("SELECT *, DATE(final) AS fecha, HOUR(descanso)*60+MINUTE(descanso) AS descanso FROM fatiga WHERE ? <= DATE(final) AND DATE(final) < ? AND dni=?", [$rango['desde'], $rango['hasta'],$op['dni'] ]);

            foreach ($fat_list as $fa) {
                $fa['link'] = Storage::url($fa['rel']);
                $datos[$fa['fecha']] = $fa;
                $days[$fa['fecha']] = true;
            }
            $lot[] = [
                "persona" => $op,
                "datos" => $datos
            ];
        }

        go(true, [
            "dias" => array_reverse(array_keys($days)),
            "datos" => $lot
        ]);
    }
    public static function screen(Req $req)
    {
        $url = "";
        go(true, [$url]);
    }
    public static function callback_auth(Req $req)
    {
        go(true, "Aqui esta todo bien.auth");
    }
    public static function callback_data(Req $req)
    {
        go(true, "Aqui esta todo bien.data");
    }
    public static function app_url(Req $req)
    {
        go(true, "Aqui esta todo bien.app_url");
    }
    public static function fit_app(Req $req)
    {
        go(true, "Welcome to App Fit Researcg");
    }
    public static function fit_politica(Req $req)
    {
        go(true, "Politicas de privacidad");
    }
    public static function fit_condicion(Req $req)
    {
        go(true, "Condiciones del servicio");
    }
    public static function eliminar(Req $req)
    {
        $dni = isset($req->{'oper'}->dni) ? $req->{'oper'}->dni : $req->param('dni', null);
        $id = $req->param('id', null);

        $me = (object)DB::me("SELECT * FROM fatiga WHERE id=? AND dni=?", [$id,$dni]);
        if (!$me->id) {
            go(false, "No existe un reporte con el id=$id");
        }
        if ($me->rel) {
            Storage::delete($me->rel);
        }
        DB::query("DELETE FROM fatiga WHERE id=? AND dni=?", [$id,$dni]);
        go(true, "Se elimino");
    }
    public static function fatiga(Req $req)
    {
        $in = $req->filter([
            'id' => v::optional(v::digit()),
            'final' => v::digit('-', ':', ' '),
            'turno' => v::alnum(' '),
            'equipo' => v::alnum(' ', '-'),
            'descanso' => v::alnum(' ', ':'),
            'tprofundo' => v::optional(v::digit(':', ' ')),
            'tligero' => v::optional(v::digit(':', ' ')),
            'trem' => v::optional(v::digit(':', ' ')),
            'observacion' => v::optional(v::stringVal())
        ]);
        $in->tprofundo = $req->param("tprofundo", null);
        $in->tligero = $req->param("tligero", null);
        $in->trem = $req->param("trem", null);
        $in->observacion = $req->param("observacion", null);

        $in->dni = isset($req->{'oper'}->dni) ? $req->{'oper'}->dni : $req->param('dni', null);

        $in->final = trim($in->final);
        $in->descanso = self::_gethour($in->descanso);
        $in->equipo = trim($in->equipo);
        $in->turno = trim($in->turno);

        if ($in->id) {
            DB::query("UPDATE fatiga SET dni=:dni, inicio=SUBTIME(:final,:descanso), final=:final, turno=:turno, equipo=:equipo, descanso=:descanso, tprofundo=:tprofundo, tligero=:tligero, trem=:trem, observacion=:observacion WHERE id=:id", $in);
        } else {
            unset($in->id);
            DB::query("INSERT INTO fatiga (dni, inicio, final, turno, equipo, descanso, tprofundo, tligero, trem, observacion) VALUES (:dni, SUBTIME(:final,:descanso), :final, :turno, :equipo, :descanso, :tprofundo, :tligero, :trem, :observacion)", $in);
            $in->id = DB::last_insert_id();
        }

        $screen = $req->file('screen');
        if ($screen) {
            $path = 'fatiga/'.$in->dni.'/'.$in->final.$screen->ext();
            $screen->store($path);
            DB::query("UPDATE fatiga SET rel=? WHERE id=?", [$path,$in->id]);
        }
        self::_go_item_id_dni($in->id, $in->dni);
    }
}
