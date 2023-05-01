<?php

use Crunz\Schedule;

$schedule = new Schedule();

$x = 12;
$task = $schedule->run(function() use ($x) { 
   echo "Saludos";
   $mail = (new Sender())
       ->from('intellicorp.app@gmail.com')
       ->to('g3yuri@gmail.com')
       ->subject('Otra forma')
       ->htmlTemplate('login/rescue.html.twig')
       ->context([
         'CODIGO' => '9999',
         'NAME' => 'Algun nombre'
       ])
       ->send($email);
});

$task
    ->everyHour()
    ->description('Copying the project directory');

return $schedule;
