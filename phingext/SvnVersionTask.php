<?php
/**
 * This file is copied verbatim from Akeeba Build Tools.
 *
 * @link	https://github.com/akeeba/buildfiles
 */

require_once 'phing/Task.php';
require_once 'phing/tasks/ext/svn/SvnBaseTask.php';

// Required for Zend Server 6 on Mac OS X
putenv("DYLD_LIBRARY_PATH=''");

/**
 * SVN latest tree version to Phing property
 *
 * @version   $Id: SvnVersionTask.php 690 2011-06-02 19:58:58Z nikosdion $
 * @package   akeebabuilder
 * @copyright Copyright (c)2009-2014 Nicholas K. Dionysopoulos
 * @license   GNU GPL version 3 or, at your option, any later version
 * @author    nicholas
 */
class SvnVersionTask extends SvnBaseTask
{
	private $propertyName = "svn.version";

	/**
	 * Sets the name of the property to use
	 */
	function setPropertyName($propertyName)
	{
		$this->propertyName = $propertyName;
	}

	/**
	 * Returns the name of the property to use
	 */
	function getPropertyName()
	{
		return $this->propertyName;
	}

	/**
	 * Sets the path to the working copy
	 */
	function setWorkingCopy($wc)
	{
		$this->workingCopy = $wc;
	}

	/**
	 * The main entry point
	 *
	 * @throws BuildException
	 */
	function main()
	{
		$this->setup('info');


		exec('svnversion ' . escapeshellarg($this->workingCopy), $out);
		if (strpos($out[0], ':') === false)
		{
			$version = intval($out[0]);
		}
		else
		{
			$parts = explode(':', $out[0]);
			$version = intval($parts[1]);
		}

		if ($version > 0)
		{
			$this->project->setProperty($this->getPropertyName(), $version);
		}
		else
		{
			$this->_use_gitsvn();
		}
	}

	function _use_gitsvn()
	{
		$path = realpath($this->workingCopy);
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		{
			$mydir = getcwd();
			chdir($path);
			exec('git svn info', $out);
			chdir($mydir);
		}
		else
		{
			exec('pushd ' . escapeshellarg($path) . ' > /dev/null; git svn info; popd > /dev/null', $out);
		}


		if (empty($out))
		{
			throw new BuildException("Failed to parse the output of 'git svn info'.");

			return;
		}

		$version = 0;
		foreach ($out as $line)
		{
			$parts = explode(':', $line, 2);
			if ($parts[0] != 'Revision')
			{
				continue;
			}

			$version = intval($parts[1]);
		}

		if ($version > 0)
		{
			$this->project->setProperty($this->getPropertyName(), $version);
		}
		else
		{
			throw new BuildException("Failed to parse the output of 'git svn info'.");
		}
	}
}