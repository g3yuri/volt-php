# Slim Framework

## DB

### Testing con Php unit

PHPUnit esta con composer, se debe agregar a terminus los gieuiten:

```json
    { 
        "keys": ["super+t"],
        "command": "terminus_open",
        
        "args": {
            "shell_cmd": "ssh ene -t 'cd /www/wwwroot/slim.alpayana.gd.pe; ./vendor/bin/phpunit tests'",
            "working_dir": "$folder",
            "title": "Linea de Comandos",
            "auto_close": false,
        }
    },
```

Mas configuracion de terminus en: <https://packagecontrol.io/packages/Terminus>

## PASO 1: Server - aapanel

Para inciar en el servidor:

- Generar en github.com un Token Personal
- Mediante git realizar un clone del proyecto
- mover a la carpeta raiz
- La propiedad debe estar en ubuntu:www excepto vendor y storage
- Se tiene que instalar la extenxion fileinfo en php

```bash
cd /www/wwwroot/slim.gmi.gd.pe
sudo git clone https://github.com/g3yuri/slim.gmi.git
sudo mv slim.gmi/* .
sudo chown ubuntu:www -R .
```

## PASO 2: ejecutar composer install

El archivo  php.ini debe estar en: `/www/server/php/74/etc/php.ini`, este archivo se copia y se busca en `disable_functions =` debe ser igual a un vacio, esto limita las funciones

Para la instalacion de los paquetes de composer use:

```bash
php -c ~/php.ini /usr/bin/composer install
```

si no funciona usa `/www/server/php/74/bin/php -c ~/php.ini /usr/bin/composer install` la carpeta 74 podria variar segun la version del php instalado de el aapanel

se tiene que asegurar que el usuario sea propietario de la carpeta vendor

## PASO 3: Configurar Nginx para que lea el path en index.php

En la configuracion del servidor debe estar lo siguiente:

```bash
    index index.php;
    root /www/wwwroot/slim.gmi.gd.pe/public;
    location / {
          try_files $uri $uri/ /index.php?$args;
    }
```

Se debe comentar algunos items como:

```bash
    #location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$
    #{
    #    expires      30d;
    #    error_log /dev/null;
    #    access_log off;
    #}

    #location ~ .*\.(js|css)?$
    #{
    #    expires      12h;
    #    error_log /dev/null;
    #    access_log off; 
    #}
```

## PASO 4: Extenciones ext-info - Zona Horaria - la web en la carpeta /public

- ext-info: Se necesita para phpoffice/phpspreadsheet, league/flysystem

- Mysql: Obtiene del sistema, se tiene que setear por comando
    1. se obtiene informacion de la hora y fecha con `timedatectl`
    2. listar las zonas horarias `timedatectl list-timezones`
    3. establecer una zona horaria `sudo timedatectl set-timezone America/Toronto`

- Php: Se tiene que modificar de su configuracion en apanel

- La carpeta public debe estar como inicio de la web

## Para refrescar al mapeo de clases del autoload

- Ejecutar `composer dump-autoload`

## Para instalar una extensión pear

- Paso 1: Instalar lo necesario `sudo apt install php-pear php-dev`, php-pear para usar el comando `pecl` y php-dev para user `php-config`
- Paso 2: Copiar la extension generada al directorio de extensiones de php, puede ser un directorio como `/usr/lib/php/20190902` y se debe copiar a un directorio como `/www/server/php/74/lib/php/extensions/no-debug-non-zts-20190902` haciendo `sudo cp /usr/lib/php/20190902/dbase.so /www/server/php/74/lib/php/extensions/no-debug-non-zts-20190902`, puede variar las rutas

## Storage

Referencia : <https://flysystem.thephpleague.com/docs/>

Las url que de rutas en los parametros de las funciones son relativas:

## Testing

Referencia: <https://docs.phpunit.de/en/9.6/>

Suponiendo que los archivos de prueba se encuentran en la carpeta `tests`

```php
./vendor/bin/phpunit tests
```

Agregar un shorcut en el editor de codigo:

- En visual studio code: instalar la extensión: `multi-command` y luego en el archivo `keybondongs.json` agregar el siguiente codigo

```json
{
    "key": "alt+cmd+b",
    "command": "extension.multiCommand.execute",
    "args": { 
        "sequence": [
            "workbench.action.createTerminalEditor",
            {
                "command": "workbench.action.terminal.sendSequence",
                "args": {
                    "text": "ssh ene -t 'cd /www/wwwroot/slim.gmi.gd.pe;./vendor/bin/phpunit tests'\u000D"
                }
            }
        ]
    }
}
```

- En Sublime text: Instalar la extencion `terminus` y agregar el siguiente codigo en el mwnu Sublime Text > Settings > Key Bindings y agregar el siguiente codigo:

```json
{ 
    "keys": ["super+g"],
    "command": "terminus_open",
    
    "args": {
        "shell_cmd": "ssh ene -t 'cd /www/wwwroot/slim.gmi.gd.pe; sudo su -c \"./vendor/bin/phpunit tests\"'",
        "working_dir": "$folder",
        "title": "Linea de Comandos",
        "auto_close": false,
    }
}
```

## Tareas / CronJobs

Referencia : <https://github.com/crunzphp/crunz>

## Pendiente

Esta pendiente que jale de una sola variable los siguientes datos:

- Conexion a la base de datos hay en config.php y db.php
- Dominio de la web, hay en archivos de la carpeta test y otros como session.php
- Modificar la linea `return self::path_join("https://slim.presto.gd.pe/storage/",$path);` en storage.php para que pueda leer la ruta raiz de storage, desde config.php
- Agregar a storage:

```php
    public static function lastModified($path)  {
        return self::driver()->lastModified($path);
    }
```

- Agregar a app.php para obtener el origin:

```php
        if (array_key_exists('HTTP_ORIGIN', $_SERVER)) {
            $origin = $_SERVER['HTTP_ORIGIN'];
        } else if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            $origin = $_SERVER['HTTP_REFERER'];
        } else {
            $origin = $_SERVER['REMOTE_ADDR'];
        }
```

- Agregar a storage.php:

```php
    public static function write($path, $content) {
        $fs = self::driver();
        $fs->write($path, $content);
    }
```

- Para realizar una actualización a PHP 8.2
  Ahora: El router `Klein` esta botando mensajes de `deprecates`, por ello se esta colocando `error_reporting(E_ALL ^ E_DEPRECATED);` para evitar estos mensajes
  Se necesita: Realizar el cambio con otro router como `FastRouter` (<https://github.com/nikic/FastRoute>)

- Tareas
Debe estar agregado `composer require lavary/crunz`
Mas info de Crunz esta en [Crunz] [https://github.com/lavary/crunz]
Crear el archivo de configuración

```bash
/project/vendor/bin/crunz publish:config
```

Agregar un cronjobs normal de la siguiente manera

```bash
* * * * * cd /project && vendor/bin/crunz schedule:run /path/to/tasks/directory
```
