<?php
/**
 * This file is part of PHP Atari Toolkit - library for manipulating Atari 8-bit
 * computer/emulator files. 
 *
 * @author Petr Kratina <petr.kratina@gmail.com>
 * @package php_atari_toolkit
 * @link https://github.com/AfBu/php_atari_toolkit
 */

class Atari_Atascii
{
	protected $_atasciiTable = null;
	protected $_asciiTable = null;

	public function getAtasciiTable()
	{
		if ($this->_atasciiTable == null) {
			$tableFileName = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'atascii.txt';
			$f = fopen($tableFileName, 'r');
			$this->_atasciiTable = array();
			while ($row = fgets($f)) {
				if ($row[0] == '#' || empty($row)) continue;
				$row = explode("\t", $row);
				$row[0] = trim($row[0]);
				$row[1] = trim($row[1]);
				$this->_atasciiTable[] = chr(hexdec($row[0]));
			}
		}
		return $this->_atasciiTable;
	}

	public function getAsciiTable()
	{
		if ($this->_asciiTable == null) {
			$tableFileName = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'atascii.txt';
			$f = fopen($tableFileName, 'r');
			$this->_asciiTable = array();
			while ($row = fgets($f)) {
				if ($row[0] == '#') continue;
				$row = explode("\t", $row);
				$row[0] = trim($row[0]);
				$row[1] = trim($row[1]);
				$this->_asciiTable[] = Atari_Atascii::unichr(hexdec($row[1]));
			}
		}
		return $this->_asciiTable;
	}

	public function toAtascii($text)
	{
		$text = str_replace("\r\n", "\n", $text);
		$text = str_replace("\r", "\n", $text);
		$ascii = $this->getAsciiTable();
		$atascii = $this->getAtasciiTable();

		$text = Atari_Atascii::mbReplace($ascii, $atascii, $text);

		return $text;
	}

	public function toAscii($text)
	{
		$ascii = $this->getAsciiTable();
		$atascii = $this->getAtasciiTable();

		$text = Atari_Atascii::mbReplace($atascii, $ascii, $text);

		return $text;
	}

	public static function unichr($u) {
	    return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES');
	}

	public static function mbReplace($search, $replace, $subject, &$count=0) {
	    if (!is_array($search) && is_array($replace)) {
	        return false;
	    }
	    if (is_array($subject)) {
	        // call self::mbReplace for each single string in $subject
	        foreach ($subject as &$string) {
	            $string = &self::mbReplace($search, $replace, $string, $c);
	            $count += $c;
	        }
	    } elseif (is_array($search)) {
	        if (!is_array($replace)) {
	            foreach ($search as &$string) {
	                $subject = self::mbReplace($string, $replace, $subject, $c);
	                $count += $c;
	            }
	        } else {
	            $n = max(count($search), count($replace));
	            while ($n--) {
	                $subject = self::mbReplace(current($search), current($replace), $subject, $c);
	                $count += $c;
	                next($search);
	                next($replace);
	            }
	        }
	    } else {
	        $parts = mb_split(preg_quote($search), $subject);
	        $count = count($parts)-1;
	        $subject = implode($replace, $parts);
	    }
	    return $subject;
	}
}