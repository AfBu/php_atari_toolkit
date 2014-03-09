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
require_once 'Atari/Fms.php';

class Atari_Fms_Dos extends Atari_Fms
{
	protected $files;
	protected $vtoc;

	public function update()
	{
		$this->vtoc = $this->parseVtoc($this->atr);
		$this->files = $this->parseFileDirectory($this->atr);
	}

	public function parseVtoc($atr)
	{
		$vtoc = array();
		$sector = $atr->getSector(360);

		// 0 directory type
		$vtoc['directory_type'] = hexdec(bin2hex($sector[0]));
		// 1-2 maximum sector number
		$vtoc['maximum_sector'] = hexdec(bin2hex(strrev(substr($sector, 1, 2))));
		// 3-4 sectors available
		$vtoc['sectors_available'] = hexdec(bin2hex(strrev(substr($sector, 3, 2))));
		// 10-99 bitmap
		$bitmap = array();
		for ($i = 10; $i <= 99; $i++) {
			$b = hexdec(bin2hex($sector[$i]));
			for ($l = 7; $l >= 0; $l--) {
				$bitmap[] = ($b & (1 << $l)) > 0;
			}
		}
		$vtoc['bitmap'] = $bitmap;

		return $vtoc;
	}

	public function saveVtoc()
	{
		$sector = pack('Cvv', $this->vtoc['directory_type'], $this->vtoc['maximum_sector'], $this->vtoc['sectors_available']);
		$sector = str_pad($sector, $this->atr->getHeader('sector_size'), chr(0));
		$si = 0;
		for ($i = 10; $i <= 99; $i++) {
			$b = 0;
			for ($bi = 0; $bi < 8; $bi++) {
				if ($this->vtoc['bitmap'][$si]) {
					$b = $b | (0x01 << (7 - $bi));
				}
				$si++;
			}
			$sector[$i] = pack('C', $b);
		}
		$this->atr->setSector(360, $sector);
	}

	protected function parseFileDirectory($atr)
	{
		$files = array();
		$id = 0;

		for ($i = 361; $i <= 368; $i++) {
			$sector = $atr->getSector($i);

			for ($f = 0; $f < 8; $f++) {
				$of = $f * 0x10;
				$file = array('id' => $id);
				// 0 flag
				$flag = hexdec(bin2hex($sector[$of]));
				$file['output'] = ($flag & (1 << 0)) > 0;
				$file['system'] = ($flag & (1 << 1)) > 0;
				$file['locked'] = ($flag & (1 << 5)) > 0;
				$file['used'] = ($flag & (1 << 6)) > 0;
				$file['deleted'] = ($flag & (1 << 7)) > 0;
				// 1-2 sector count
				$file['sector_count'] = hexdec(bin2hex(strrev(substr($sector, $of + 1, 2))));
				// 3-4 starting sector number
				$file['start_sector'] = hexdec(bin2hex(strrev(substr($sector, $of + 3, 2))));
				// 5-12 filename & 13-15 extension
				$file['name'] = trim(substr($sector, $of + 5, 8));
				$file['ext'] = trim(substr($sector, $of + 13, 3));
				$file['filename'] = $file['name'] . (!empty($file['ext']) ? '.' . $file['ext'] : '');
				// add file
				$files[$id++] = $file;
			}
		}

		return $files;
	}

	public function saveFileDirectory()
	{
		$id = 0;
		for ($i = 361; $i <= 368; $i++) {
			$sector = str_pad('', $this->atr->getHeader('sector_size'), chr(0));

			for ($f = 0; $f < 8; $f++) {
				$of = $f * 0x10;
				$file = $this->files[$id];
				// flag
				$flag = 0;
				if ($file['output']) $flag = $flag | (1 << 0);
				if ($file['system']) $flag = $flag | (1 << 1);
				if ($file['locked']) $flag = $flag | (1 << 5);
				if ($file['used']) $flag = $flag | (1 << 6);
				if ($file['deleted']) $flag = $flag | (1 << 7);
				$sector[$of] = pack('C', $flag);
				// sector count
				$data = pack('v', $file['sector_count']);
				$sector[$of + 1] = $data[0];
				$sector[$of + 2] = $data[1];
				// starting sector number
				$data = pack('v', $file['start_sector']);
				$sector[$of + 3] = $data[0];
				$sector[$of + 4] = $data[1];
				// name
				$data = str_pad($file['name'], 8);
				for ($t = 0; $t < 8; $t++) $sector[$of + 5 + $t] = $data[$t];
				// ext
				$data = str_pad($file['ext'], 3);
				for ($t = 0; $t < 3; $t++) $sector[$of + 13 + $t] = $data[$t];

				$id++;
			}

			$this->atr->setSector($i, $sector);
		}		
	}

	public function getFiles($onlyUsed = true)
	{
		if (!$onlyUsed) return $this->files;
		$files = array();
		foreach ($this->files as $id => $file) {
			if ($file['used']) $files[$id] = $file;
		}
		return $files;
	}

	public function getAtr()
	{
		return $this->atr;
	}

