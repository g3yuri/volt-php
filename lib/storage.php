<?php

namespace Lib;
use \League\Flysystem\Local\LocalFilesystemAdapter;
use \League\Flysystem\Filesystem;

class Storage {
	private static $driver = null;
	private static $base = __DIR__.'/../storage/app/';
	public static function path_join(string $path_a , string $path_b) : string {
		return rtrim($path_a,'/').'/'.ltrim($path_b,'/');
	}
	public static function path_local(string $path) : ?string {
		// ojo: el uso de ralpath esta sujeto a que el archivo exista
		return self::path_join(self::$base,$path);
	}
	public static function driver() {
		if (self::$driver===null){
			$adapter = new LocalFilesystemAdapter(self::$base);
			$filesystem = new Filesystem($adapter);
			self::$driver = $filesystem;
		}
		return self::$driver;
	}
	public static function put($path, $content) {

	}
	public static function go($path, $mimetype = null) {
		$fs = self::driver();
		$path = urldecode($path);
		if (!$fs->fileExists($path)) {
			header("HTTP/1.0 404 Not Found");
			die();
		}
		if (null === $mimetype) {
			$mimetype = $fs->mimeType($path);
		}
		header('Content-length: '.$fs->fileSize($path));
		header('Content-Type: '.$mimetype);
		//header('Content-Disposition: attachment; filename="filename.jpg"'); // solo para descarga

		/*
		IMPORTANTE!!:
		==========
		ob_clean debe limpiar cualquier salida previa del buffer, ya que se combina con la data del archivo
		*/
		ob_clean();

        $bytes_read = readfile(self::path_join(self::$base,$path));

        if (false === $bytes_read) {
            throw new Exception('El archivo no puede ser leido');
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        die();
	}
	public static function url($path) {
		if ($path===null)
			return null;
		return self::path_join("https://slim.gmi.gd.pe/storage/",$path);
	}
	public static function delete($path) {
		$fs = self::driver();
		if (is_array($path)) {
			foreach($path as $pt) {
				$fs->delete($pt);
			}
		} else {
			$fs->delete($path);
		}
	}
	public static function ls($path)  {
		$filesystem = self::driver();
		$allPaths = $filesystem->listContents($path);
		return $allPaths->toArray();
	}
	public static function mimeType($path)  {
		return self::driver()->mimeType($path);
	}
	public static function fileSize($path)  {
		return self::driver()->fileSize($path);
	}
	public static function putStream($path, $stream) {
		$fs = self::driver();
		$fs->writeStream($path,$stream);
	}
	public static function fileExists($path) : bool {
		$fs = self::driver();
		return $fs->fileExists($path);
	}


	// public static function directoryExists($path) {
	// 	$fs = self::driver();
	// 	$fs->directoryExists($path);
	// }
	public static function createDirectory($path) {
		$fs = self::driver();
		$fs->createDirectory($path);
	}
	public static function move($source, $destination) {
		$fs = self::driver();
		$fs->move($source,$destination);
	}
	public static function copy($source, $destination) {
		$fs = self::driver();
		$fs->copy($source,$destination);
	}
	public static function write($path, $content) {
		$fs = self::driver();
		$fs->write($path,$content);
	}
	public static function read($path) {
		$fs = self::driver();
		return $fs->read($path);
	}
}