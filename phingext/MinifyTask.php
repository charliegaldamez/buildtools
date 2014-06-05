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
 * Merging multiple files of a fileset into one file is also supported. This
 * can be done by setting the 'tofile' property. Be aware that the fileset should
 * only contain js OR css files.
 *
 * Example (replace \/ by /):
 * <code>
 *  <minify watcherfilepath="${watcher.file.path}" tofile="${dirs.plugins}/system/cookieconfirm/media/js/combined.min.js">
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
	 * Set to true to output messages and warnings.
	 *
	 * @var bool
	 */
	private $debug = false;

	/**
	 * Collection of filesets
	 *
	 * Used when linking contents of a directory.
	 *
	 * @var array
	 */
	private $filesets = array();

	/**
	 * The destination file.
	 *
	 * @var null
	 */
	protected $toFile = null;

	/**
	 * The file which is triggered by PHP Storm's File Watcher.
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
					$targetPath = realpath($fromDir . DIRECTORY_SEPARATOR . $target);

					// Do not add the temp merge filepath to the map
					if ($targetPath !== $this->getTempMergeFilePath())
					{
						$targets[$target] = $targetPath;
					}
				}
			}
		}

		return $targets;
	}

	/**
	 * Gets the destination file.
	 *
	 * @return  object  PhingFile  The destination file. Either a string or a PhingFile object.
	 */
	public function getTofile()
	{
		return $this->toFile;
	}

	/**
	 * Gets the absolute destination file path.
	 *
	 * @return  null|string  The absolute destination file path or 'null' if 'to file' is not provided.
	 */
	protected function getTofilePath()
	{
		$toFilePath = null;

		$toFile = $this->getTofile();

		if ($toFile)
		{
			$toFilePath = $toFile->getAbsolutePath();
		}

		return $toFilePath;
	}

	/**
	 * Sets the destination file.
	 *
	 * @param  object  PhingFile  The destination file. Either a string or a PhingFile object.
	 */
	public function setTofile(PhingFile $file)
	{
		$this->toFile = $file;
	}

	/**
	 * Sets the file which is triggered by PHP Storm's File Watcher.
	 *
	 * @param  object  PhingFile  The source file. Either a string or a PhingFile object.
	 */
	public function setWatcherFilePath(PhingFile $file)
	{
		$this->watcherFilePath = $file;
	}

	/**
	 * The main entry point
	 *
	 * @throws BuildException
	 */
	public function main()
	{
		// Get the files from the fileset
		$files = array();
		$map = $this->getMap();

		foreach ($map as $file)
		{
			$files[] = $file;

			if ($this->needToMergeFiles())
			{
				echo "\t\t\t  - $file" . PHP_EOL;
			}
		}

		if ($this->needToMergeFiles())
		{
			$mergedFile = $this->mergeFiles($files);
			$this->minifyFile($mergedFile, true);
		}
		else
		{
			// If it's a watcherfilepath file(?) then get the files of that path to minify?
			if (is_file($this->watcherFilePath) && !$this->needToMergeFiles())
			{
				if (in_array($this->watcherFilePath, $map))
				{
					$files = array($this->watcherFilePath);
				}
			}

			$this->minifyFiles($files);
		}

		$this->deleteTempMergeFile();

		return true;
	}

	/**
	 * Checks if we need to merge the files.
	 *
	 * If the 'tofile' property has been set in the Phing XML build file, we
	 * need to merge the files into one file.
	 *
	 * @return  boolean  True if we need to merge the files.
	 */
	private function needToMergeFiles()
	{
		$toFile = $this->getToFile();

		return isset($toFile);
	}

	/**
	 * Merges files into one file.
	 *
	 * All file extensions should be the same in order to minify them into one file.
	 *
	 * @param  array  $files  The files to merge.
	 *
	 * @return  string|false  The path to the merged file or false if 'tofile' is not set
	 * 						  in the XML Phing build file.
	 *
	 * @throws BuildException
	 */
	private function mergeFiles($files)
	{
		// Delete the temp file to make sure its contents are not (recursively) added to the temporary merge file
		$this->deleteTempMergeFile();

		$mergeFilePath = $this->getTempMergeFilePath();

		if (!$mergeFilePath)
		{
			return false;
		}

		// Make sure all files have the same extension (you can't merge .css and .js to one file and minify it...)
		$extensions = $this->getFilesExtensions($files);

		foreach ($extensions as $extension)
		{
			if ($extension != $extensions[0])
			{
				throw new BuildException("All file extensions of a single minify task should be the same "
					. "($extension != $extensions[0]) when the files are going to be merged ('tofile' is "
					. "set in the Phing build file).");
			}
		}

		$data = array();

		foreach ($files as $filename)
		{
			$data[] = file_get_contents($filename);
		}

		file_put_contents($mergeFilePath, $data);

		return $mergeFilePath;
	}


	/**
	 * Gets the path to the temporary merge file.
	 *
	 * This file is used for merging .css or .js files.
	 *
	 * @return  string|false  The path to the temporary merge file or false if
	 * 						  'tofile' is not set in the XML Phing build file.
	 */
	private function getTempMergeFilePath()
	{
		if (!$this->getTofilePath())
		{
			return false;
		}

		return $this->getTofilePath() . '.tempmerge';
	}

	/**
	 * Deletes the temporary merged file.
	 */
	private function deleteTempMergeFile()
	{
		$tempMergeFilePath = $this->getTempMergeFilePath();

		if (is_file($tempMergeFilePath))
		{
			unlink($tempMergeFilePath);
		}
	}

	/**
	 * Minifies file(s) using YUI compressor.
	 *
	 * @param  array  $files
	 *
	 * @throws BuildException
	 */
	private function minifyFiles($files)
	{
		foreach ($files as $file)
		{
			$this->minifyFile($file, false);
		}
	}

	/**
	 * Minifies file(s) using YUI compressor.
	 *
	 * @param  array    $file          The file to minify.
	 * @param  boolean  $isMergedFile  True if it's a merged file.
	 *
	 * @throws BuildException
	 */
	private function minifyFile($file, $isMergedFile)
	{
		if ($isMergedFile)
		{
			$cleanedFile = rtrim($file, '.tempmerge');
		}
		else
		{
			$cleanedFile = $file;
		}

		$extension = $this->getFileExtension($cleanedFile);

		if ($isMergedFile)
		{
			$outputFileArgument = rtrim($file, '.tempmerge');
			$outputFilePath = $outputFileArgument;
		}
		else
		{
			// minify all .css / .js files and save them as .min.css or .min.js
			$outputFileArgument = "." . $extension . "$:.min." . $extension;
			$outputFilePath = str_replace(array('.css', '.js'), array('.min.css', '.min.js'), $file);

			echo "\t\t\t  - $cleanedFile" . PHP_EOL;
		}

		echo "\t\t\t -> $outputFilePath" . PHP_EOL;

		exec("yuicompressor " . escapeshellarg($file)
			. " --type " . $extension
			. " -o '". escapeshellarg($outputFileArgument) . "' "
			. ($this->debug ? ' -v' : '')
			. " --line-break 5000 --charset utf-8");
	}

	/**
	 * Gets the file extensions.
	 *
	 * @param   array    $files        Files to get the extensions from.
	 *
	 * @return  array  The files extensions.
	 *
	 * @throws  BuildException
	 */
	private function getFilesExtensions($files)
	{
		$extensions = array();

		foreach ($files as $file)
		{
			$extensions[] = $this->getFileExtension($file);
		}

		return $extensions;
	}

	/**
	 * Gets the file extensions.
	 *
	 * @param   string  $file  File to get the extensions from.
	 *
	 * @return  array  The file extension.
	 *
	 * @throws  BuildException
	 */
	private function getFileExtension($file)
	{
		$extension = pathinfo($file, PATHINFO_EXTENSION);

		if (!in_array($extension, array('css', 'js')))
		{
			throw new BuildException("Invalid file extension ($extension). Only css and js files are supported.");
		}

		return $extension;
	}
}