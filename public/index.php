<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once("../lib/boot.php");

use Respect\Validation\Validator as v;
use \Slim\Features\Login;
use \Slim\Features\Perfil;
use \Slim\Features\Master;
use \Slim\Features\Admin;
use \Slim\Features\Rrhh;
use \Slim\Features\Tormenta;
use \Slim\Features\Icam;
use \Slim\Features\Colaborador;
use \Slim\Features\Labor;
use \Slim\Features\Plano;
use \Slim\Features\Persona;
use \Slim\Features\Fatiga;
use \Slim\Features\Comunicacion;
use \Slim\Features\Iai;
use \Slim\Features\Petar;
use \Slim\Features\Veo;
use \Slim\Features\Gmailer;
use \Slim\Features\Reflex;
use \Slim\Features\Capa;

Router::get('/boot', [Login::class,'boot']);
Router::post('/login', [Login::class,'login']);
Router::post('/registro', [Login::class,'registro']);
Router::post('/code/rescue', [Login::class,'rescue']);
Router::post('/code/verify', [Login::class,'verify']);
Router::post('/code/passchange', [Login::class,'passchange']);

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/logout', [Login::class,'logout']);
    Router::post('/login/pwd/update', [Login::class,'pwd_update']);
    Router::post('/user/update', [Perfil::class,'update']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/admin/roles', [Admin::class,'roles']);
    Router::get('/admin/usuarios', [Admin::class,'usuarios']);
    Router::post('/admin/usuario/store', [Admin::class,'usuario_store']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/rrhh/list', [Rrhh::class,'list']);
    Router::get('/rrhh/ver/[i:id]', [Rrhh::class,'ver']);
    Router::post('/rrhh/store', [Rrhh::class,'store']);
    Router::get('/rrhh/empresas', [Rrhh::class,'empresas']);
    Router::post('/rrhh/experiencia', [Rrhh::class,'experiencia']);
    Router::delete('/rrhh/experiencia', [Rrhh::class,'del_exp']);
    Router::post('/rrhh/upload/adjunto', [Rrhh::class,'upload_adjunto']);
    Router::post('/rrhh/revision', [Rrhh::class,'revision']);
    Router::post('/rrhh/subir', [Rrhh::class,'subir']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/reflex/schema', [Reflex::class,'schema']);
    Router::post('/reflex/sync', [Reflex::class,'sync']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/iai/list', [Iai::class,'list']);
    Router::get('/iai/helper', [Iai::class,'helper']);
    Router::post('/iai/store', [Iai::class,'store']);
    Router::get('/iai/ver/[i:id]', [Iai::class,'ver']);

    //version 2
    Router::get('/iai/list/ultimos', [Iai::class,'list_ultimos']);
    Router::post('/iai/list/mes', [Iai::class,'list_mes']);
});

Router::post('/persona/list', [Persona::class,'list']);
Router::post('/persona/upload', [Persona::class,'upload']);

Router::get('/fatiga/reporte', [Fatiga::class,'reporte']);
Router::post('/fatiga/reporteV2', [Fatiga::class,'reporteV2']);
Router::get('/fatiga/grupos', [Fatiga::class,'grupos']);
Router::get('/fatiga/screen/[dni]/[fecha]', [Fatiga::class,'screen']);

Router::get('/fatiga/login_google', [Fatiga::class,'login_google']);
Router::post('/fatiga/registro/manual', [Fatiga::class,'manual']);

Router::middlelware([Fatiga::class,'middlelware'], function () {
    Router::get('/fatiga/eliminar/[i:id]', [Fatiga::class,'eliminar']);
    Router::post('/fatiga/fatiga', [Fatiga::class,'fatiga']);
    Router::get('/fatiga/boot', [Fatiga::class,'boot']);
    Router::get('/fatiga/day/[:fecha]', [Fatiga::class,'day']);
    Router::get('/fatiga/item/[i:id]', [Fatiga::class,'item']);
    Router::get('/fatiga/oper/list', [Fatiga::class,'oper_list']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/fatiga/admin/list', [Fatiga::class,'admin_list']);
    Router::get('/fatiga/super/list', [Fatiga::class,'super_list']);
    Router::get('/fatiga/admin/guardia/[a:guardia]', [Fatiga::class,'admin_guardia']);
    Router::get('/fatiga/super/guardia/[a:guardia]', [Fatiga::class,'super_guardia']);
    Router::get('/fatiga/super/oper/store', [Fatiga::class,'super_oper_store']);
    Router::post('/fatiga/admin/opers', [Fatiga::class,'admin_oper_store']);
    Router::delete('/fatiga/admin/opers/[i:dni]', [Fatiga::class,'admin_oper_delete']);
    Router::get('/fatiga/admin/dashboard', [Fatiga::class,'admin_dashboard']);
    Router::get('/fatiga/super/dashboard', [Fatiga::class,'super_dashboard']);
    Router::get('/fatiga/super/dashboard/operador', [Fatiga::class,'super_dashboard_operador']);
    
    
    

    Router::get('/fatiga/oper/[:dni]', [Fatiga::class,'oper_s_list']);
    Router::get('/fatiga/oper/[:dni]/[:fecha]', [Fatiga::class,'s_day']);
    Router::get('/fatiga/oper/[:dni]/[:id]/eliminar', [Fatiga::class,'eliminar']);
    Router::get('/fatiga/item/[:dni]/[i:id]', [Fatiga::class,'s_item']);
    Router::post('/fatiga/oper/[:dni]/store', [Fatiga::class,'fatiga']);
    Router::get('/fatiga/admin/report/download/[:from]/[:to]', [Fatiga::class,'report_download']);
});

// oauth token google client
Router::get('/fatiga/oauth', [Fatiga::class,'oauth_token']);
Router::post('/fatiga/registro', [Fatiga::class,'oauth_registro']);
Router::post('/fatiga/verificar', [Fatiga::class,'oauth_verificar']);

// auth administracion
Router::get('/fatiga/oauth/list', [Fatiga::class,'oauth_list']);
Router::post('/fatiga/oauth/refresh', [Fatiga::class,'oauth_refresh']);
Router::get('/fatiga/datos/[:dni]', [Fatiga::class,'datos_list']);
Router::post('/fatiga/datos/lectura', [Fatiga::class,'datos_lectura']);

Router::get('/fatiga/auth-callback', [Fatiga::class,'callback_auth']);
Router::get('/fatiga/data-callback', [Fatiga::class,'callback_data']);
Router::get('/fatiga/app-url', [Fatiga::class,'app_url']);
// END POINT GOOGLE FIT API, OAUTH
Router::get('/fatiga/fit/app', [Fatiga::class,'fit_app']);
Router::get('/fatiga/fit/politica', [Fatiga::class,'fit_politica']);
Router::get('/fatiga/fit/condicion', [Fatiga::class,'fit_condicion']);

Router::post('/comunicacion/install', [Comunicacion::class,'install']);

Router::get('/comunicacion/list', [Comunicacion::class,'go_list']);

//Router::middlelware([Comunicacion::class,'middlelware'],function (){
Router::middlelware([Login::class,'middlelware'], function () {
    Router::post('/comunicacion/store', [Comunicacion::class,'store']);
    Router::post('/comunicacion/sync', [Comunicacion::class,'sync']);
    Router::post('/comunicacion/masst', [Comunicacion::class,'masst']);
    Router::post('/comunicacion/ppt', [Comunicacion::class,'ppt']);
    Router::get('/comunicacion/masst', [Comunicacion::class,'masst']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/tormenta/list', [Tormenta::class,'list']);
    Router::post('/tormenta/store', [Tormenta::class,'store']);
});
Router::post('/tormenta/sync', [Tormenta::class,'sync']);
Router::get('/tormenta/propagate', [Tormenta::class,'_propagate']);

Router::middlelware([Login::class,'middlelware'], function () {
    Router::post('/sis/[a:base]/drop', [Plano::class,'drop']);
    Router::post('/sis/[a:base]/upload', [Plano::class,'upload']);
    Router::get('/sis/[a:base]/realloc', [Plano::class,'realloc']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::post('/petar/store', [Petar::class,'store']);
    Router::get('/petar/list', [Petar::class,'list']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::post('/veo/sso/store', [Veo::class,'sso_store']);
    Router::post('/veo/sso/list', [Veo::class,'sso_list']);
});

/* lo utiliza app, web, inspec*/
Router::get('/sis/areas', [Plano::class,'areas']);
Router::get('/sis/secciones', [Plano::class,'secciones']);
Router::post('/sis/persona', [Plano::class,'persona']);
Router::get('/sis/ob_causas', [Plano::class,'ob_causas']);

Router::get('/sis/[a:base]/list', [Plano::class,'go_list']);
Router::post('/sis/[a:base]/sync', [Plano::class,'sync']);

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/icam/list', [Icam::class,'list']);
    Router::get('/icam/ver/[i:id]', [Icam::class,'ver']);
    Router::post('/icam/store', [Icam::class,'store']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::post('/colaborador/upload', [Colaborador::class,'upload']);
    Router::get('/colaborador/venc', [Colaborador::class,'venc']);
    Router::get('/colaborador/permiso', [Colaborador::class,'permiso']);
    Router::post('/colaborador/query', [Colaborador::class,'query']);
    Router::post('/colaborador/permiso/store', [Colaborador::class,'permiso_store']);
});
Router::get('/colaborador/info/[i:id]', [Colaborador::class,'info']);
Router::get('/colaborador/info/[a:uuid]/[i:id]', [Colaborador::class,'info']);
Router::get('/colaborador/registro/[a:uuid]', [Colaborador::class,'registro']);

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/labor/list', [Labor::class,'list']);
    Router::post('/labor/info', [Labor::class,'info']);
    Router::post('/labor/obs-store', [Labor::class,'obs_store']);
});
Router::post('/labor/sync', [Labor::class,'sync']);

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/labor/resumen', [Labor::class,'resumen']);
    Router::get('/labor/resumenV2', [Labor::class,'resumen']);
    Router::get('/labor/super/resumen', [Labor::class,'super_resumen']);
    Router::get('/labor/super/pendiente', [Labor::class,'super_pendiente']);
    Router::get('/labor/super/levantado', [Labor::class,'super_levantado']);
    Router::post('/labor/day', [Labor::class,'day']);
    Router::post('/labor/query', [Labor::class,'query']);
    Router::post('/labor/racs/[:tipo]', [Labor::class,'racs']);
    Router::get('/labor/obs/[i:id]', [Labor::class,'obs']);
    Router::post('/labor/obs-drop/[i:id]', [Labor::class,'obs_drop']);
    Router::post('/labor/obs-save', [Labor::class,'obs_save_v2']);
});

Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/capa/certificado/init', [Capa::class,'init']);
    Router::post('/capa/certificado/subir', [Capa::class,'subir']);
    Router::post('/capa/certificado/generar', [Capa::class,'generar']);

    Router::post('/capa/certificado/download', [Capa::class,'download']);
    Router::post('/capa/cursos/crear', [Capa::class,'curso_crear']);
    Router::get('/capa/cursos/list', [Capa::class,'curso_list']);
});

