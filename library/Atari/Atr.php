<?php
/**
 * This file is part of PHP Atari Toolkit - library for manipulating Atari 8-bit
 * computer/emulator files. 
 *
 * @author Petr Kratina <petr.kratina@gmail.com>
 * @package php_atari_toolkit
 * @link https://github.com/AfBu/php_atari_toolkit
 */

require_once 'Atari/Fms.php';
require_once 'Atari/Fms/Dos.php';

/*
ATR File Structure

WORD	 wMagic	 $0296 (sum of 'NICKATARI')
WORD	 wPars	 size of this disk image, in paragraphs (size/$10)
WORD	 wSecSize	 sector size. ($80 or $100) bytes/sector
BYTE	 btParsHigh	 high part of size, in paragraphs (added by REV 3.00)
DWORD	 dwCRC	 32bit CRC of file (added by APE?)
DWORD	 dwUnused	 unused
BYTE	 btFlags	 bit 0 (ReadOnly) (added by APE?)
*/

class Atari_Atr
{
	protected $headers;
	protected $sectors;
	protected $fs;

	public function __construct($filename = null)
	{
		$this->fs = new Atari_Fms_Dos($this, false);

		if ($filename != null && file_exists($filename)) {
			$this->load($filename);
		} else {

		}
	}

	public function load($filename)
	{
		$fp = fopen($filename, 'r');

		$this->headers = $this->parseHeaders($fp);
		$this->sectors = $this->loadSectors($fp, $this->headers);

		fclose($fp);

		$this->fs->update();
	}

	public function save($filename)
	{
		$fp = fopen($filename, 'w');

		$this->saveHeaders($fp, $this->getHeaders());
		$this->saveSectors($fp, $this->getSectors());

		fclose($fp);
	}

	protected function saveHeaders($fp, $headers)
	{
		$data = pack('vvvCVVC', 0x0296, $headers['size'] / 16, $headers['sector_size'], $headers['high_size'], $headers['crc'], 0x00000000, $headers['flags']);
		fwrite($fp, $data);
	}

	protected function saveSectors($fp, $sectors)
	{
		foreach ($sectors as $sector) {
			fwrite($fp, $sector);
		}
	}

	protected function parseHeaders($fp)
	{
		// seek start of file
		fseek($fp, 0);

		// load data as binary-string, reverse to deal with little endian
		$format = strrev(fread($fp, 2));
		$size = strrev(fread($fp, 2));
		$sectorSize = strrev(fread($fp, 2));
		$highSize = fread($fp, 1);
		$crc = strrev(fread($fp, 4));
		$unused = strrev(fread($fp, 4));
		$flags = fread($fp, 1);
		
		$headers = array(
			'format' => ($this->binToInt($format) == 0x0296 ? 'atr' : 'unknown'),
			'size' => $this->binToInt($size) * 16,
			'sector_size' => $this->binToInt($sectorSize),
			'sector_count' => ($this->binToInt($size) * 16) / $this->binToInt($sectorSize),
			'high_size' => $this->binToInt($highSize),
			'crc' => $this->binToInt($crc),
			'flags' => $this->binToInt($flags),
			// for debug purposes, bin2hex dumps of values
			'format_hex' => bin2hex($format),
			'size_hex' => bin2hex($size),
			'sector_size_hex' => bin2hex($sectorSize),
			'high_size_hex' => bin2hex($highSize),
			'crc_hex' => bin2hex($crc),
			'flags_hex' => bin2hex($flags),
		);

		return $headers;
	}

	protected function loadSectors($fp, $headers)
	{
		// seek start of raw data
		fseek($fp, 0x10);

		// load all sectors
		$sectors = array();
		for ($i = 0; $i < $this->headers['sector_count']; $i++) {
			$sectors[] = fread($fp, $this->headers['sector_size']);
		}

		return $sectors;
	}

	protected function binToInt($binary)
	{
		return hexdec(bin2hex($binary));
	}

	public function getHeaders()
	{
		return $this->headers;
	}

	public function getHeader($key)
	{
		if (isset($this->headers[$key])) return $this->headers[$key];
		return false;
	}

	public function getSectors()
	{
		return $this->sectors;
	}

	public function getSector($i)
	{
		if (isset($this->sectors[$i - 1])) return $this->sectors[$i - 1];
		return false;
	}

	public function setSector($i, $data)
	{
		if (isset($this->sectors[$i - 1])) $this->sectors[$i - 1] = $data;
	}

	public function getFs()
	{
		return $this->fs;
	}
}