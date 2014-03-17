<?php
/**
 * @package			Twentronix Build Tools
 * @author			Jurian Even
 * @link			https://www.twentronix.com
 * @copyright		Copyright (C) 2013 - 2014 Twentronix. All rights reserved.
 * @license			GNU GPL version 3 or later <http://www.gnu.org/licenses/gpl.html>
 */

require_once 'phing/Task.php';
require_once 'phing/tasks/ext/svn/SvnBaseTask.php';

/**
 * Class ExtensionVersionTask
 *
 * Retrieves latest extension version from the CHANGELOG.
 *
 * Example:
 *
 * <taskdef name="extversion" classname="phingext.ExtensionVersionTask" />
 *
 * <code>
 *  <extversion file="${dirs.root}/CHANGELOG" propertyname="ext.version" />
 * </code>
 */
class ExtensionVersionTask extends Task
{
	/**
	 * The filename of the CHANGELOG
	 *
	 * @var string
	 */
	protected $file;

	/**
	 * The property name
	 *
	 * @var string
	 */
	private $propertyName = "ext.version";

	/**
	 * Sets the CHANGELOG filename to get the version from
	 */
	public function setFile($file)
	{
		$this->file = $file;
	}

	/**
	 * Returns the name of the property to use
	 */
	public function getFile()
	{
		return $this->file;
	}

    /**
     * Sets the name of the property to use
     */
	public function setPropertyName($propertyName)
    {
        $this->propertyName = $propertyName;
    }

    /**
     * Returns the name of the property to use
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * The main entry point
     */
    function main()
    {
		$filePath = $this->getFile();

		if (empty($filePath))
		{
			throw new BuildException('ExtensionVersionTask - Please set the file parameter');
		}

		$changelog = file_get_contents($filePath);
		preg_match('#[0-9]+\.[0-9]+\.[a-zA-Z0-9]+#s', $changelog, $matches);
		$version = $matches[0];

		$this->project->setProperty($this->getPropertyName(), $version);
    }
}