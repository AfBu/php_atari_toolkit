<?php
/**
 * This file is part of PHP Atari Toolkit - library for manipulating Atari 8-bit
 * computer/emulator files. 
 *
 * @author Petr Kratina <petr.kratina@gmail.com>
 * @package php_atari_toolkit
 * @link https://github.com/AfBu/php_atari_toolkit
 */

require_once 'Atari/Exception.php';
require_once 'Atari/Atr.php';

class Atari_Fms
{
	protected $atr;

	public function __construct($atr, $update = true)
	{
		$this->atr = $atr;
		if ($update) $this->update();
	}

	public function update() { }

	public function getFiles($onlyUsed = true) { }

	public function getAtr()
	{
		return $this->atr;
	}

	public function getFileByName($filename) { }

	public function putFile($filename, $data) { }
}