	public function getVtoc($key = null)
	{
		if ($key == null) return $this->vtoc;
		if (isset($this->vtoc[$key])) return $this->vtoc[$key];
		return false;
	}

	public function setVtoc($key, $value)
	{
		if (isset($this->vtoc[$key])) {
			$this->vtoc[$key] = $value;
		}
	}

	public function getFileByName($filename)
	{
		foreach ($this->files as $file) {
			if (strtoupper($file['filename']) == strtoupper($filename)) return $this->getFile($file['id']);
		}
		throw new Atari_Exception('File do not exist.');
		return false;
	}

	public function getFile($id)
	{
		if (!isset($this->files[$id])) return false;
		$file = $this->files[$id];

		$data = $this->readFile($file['start_sector'], $file);
		return $data;
	}

	public function setFile($id, $fields)
	{
		foreach ($fields as $key => $value)
		{
			if (isset($this->files[$id][$key])) {
				$this->files[$id][$key] = $value;
			}
		}
	}

	public function putFile($filename, $data)
	{
		$filename = explode('.', preg_replace("/[^A-Z0-9.]/i", '', strtoupper($filename)));
		$name = substr($filename[0], 0, 8);
		$ext = (isset($filename[1]) ? substr($filename[1], 0, 8) : '');

		$size = strlen($data);
		$sectorSize = $this->atr->getHeader('sector_size');
		$dataSize = $sectorSize - 3;
		$sectorsNeeded = ceil($size / $dataSize);

		// check if we have enough space left
		if ($sectorsNeeded > $this->getVtoc('sectors_available')) {
			throw new Atari_Exception('Not enough free space.');
			return false;
		}
		// get first free file id
		$id = -1;
		foreach ($this->getFiles(false) as $file) {
			if (!$file['used']) {
				$id = $file['id'];
				break;
			}
		}
		if ($id < 0) {
			throw new Atari_Exception('Maximum number of files reached.');
		}
		// get sectors
		$sectors = array();
		foreach ($this->getVtoc('bitmap') as $sid => $sec) {
			if ($sec) {
				$sectors[] = $sid;
				$this->updateVtocBitmap($sid, false);
			}
			if (count($sectors) == $sectorsNeeded) break;
		}
		// write file sectors
		for ($i = 0; $i < $sectorsNeeded; $i++) {
			$sector = str_pad('', $sectorSize, chr(0));
			$fileDataSize = $dataSize;
			if ($i == $sectorsNeeded - 1) {
				$fileDataSize = ($size - ($sectorsNeeded - 1) * $dataSize); // data size of last sector
				$nextSector = 0;
				$lastSector = 0x80;
			} else {
				$nextSector = $sectors[$i + 1];
				$lastSector = 0x00;
			}
			// data size + last sector bit
			$sector[$sectorSize - 1] = pack('C', $lastSector | $fileDataSize);
			// next sector number + crc
			$nextData = pack('n', (($id << 10) | $nextSector));
			$sector[$sectorSize - 3] = $nextData[0];
			$sector[$sectorSize - 2] = $nextData[1];
			// data
			for ($fi = 0; $fi < $fileDataSize; $fi++) {
				$sector[$fi] = $data[$fi + $i * $dataSize];
			}
			// save sector data to atr file
			$this->atr->setSector($sectors[$i], $sector);
		}

		// update vtoc
		$this->setVtoc('sectors_available', $this->getVtoc('sectors_available') - $sectorsNeeded);
		// update file
		$this->setFile($id, array(
			'output' => false,
			'locked' => false,
			'used' => true,
			'deleted' => false,
			'sector_count' => $sectorsNeeded,
			'start_sector' => reset($sectors),
			'name' => $name,
			'ext' => $ext,
			'filename' => $name . '.' . $ext,
		));

		$this->saveVtoc();
		$this->saveFileDirectory();

		$this->update();

		return true;
	}

	public function readFile($sectorId, $file, $sectorNum = 1)
	{
		$sector = $this->atr->getSector($sectorId);
		$size = $this->atr->getHeader('sector_size');
		// next word
		$next1 = hexdec(bin2hex($sector[$size - 3]));
		$next2 = hexdec(bin2hex($sector[$size - 2]));
		$next = (($next1 & 0x03) << 8) + $next2;
		// crc
		$crc = ($next1 & 0xFC) >> 2;
		if ($crc != $file['id']) return false;
		// short byte
		$short = hexdec(bin2hex($sector[$size - 1]));
		$lastSector = (($short & 0x80) > 0) || ($sectorNum == $file['sector_count']);
		$dataLength = $short & 0x7F;
		// fetch data
		$data = substr($sector, 0, $dataLength);
		// next sector
		if (!$lastSector) {
			if (!$nextData = $this->readFile($next, $file, $sectorNum + 1)) return false;
			$data .= $nextData;
		}
		// flush data
		return $data;
	}

	public function sectorAvailable($i)
	{
		if (!isset($this->vtoc['bitmap'][$i])) return false;
		return $this->vtoc['bitmap'][$i];
	}

	public function updateVtocBitmap($sector, $value)
	{
		$this->vtoc['bitmap'][$sector] = $value;
	}
}