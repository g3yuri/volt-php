<?php

namespace Slim\Features;

use \Req;
use \Util;
use \DB;
use Respect\Validation\Validator as v;
use \Lib\Storage;
use \Lib\Entity;
use \Lib\Validator;

class Curso extends Entity
{
  static ?string $table = "capa_cursos";
  public function createValidator() : array{
    return [
      "curso" => v::stringType()->notEmpty()
    ];
  }
}

class Certi extends Entity
{
  static ?string $table = "capa_certi";

  public function store_before() : bool {
    $this->nombres = strtoupper($this->nombres);
    $this->apellidos = strtoupper($this->apellidos);
    $this->hash = md5($this->dni.$this->nombres.$this->apellidos.$this->curso.$this->fecha);
    return false;
  }
  public function createValidator() : array{
    return [
      "dni" => v::stringType()->length(8,8),
      "nombres" => v::stringType()->notEmpty(),
      "apellidos" => v::stringType()->notEmpty(),
      "curso" => v::stringType()->notEmpty(),
      "fecha" => v::date(),
    ];
  }
}

class MYPDF extends \TCPDF {
  //Page header
  public function Header() {
      // get the current page break margin
      $bMargin = $this->getBreakMargin();
      // get current auto-page-break mode
      $auto_page_break = $this->AutoPageBreak;
      // disable auto-page-break
      $this->SetAutoPageBreak(false, 0);
      // set bacground image
      $img_file = Storage::path_local(Capa::PATH_CERTI);
      $this->Image($img_file, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
      // restore auto-page-break status
      $this->SetAutoPageBreak($auto_page_break, $bMargin);
      // set the starting point for the page content
      $this->setPageMark();
  }
  public static function create($dni, $name, $curso, $fecha, $output = false): string{
    $pdf = new MYPDF("L", PDF_UNIT, "A4", true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('GMI - CAPACITACION');
    $pdf->SetTitle('Certificado');
    $pdf->SetSubject('Certificado');
    $pdf->SetKeywords('certificado, gmi');
    
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setDefaultMonospacedFont("courier");
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->setPrintFooter(false);
    
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(0.8);

    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 20);

    // $html = '<p color="black" style="font-family:helvetica;font-weight:bold;font-size:26pt;">Nombre de ejemplo</p>';
    // $pdf->writeHTML($html, true, false, true, false, 'left');
    $pdf->Ln(42);
    $pdf->Write(0, "                               $name", '', 0, 'L', true, 0, false, false, 0);
    $pdf->Ln(16);
    $pdf->Write(0, "                               $curso", '', 0, 'L', true, 0, false, false, 0);
    $style = array(
      'position' => '',
      'align' => 'C',
      'stretch' => false,
      'fitwidth' => true,
      'cellfitalign' => '',
      'border' => true,
      'hpadding' => 'auto',
      'vpadding' => 'auto',
      'fgcolor' => array(0,0,0),
      'bgcolor' => false, //array(255,255,255),
      'text' => true,
      'font' => 'helvetica',
      'fontsize' => 8,
      'stretchtext' => 4
    );
    $pdf->write1DBarcode($dni, 'EAN13', 11, '', '', 18, 0.4, $style, 'N');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->setColor('text',150);
    $pdf->Write(0, "Fecha: $fecha", '', 0, 'L', true, 0, false, false, 0);
    $hash = md5($fecha.$dni.$name.$curso);
    $pdf->Write(0, "Código: $hash", '', 0, 'L', true, 0, false, false, 0);

    $pdf->setPrintHeader(false);
    
    //$pdf->Output('example_051.pdf', 'I');
    return $pdf->Output("name.pdf",$output ? "S" : "I");
  }
}
class Capa
{
  const PATH_CERTI = "/capa/certi.jpg";
  public static function init(Req $req): void
  {
    //$url = Storage::url(self::PATH_CERTI);
    go(true,[
      "certis" => Certi::all(),
      "cursos" => Curso::all()
    ]);
  }
  public static function subir(Req $req): void
  {
    $fs = $req->file("file");
    if ($fs) {
        
        $fs->store(self::PATH_CERTI);
    }
    $url = Storage::url(self::PATH_CERTI);
    go(true,[
      "uri" => $url
    ]);
  }
  public static function download(Req $req): void{
    $path = Storage::path_local(self::PATH_CERTI);
    $f = fopen($path, 'r');
    $id = md5_file($path);
    go(true, [
      "filename" => "certificado-{$id}.pdf",
      "content" => stream_get_contents($f),
      "mimeType" => "text/csv"
  ]);
  }
  public static function curso_crear(Req $req): void{
    $in = Validator::filter([ 
      "curso" => 'texto',
    ],$req->params());
    go(true,[
      "curso" => $in["curso"]
    ]);
  }
  public static function test(Req $req): void{
    go(true,[ 
      "file" =>  MYPDF::create("42995248","QUISPE MAMANI YURI EBERSON", "ATS","12/04/2023")
    ]);
    
  }
  public static function curso_list(Req $req): void{
    go(true,[
      "cursos" => Curso::all()
    ]);
  }
  public static function display(Req $req): void{
    $in = $req->param("hash",null);
    $cer = Certi::where_first("hash=?",[$in]);
    //go(true,$cer);
    MYPDF::create($cer->dni,$cer->apellidos." ".$cer->nombres, $cer->curso,$cer->fecha);
  }
  public static function generar(Req $req): void{
    $cer = new Certi();
    $cer->merge($req->params());
    $cer->store();
    go(true,[
      "certis" => Certi::all()
    ]);
  }
}