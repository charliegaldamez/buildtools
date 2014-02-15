<?php
/**
 * @package			Twentronix Build Tools
 * @author			Jurian Even
 * @link			https://www.twentronix.com
 * @copyright		Copyright (C) 2012 - 2014 Twentronix. All rights reserved.
 * @license			GNU GPL version 3 or later <http://www.gnu.org/licenses/gpl.html>
 */

require_once 'phing/Task.php';

/**
 * Class MinifyTask
 *
 * Minifies CSS and JS files using YUI compressor.
 *
 * This task requires YUI compressor. You can install it via the terminal with
 * the command "brew install yuicompressor".
 *
 * Example (replace \/ by /):
 * <code>
 *  <minify>
 *		<fileset dir="${dirs.plugins}/system/cookieconfirm/media">
 *			<include name="*.js" />
 *			<include name="**\/*.js" />
 *			<exclude name="*.min.js" />
 *			<exclude name="**\/*.min.js" />
 *		</fileset>
 * 	</minify>
 * </code>
 */
class MinifyTask extends Task
{
	/**
	 * Set to true to output messages and warnings
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 * Collection of filesets
	 * Used when linking contents of a directory
	 *
	 * @var array
	 */
	private $filesets = array();

	/**
	 * The file which is triggered by PHP Storm's File Watcher
	 *
	 * @var null
	 */
	protected $watcherFilePath = null;

	/**
	 * Creator for filesets
	 *
	 * @return FileSet
	 */
	public function createFileset()
	{
		$num = array_push($this->filesets, new FileSet());

		return $this->filesets[$num-1];
	}

	/**
	 * Gets filesets
	 *
	 * @return array
	 */
	public function getFilesets()
	{
		return $this->filesets;
	}

	/**
	 * Generates an array of directories / files to be minified
	 *
	 * @return array|string
	 *
	 * @throws BuildException
	 */
	protected function getMap()
	{
		$fileSets = $this->getFilesets();

		if (empty($fileSets))
		{
			throw new BuildException('Fileset may not be empty');
		}

		$targets = array();

		foreach ($fileSets as $fs)
		{
			if (!($fs instanceof FileSet))
			{
				continue;
			}

			$fromDir = $fs->getDir($this->getProject())->getAbsolutePath();

			if (!is_dir($fromDir))
			{
				$this->log('Directory doesn\'t exist: ' . $fromDir, Project::MSG_WARN);
				continue;
			}

			$fsTargets = array();

			$ds = $fs->getDirectoryScanner($this->getProject());

			$fsTargets = array_merge(
				$fsTargets,
				$ds->getIncludedDirectories(),
				$ds->getIncludedFiles()
			);

			// Add each target to the map
			foreach ($fsTargets as $target)
			{
				if (!empty($target))
				{
					$targets[$target] = realpath($fromDir . DIRECTORY_SEPARATOR . $target);
				}
			}
		}

		return $targets;
	}

	/**
	 * Sets the file which is triggered by PHP Storm's File Watcher.
	 *
	 * @param  PhingFile  The source file. Either a string or an PhingFile object
	 */
	function setWatcherFilePath(PhingFile $file)
	{
		$this->watcherFilePath = $file;
	}

	/**
	 * The main entry point
	 *
	 * @throws BuildException
	 */
	function main()
	{
		$map = $this->getMap();

		if (is_file($this->watcherFilePath))
		{
			// Only minify the file if it's in the fileset
			if (in_array($this->watcherFilePath, $map))
			{
				$this->minify($this->watcherFilePath);
			}
		}
		else
		{
			foreach ($map as $file)
			{
				$this->minify($file);
			}
		}	
		
		return true;
	}

	/**
	 * Minifies a file using YUI compressor.
	 *
	 * @param $file
	 *
	 * @throws BuildException
	 */
	protected function minify($file)
	{
		echo "\t\t\t$file\n";

		$ext = pathinfo($file, PATHINFO_EXTENSION);

		if (in_array($ext, array('css', 'js')))
		{
			exec("yuicompressor ".escapeshellarg($file)." -o '.".$ext."$:.min.".$ext."' ".escapeshellarg($file)." ".($this->debug ? '-v' : '')." --line-break 5000 --charset utf-8");
		}
		else
		{
			throw new BuildException('Invalid file extension!');
		}
	}
}