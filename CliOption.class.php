<?php

/** CliOptions class set
 * http://github.com/tremby/clioptions
 *
 * Copyright 2011 Bart Nagel (bart@tremby.net)
 *
 * This program is free software: you can redistribute it and/or modify it under 
 * the terms of the GNU General Public License as published by the Free Software 
 * Foundation, either version 3 of the License, or (at your option) any later 
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more 
 * details.
 *
 * You should have received a copy of the GNU General Public License along with 
 * this program. If not, see <http://www.gnu.org/licenses/>.
 */

/** CliOption class
 * This doesn't need to be directly interacted with, but its constructor is 
 * directly invoked by the CliOptions class's add method.
 */
class CliOption {
	const TYPE_SWITCH = 0;
	const TYPE_ACCUMULATOR = 1;
	const TYPE_VALUE = 2;
	const TYPE_OPTIONALVALUE = 3;
	const TYPE_MULTIPLEVALUE = 4;

	private $short;
	private $long;
	private $type;
	private $default;
	private $helptext;

	/** constructor
	 * All arguments are optional and are skipped by giving null values, though 
	 * at least one of the first two arguments must be given.
	 * The arguments:
	 *	$short
	 *		The option's short name
	 *	$long
	 *		The option's long name
	 *	$type
	 *		One of the TYPE_* constants defined in this class
	 *	$default
	 *		The default value for the option -- this should seldom need to be 
	 *		overridden
	 *	$helptext
	 *		Some help text to be output alongside the option's forms, type and default 
	 *		when CliOptions::showopts() is called
	 *
	 * This method is fairly picky about the datatypes it is given.
	 */
	public function __construct($short = null, $long = null, $type = self::TYPE_SWITCH, $default = null, $helptext = null) {
		if (!is_null($short) && (!is_string($short) || !preg_match('%^[a-zA-Z0-9]$%', $short)))
			die("CliOption: short option must be one alphanumeric character\n");
		$this->short = $short;

		if (!is_null($long) && (!is_string($long) || !preg_match('%^[a-zA-Z0-9][a-zA-Z0-9-]+$%', $long)))
			die("CliOption: long option must be a string at least two characters long, the first of which is alphanumeric, the rest of which also allowing hyphens\n");
		$this->long = $long;

		if (is_null($short) && is_null($long))
			die("CliOption: option must have at least one of short and long forms\n");

		switch ($type) {
			case self::TYPE_SWITCH:
				if (is_null($this->default))
					$default = false;
				else if (!is_bool($default))
					die("CliOption: default must be a boolean for switch type, or null to keep the basic default\n");
				break;
			case self::TYPE_ACCUMULATOR:
				if (is_null($this->default))
					$default = 0;
				else if (!is_int($default))
					die("CliOption: default must be zero or a positive integer for accumulator type, or null to keep the basic default\n");
				break;
			case self::TYPE_VALUE:
				if (is_null($default))
					$default = false;
				else if (!is_string($default))
					die("CliOption: default must be a string for value type, or null to keep the basic default\n");
				break;
			case self::TYPE_OPTIONALVALUE:
				if (is_null($default))
					$default = false;
				else if (!is_bool($default) && !is_string($default))
					die("CliOption: default must be a boolean or a string for optional value type, or null to keep the basic default\n");
				break;
			case self::TYPE_MULTIPLEVALUE:
				$errormessage = "CliOption: default must be an array (which could be empty) of string values for multiple value type, or null to keep the basic default";
				if (is_null($default))
					$default = array();
				else if (!is_array($default))
					die($errormessage);
				foreach ($default as $value)
					if (!is_string($value))
						die($errormessage);
				break;
			default:
				die("CliOption: one of the CliOption::TYPE_* constants should be used for type\n");
		}
		$this->type = $type;
		$this->default = $default;

		$this->helptext = $helptext;
	}

	/** short
	 * Return the option's short name
	 */
	public function short() {
		return $this->short;
	}

	/** long
	 * Return the option's long name
	 */
	public function long() {
		return $this->long;
	}

	/** type
	 * Return the option's type
	 */
	public function type() {
		return $this->type;
	}

	/** def
	 * Return the option's default value
	 */
	public function def() {
		return $this->default;
	}

	/** helptext
	 * Return the object's help text
	 */
	public function helptext() {
		return $this->helptext;
	}

	/** hasshort
	 * Return true if the option has a short name
	 */
	public function hasshort() {
		return !is_null($this->short);
	}

	/** haslong
	 * Return true if the option has a long name
	 */
	public function haslong() {
		return !is_null($this->long);
	}

	/** longest
	 * Return the option's long name if it has one, otherwise its short name
	 */
	public function longest() {
		if ($this->haslong())
			return $this->long();
		return $this->short();
	}

	/** cantakearg
	 * Return true if the option can take an argument
	 */
	public function cantakearg() {
		return in_array($this->type(), array(
			self::TYPE_VALUE,
			self::TYPE_OPTIONALVALUE,
			self::TYPE_MULTIPLEVALUE,
		));
	}

	/** musttakearg
	 * Return true if the option requires an argument
	 */
	public function musttakearg() {
		return in_array($this->type(), array(
			self::TYPE_VALUE,
			self::TYPE_MULTIPLEVALUE,
		));
	}
}

?>
