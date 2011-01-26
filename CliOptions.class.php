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

require_once "CliOptionException.class.php";
require_once "CliOptionTakesNoArgumentException.class.php";
require_once "CliOptionRequiresArgumentException.class.php";
require_once "CliOptionNotFoundException.class.php";
require_once "CliOption.class.php";

/** CliOptions class
 * This is the only class which needs to be directly interacted with, though the 
 * arguments for the constructor of CliOption must be used for the add method 
 * and its constants must also be used.
 * This class is instantiated and given a source array of commandline arguments. 
 * This defaults to $_SERVER["argv"].
 */
class CliOptions {
	private $options;
	private $source;

	/** constructor
	 * Optionally takes an array of command line arguments, but uses 
	 * $_SERVER["argv"] by default. If using $_SERVER["argv"] the first argument 
	 * is discarded.
	 */
	public function __construct($source = null) {
		if (is_null($source))
			$source = $_SERVER["argv"];

		if ($source == $_SERVER["argv"])
			array_shift($source);

		if (!is_array($source))
			die("CliOptions: source must be an array");
		$this->source = $source;

		$this->options = array();
	}

	/** add
	 * Add an option -- takes the arguments of the CliOption constructor and 
	 * returns this CliOption object so calls can be chained together
	 */
	public function add() {
		$args = func_get_args();
		$reflection = new ReflectionClass("CliOption");
		$option = $reflection->newInstanceArgs($args);
		$this->options[] = $option;
		return $this;
	}

	/** showopts
	 * Print out each option and its type, default and any help text
	 */
	public function showopts() {
		foreach ($this->options as $option) {
			if ($option->hasshort()) {
				if ($option->musttakearg()) {
					echo "-" . $option->short() . "<ARG>\n";
					echo "-" . $option->short() . " <ARG>\n";
				} else if ($option->cantakearg())
					echo "-" . $option->short() . "[<ARG>]\n";
				else
					echo "-" . $option->short() . "\n";
			}
			if ($option->haslong()) {
				if ($option->musttakearg()) {
					echo "--" . $option->long() . "=<ARG>\n";
					echo "--" . $option->long() . " <ARG>\n";
				} else if ($option->cantakearg())
					echo "--" . $option->long() . "[=<ARG>]\n";
				else
					echo "--" . $option->long() . "\n";
			}
			echo "\t";
			switch ($option->type()) {
				case CliOption::TYPE_SWITCH:
					echo "switch; default value is " . ($option->def() ? "true" : "false");
					break;
				case CliOption::TYPE_ACCUMULATOR:
					echo "accumulating switch; default value is " . $option->def();
					break;
				case CliOption::TYPE_VALUE:
					echo "option requiring a value; " . ($option->def() === false ? "no default value" : "default value is '" . $option->def() . "'");
					break;
				case CliOption::TYPE_OPTIONALVALUE:
					echo "option with an optional value; default value is " . ($option->def() === false ? "unset" : ($option->def() === true ? "set" : "'" . $option->def() . "'"));
					break;
				case CliOption::TYPE_MULTIPLEVALUE:
					echo "requires a value and can be used multiple times; ";
					if (count($option->def()) == 0)
						echo "no default values";
					else
						echo "default values are '" . implode("', '", $option->def()) . "'";
					break;
			}
			echo "\n";
			if (strlen($option->helptext()))
				echo "\t" . $option->helptext() . "\n";
		}
	}

	// return true if there are more arguments left to process
	private function moreargs() {
		return count($this->source) > 0;
	}

	// return the next argument and remove it from the source array
	private function getarg() {
		if (!$this->moreargs())
			die("CliOptions: tried to get an argument but there were none left -- should be using CliOptions::moreargs()");
		return array_shift($this->source);
	}

	// prepend an argument to the source array
	private function prependarg($arg) {
		array_unshift($this->source, $arg);
	}

