<?php

use Crunz\Schedule;
use Slim\Features\Labor;
use \Lib\Storage;
use \DB;

$schedule = new Schedule();

$task = $schedule->run(function() {
   $dest = DB::lot("SELECT * FROM usuario WHERE AREA='SEGURIDAD'");

   $ob = Labor::ext_resumen(-4);
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
   foreach($dest as $seg) {
    $mail = (new \Sender())
        ->from('intellicorp.app@gmail.com')
        ->to($seg['EMAIL'])
        ->subject('Resumen de reportes SSO')
        ->htmlTemplate('gmi.sso.resumen.html.twig')
        ->context([
          "lot" => $lot,
          "cant" => count($lot),
          "text" => 'resumen'
        ])
        ->send();

   }
});

$task
    ->daily()
    ->at('21:30')
    ->appendOutputTo(Storage::path_local("setting/task/output.log"))
    ->description('Envia los resumenes de correo del dia');

return $schedule;