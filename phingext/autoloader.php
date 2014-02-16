<?php
/**
 * @package			TwentronixBuildTools
 * @author			Jurian Even
 * @link			https://www.twentronix.com
 * @copyright		Copyright (C) 2013 - 2014 Twentronix. All rights reserved.
 * @license			GNU GPL version 3 or later <http://www.gnu.org/licenses/gpl.html>
 */

/**
 * The autoloader for phingext directories.
 */
class TxbtAutoloader
{
	/**
	 * An instance of this autoloader
	 *
	 * @var   TxbtAutoloader
	 */
	public static $autoloader = null;

	/**
	 * The path to the phingext root directory
	 *
	 * @var   string
	 */
	public static $phingextPath = null;

	/**
	 * Initialise this autoloader, using a singleton
	 *
	 * @return  TxbtAutoloader
	 */
	public static function init()
	{
		if (self::$autoloader === null)
		{
			self::$autoloader = new self;
		}

		return self::$autoloader;
	}

	/**
	 * Public constructor. Registers the autoloader with PHP.
	 */
	public function __construct()
	{
		self::$phingextPath = realpath(__DIR__);

		spl_autoload_register(array($this, 'autoload_phingext'));
	}

	/**
	 * The actual auto loader
	 *
	 * @param   string  $class_name  The name of the class to load
	 *
	 * @return  void
	 */
	public function autoload_phingext($class_name)
	{
		// Make sure the class has a Txbt and not a TxbtShortcut prefix
		if (substr($class_name, 0, 4) != 'Txbt')
		{
			return;
		}

		// Remove the Txbt prefix
		$class = substr($class_name, 4);

		// Change from camel cased (e.g. ViewHtml) into a lowercase array (e.g. 'view','html')
		$class = preg_replace('/(\s)+/', '#', $class);
		$class = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '#\\1', $class));
		$class = explode('#', $class);

		// First try finding in structured directory format (preferred)
		$relPath = implode('/', $class);
		$path = self::$phingextPath . '/' . $relPath . '.php';
		if (@file_exists($path))
		{
			include_once $path;
		}

		// Then try the duplicate last name structured directory format (not recommended)
		if (!class_exists($class_name, false))
		{
			$lastPart = end($class);
			$path = self::$phingextPath . '/' . $relPath . '/' . $lastPart . '.php';
			if (@file_exists($path))
			{
				include_once $path;
			}
		}
	}
}