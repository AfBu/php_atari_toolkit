<?php

define('DS', DIRECTORY_SEPARATOR);
set_include_path(implode(PATH_SEPARATOR, array(get_include_path(), dirname(__FILE__) . DS . '..' . DS . 'library')));

require_once 'Atari/Atr.php';
require_once 'Atari/Atascii.php';

$inImage = dirname(__FILE__) . DS . 'in.atr';
$outImage = dirname(__FILE__) . DS . 'out.atr';
$textFile = dirname(__FILE__) . DS . 'lorem.txt';

// open formatted ATR image - image creation and formatting will be added later
$atr = new Atari_Atr($inImage);
echo "Image opened.\n";

// get file system (currently Atari DOS 2.5 compatible file system supported)
$fs = $atr->getFs();

// print files on disk
echo "\nFiles on disk:\n";
$files = $fs->getFiles();
foreach ($files as $file) {
	echo "{$file['filename']} ({$file['sector_count']})\n";
}

// create atascii converter object
$atascii = new Atari_Atascii();

// load and convert ASCII text to ATASCII encoding
$text = file_get_contents($textFile);
$text = $atascii->toAtascii($text);

// insert file into image's filesystem
$fs->putFile('LOREM.TXT', $text);
echo "\nFile LOREM.TXT inserted.\n";

// get file, convert it and save to disk
$text = $fs->getFileByName('EXAMPLE.TXT');
$text = $atascii->toAscii($text);
file_put_contents('example.txt', $text);
echo "\nFile EXAMPLE.TXT extracted.\n";

// print files on disk
echo "\nFiles on disk:\n";
$files = $fs->getFiles();
foreach ($files as $file) {
	echo "{$file['filename']} ({$file['sector_count']})\n";
}

$atr->save($outImage);
echo "\nNew image saved.\n";