	/** getopts
	 * Parse the commandline arguments in the source array and return an 
	 * associative array
	 * Any non-option arguments are in an array given by the "_" index, in the 
	 * order in which they appeared on the commandline.
	 * Any number of these are accepted -- if they're to be disallowed or 
	 * limited you need to write the logic for that yourself.
	 * Other indices are for each option -- the index is the long option name if 
	 * it exists, otherwise the short option.
	 * The values depend on the option type: boolean for switch, integer for an 
	 * accumulator, string or false (false meaning the option wasn't given) for 
	 * an option requiring an argument, string or boolean for an option with an 
	 * optional argument (false meaning the option wasn't given, true meaning it 
	 * was but no argument was given) and an array of strings in the order in 
	 * which they were given on the commandline for an option which can take 
	 * multiple values.
	 */
	public function getopts() {
		$nomoreoptions = false;

		// set all defaults
		$options = array(
			"_" => array(),
		);
		foreach ($this->options as $option)
			$options[$option->longest()] = $option->def();

		while ($this->moreargs()) {
			$arg = $this->getarg();

			if (!$nomoreoptions && $arg == "--") {
				$nomoreoptions = true;
				continue;
			}

			if ($nomoreoptions) {
				$options["_"][] = $arg;
				continue;
			}

			if (substr($arg, 0, 2) == "--") {
				// long option
				$key = substr($arg, 2);

				// some long arguments can take an argument
				$value = null;

				// an argument can be directly afterwards, separated by an =
				// if the argument is optional it must be given this way
				if (strpos($key, "=") !== false)
					list($key, $value) = explode("=", $key, 2);

				// find the option the user meant
				$option = $this->getoptionfromlong($key);

				// if we have a value already but the option can't take one 
				// throw an error
				if (!is_null($value) && !$option->cantakearg())
					throw new CliOptionTakesNoArgumentException("Option '--" . $option->long() . "' cannot take an argument");

				// if the option must take an argument it could also be given 
				// afterwards, so if it's required and we don't have one yet we 
				// need to grab it
				if ($option->musttakearg() && is_null($value)) {
					if (!$this->moreargs())
						throw new CliOptionRequiresArgumentException("Option '--" . $option->long() . "' requires an argument");
					$value = $this->getarg();
				}
			} else if ($arg[0] == "-") {
				// short option letter
				$key = $arg[1];

				// there may be more options or a value
				$remainder = substr($arg, 2);

				// find the option
				$option = $this->getoptionfromshort($key);

				// if there are more characters, this is an argument if the 
				// option supports one or otherwise it's more options
				// if there are no more characters, look for an argument only if 
				// the option requires one (if it's optional it needs to be 
				// given directly after the option letter)
				$value = null;
				if (strlen($remainder) > 0) {
					if ($option->cantakearg())
						$value = $remainder;
					else
						$this->prependarg("-" . $remainder);
				} else if ($option->musttakearg()) {
					if (!$this->moreargs())
						throw new CliOptionRequiresArgumentException("Option '-" . $option->short() . "' requires an argument");
					$value = $this->getarg();
				}
			} else {
				$options["_"][] = $arg;
				continue;
			}

			switch ($option->type()) {
				case CliOption::TYPE_SWITCH:
					$options[$option->longest()] = true;
					break;
				case CliOption::TYPE_ACCUMULATOR:
					$options[$option->longest()]++;
					break;
				case CliOption::TYPE_VALUE:
				case CliOption::TYPE_OPTIONALVALUE:
					$options[$option->longest()] = is_null($value) ? true : $value;
					break;
				case CliOption::TYPE_MULTIPLEVALUE:
					$options[$option->longest()][] = $value;
					break;
			}
		}

		return $options;
	}

	// return the CliOption object corresponding to the given long option name, 
	// searching for a unique expansion if an exact match isn't found
	private function getoptionfromlong($key) {
		// look for exact match
		foreach ($this->options as $option) {
			if (!$option->haslong())
				continue;
			if ($option->long() == $key)
				return $option;
		}

		// look for non-ambiguous abbreviation
		$abbreviations = array();
		foreach ($this->options as $option) {
			if (!$option->haslong())
				continue;
			if (strpos($option->long(), $key) === 0)
				$abbreviations[] = $option;
		}
		if (count($abbreviations) == 1)
			return $abbreviations[0];
		if (count($abbreviations) == 0)
			throw new CliOptionNotFoundException("Long option '--$key' doesn't exist, nor does it abbreviate any existing long option");
		$fullnames = array();
		foreach ($abbreviations as $option)
			$fullnames[] = $option->long();
		throw new CliOptionNotFoundException("Long option '--$key' doesn't exist but is an ambiguous abbreviation of '" . implode("', '", $fullnames) . "'");
	}

	// return the CliOption object corresponding to the given short option name
	private function getoptionfromshort($key) {
		foreach ($this->options as $option) {
			if (!$option->hasshort())
				continue;
			if ($option->short() == $key)
				return $option;
		}
		throw new CliOptionNotFoundException("Short option '-$key' doesn't exist");
	}
}

?>