Router::get('/capa/certificado/test', [Capa::class,'test']);
Router::get('/capa/certificado/[:hash]', [Capa::class,'display']);

// Gmailer endpoint
Router::get('/mailer/gmail/redirect', [Gmailer::class,'redirect']);
Router::middlelware([Login::class,'middlelware'], function () {
    Router::get('/mailer/gmail/init', [Gmailer::class,'init']);
    Router::get('/mailer/test', [Gmailer::class,'test_email']);
    Router::post('/mailer/gmail/uri', [Gmailer::class,'google_uri']);
    Router::post('/mailer/gmail/store/credential', [Gmailer::class,'store_credential']);
});

function term()
{
    die("<html style='background-color:#000;color:#ddd'><pre>".ob_get_clean()."</pre></html>");
}

Router::get('/test', function () {
    echo "Luego aca".PHP_BINARY;
    print_r($_SERVER);
    term();
});

Router::get('/email', function () {
    $mail = (new Sender())
        ->from('intellicorp.app@gmail.com')
        ->to('g3yuri@gmail.com')
        ->subject('Otra forma')
        ->htmlTemplate('email.html.twig', [
                'expiration_date' => new \DateTime('+7 days'),
                'username' => 'foo',
            ])->send();
    //print_r($email);
    term();
    go(false, true);
});

$app->terminate();
