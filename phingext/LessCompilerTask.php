<?php
/**
 * @package			Twentronix Build Tools
 * @author			Jurian Even
 * @link			https://www.twentronix.com
 * @copyright		Copyright (C) 2012 - 2014 Twentronix. All rights reserved.
 * @license			GNU GPL version 3 or later <http://www.gnu.org/licenses/gpl.html>
 */

require_once 'phing/Task.php';
require_once 'lesscompiler/less.inc.php';

/**
 * Class LessCompilerTask
 *
 * Compiles LESS using LESS compiler of http://lesscss.org/.
 *
 * Parameters:
 * - todir (optional)	The directory to write CSS files to. Default directory: /../css
 * - formatter			The LESS formatter to use
 * - preserveComments	Do we need to preserve comments? Set to 0 to remove comments
 *
 * Example (replace \/ by /):
 * <code>
 *  <compileless todir="${dirs.plugins}/system/cookieconfirm/media/css">
 *		<fileset dir="${dirs.plugins}/system/cookieconfirm/media">
 *			<include name="*.less" />
 *			<include name="**\/*.less" />
 *		</fileset>
 * 	</compileless>
 * </code>
 *
 * @see https://github.com/leafo/lessphp
 */
class LessCompilerTask extends Task
{
	protected $_toDir;

	protected $_importDir;

	/**
	 * The used LESS formatter
	 *
	 * Possible values:
	 * lessjs (default) — Same style used in LESS for JavaScript
	 * compressed 		— Compresses all the unrequired whitespace
	 * classic 			— lessphp’s original formatter
	 */
	protected $_formatter = 'classic';

	/**
	 * Set to true to preserve comments
	 */
	protected $_preserveComments = true;

	/**
	 * Collection of filesets
	 * Used when linking contents of a directory
	 */
	private $_filesets = array();

	/**
	 * Sets the target directory
	 *
	 * @param PhingFile $path
	 */
	public function setToDir(PhingFile $path)
	{
		$this->_toDir = $path;
	}

	/**
	 * Sets the import directory
	 *
	 * @param PhingFile $path
	 */
	public function setImportDir(PhingFile $path)
	{
		$this->_importDir = $path;
	}

	/**
	 * Sets the LESS formatter
	 *
	 * @param $formatter
	 */
	public function setFormatter($formatter)
	{
		$this->_formatter = $formatter;
	}

	/**
	 * Sets $preserveComments
	 *
	 * @param $preserveComments
	 */
	public function setPreserveComments($preserveComments)
	{
		$this->_preserveComments = $preserveComments;
	}

	/**
	 * Creator for _filesets
	 *
	 * @return FileSet
	 */
	public function createFileset()
	{
		$index = array_push($this->_filesets, new FileSet());

		return $this->_filesets[$index-1];
	}

	/**
	 * Gets filesets
	 *
	 * @return array
	 */
	public function getFilesets()
	{
		return $this->_filesets;
	}

	/**
	 * Returns the destination directory to output the CSS file to.
	 */
	function getToDir()
	{
		return $this->_toDir;
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

	public function init()
	{
		// Go one level up, else we get the build directory
		$this->_importDir = array(realpath($this->project->getBasedir()->getAbsolutePath() . '/..'));

		return true;
	}

	/**
	 * The main entry point
	 *
	 * @throws BuildException
	 */
	function main()
	{
		$lessc = new lessc;
		$lessc->setImportDir($this->_importDir);
		$lessc->setFormatter($this->_formatter);
		$lessc->setPreserveComments($this->_preserveComments);

		$map = $this->getMap();

		foreach ($map as $file)
		{
			echo "\t\t\t$file\n";

			try
			{
				$pathParts = pathinfo($file);
				$dirName = $pathParts['dirname'];
				$fileName = $pathParts['filename'];
				$targetDir = $dirName . '/../css';

				if (!empty($this->_toDir))
				{
					$targetPhingFile = new PhingFile($this->_toDir);
					$targetDir = $targetPhingFile->getPath();
				}

				if (!is_dir($targetDir))
				{
					mkdir($targetDir, 0755);
				}

				$targetFile = $targetDir . '/' . $fileName . '.css';
				$lessc->compileFile($file, $targetFile);
			}
			catch (Exception $e)
			{
				$this->log("Error compiling LESS file " . $file . ": " . $e->getMessage(), Project::MSG_ERR);
			}
		}

		return true;
	}
}