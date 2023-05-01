<?php

return [
	'app' => [
		'host'=>'0.0.0.0',
		'port'=>10000,
		'allow_hosts'=> ['127.0.0.1', 'localhost:8080', 'gmi.gd.pe','*'],
		'secret_key'=>'43n080musdfjt54t-09sdgr',
		'debug'=> false,
		'name'=> 'star',
		'ssl_keyfile' => null,
		'ssl_certfile' => null
	],
	'session' => [
		'name'=> 'app_session',
		'domain'=> '%',
		'same_site'=> 'strict',
		'enabled'=>false,
		'expire'=> 30, #horas
	],
	'storage' => [
		'default'=> 'local',
		'transport'=> [
			'local'=> [
				'driver'=> 'local',
				'base_path'=> '@/storage/local'
			],
			'nube1'=> [
				'driver'=> 'aws',
				'bucket'=> 'star-bucket',
				'base_path'=> 'init/path'
			],
			'nube2'=> [
				'driver'=> 'oracle',
				'bucket'=> 'star-bucket'
			],
			'nube3'=> [
				'driver'=> 'ftp',
				'base_path'=> '/to/path'
			],
		],
	],
	'db' => [
		'default'=> 'normas',
		'transport'=> [
			'local'=>'sqlite:///data.sqlite',
			'normas'=> 'mysql://sql_slim_gmi_gd_:86842020f@localhost:3306/sql_slim_gmi_gd_'
		],
	],
	'mail' => [
		'default' => 'gd', #balance, #auto, 
		'from' =>'hola@gd.pe',
		'transport'=> [
			'gd' => [
				'url' =>'smtp://hola@gd.pe:04140089eE@mail.gd.pe:587',
			],
			'aws'=> [
				'url' => 'smtp://hola@gd.pe:04140089eE@mail.gd.pe:587',
				'from' => 'hola.aws@gd.pe',
				'subject' => 'Correos de prueba',
				'to' => 'g3yuri@gmail.com',
				'template' =>'/var/tpl.md',
				'limit_per_day' => 200
			]
		]
	]
];