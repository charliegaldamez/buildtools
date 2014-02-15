<?php
/**
 * @package			Twentronix Build Tools
 * @author			Jurian Even
 * @link			https://www.twentronix.com
 * @copyright		Copyright (C) 2012 - 2014 Twentronix. All rights reserved.
 * @license			GNU GPL version 3 or later <http://www.gnu.org/licenses/gpl.html>
 */

require_once 'phing/Task.php';
require_once 'less/less.php';
require_once 'less/parser/parser.php';

/**
 * Class LessCompileTask
 *
 * Compiles LESS using LESS compiler of http://leafo.net/lessphp/.
 *
 * Parameters:
 * - file				The LESS file to compile. Alternatively you could provide a fileset
 * - todir (optional)	The directory to write CSS files to. Default directory: /../css
 * - formatter			The LESS formatter to use
 * - preserveComments	Do we need to preserve comments? Set to 0 to remove comments
 *
 *
 * Example (replace \/ by /):
 * <code>
 *  <lesscompile todir="${dirs.plugins}/system/cookieconfirm/media/css">
 *		<fileset dir="${dirs.plugins}/system/cookieconfirm/media">
 *			<include name="*.less" />
 *			<include name="**\/*.less" />
 *		</fileset>
 * 	</lesscompile>
 * </code>
 *
 * @see https://github.com/leafo/lessphp
 */
class LessCompileTask extends Task
{
	protected $file = null;

	protected $toDir;

	protected $importDir;

	/**
	 * The used LESS formatter
	 *
	 * Possible values:
	 * lessjs (default) — Same style used in LESS for JavaScript
	 * compressed 		— Compresses all the unrequired whitespace
	 * classic 			— lessphp’s original formatter
	 */
	protected $formatter = 'classic';

	/**
	 * Set to true to preserve comments
	 */
	protected $preserveComments = true;

	/**
	 * Collection of filesets
	 * Used when linking contents of a directory
	 */
	private $filesets = array();

	/**
	 * Sets the LESS file to convert.
	 *
	 * @param  PhingFile  The source file. Either a string or an PhingFile object
	 */
	function setFile(PhingFile $file)
	{
		$this->file = $file;
	}

	/**
	 * Sets the target directory
	 *
	 * @param PhingFile $path
	 */
	public function setToDir(PhingFile $path)
	{
		$this->toDir = $path;
	}

	/**
	 * Sets the import directory
	 *
	 * @param PhingFile $path
	 */
	public function setImportDir(PhingFile $path)
	{
		$this->importDir = $path;
	}

	/**
	 * Sets the LESS formatter
	 *
	 * @param $formatter
	 */
	public function setFormatter($formatter)
	{
		$this->formatter = $formatter;
	}

	/**
	 * Sets $preserveComments
	 *
	 * @param $preserveComments
	 */
	public function setPreserveComments($preserveComments)
	{
		$this->preserveComments = $preserveComments;
	}

	/**
	 * Creator for filesets
	 *
	 * @return FileSet
	 */
	public function createFileset()
	{
		$index = array_push($this->filesets, new FileSet());

		return $this->filesets[$index-1];
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
	 * Returns the destination directory to output the CSS file to.
	 */
	function getToDir()
	{
		return $this->toDir;
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
			return array();
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
		$this->importDir = array(realpath($this->project->getBasedir()->getAbsolutePath() . '/..'));

		return true;
	}

	/**
	 * The main entry point
	 *
	 * @throws BuildException
	 */
	function main()
	{
		require_once 'less/formatter/' . $this->formatter . '.php';

		$less = new TxbtLess;
		$less->setImportDir($this->importDir);
		$less->setFormatter($this->formatter);
		$less->setPreserveComments($this->preserveComments);

		if ($this->file !== null && $this->file->exists())
		{
			$this->compileFile($less, $this->file);
		}
		else
		{
			$map = $this->getMap();

			foreach ($map as $file)
			{
				$this->compileFile($less, $file);
			}
		}

		return true;
	}

	/**
	 * Compiles a LESS file.
	 *
	 * @param $less
	 * @param $file
	 */
	private function compileFile($less, $file)
	{
		echo "\t\t\t$file\n";

		try
		{
			$pathParts = pathinfo($file);
			$dirName = $pathParts['dirname'];
			$fileName = $pathParts['filename'];
			$targetDir = $dirName . '/../css';

			if (!empty($this->toDir))
			{
				$targetPhingFile = new PhingFile($this->toDir);
				$targetDir = $targetPhingFile->getPath();
			}

			if (!is_dir($targetDir))
			{
				mkdir($targetDir, 0755);
			}

			$targetFile = $targetDir . '/' . $fileName . '.css';
			$less->compileFile($file, $targetFile);
		}
		catch (Exception $e)
		{
			$this->log("Error compiling LESS file " . $file . ": " . $e->getMessage(), Project::MSG_ERR);
		}
	}
}