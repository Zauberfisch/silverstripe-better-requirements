<?php

/**
 * @author zauberfisch
 */
class BetterRequirements_Backend extends Requirements_Backend implements Flushable {
	private static $compile_in_live = false;
	private static $compile_in_dev = true;
	protected $compiled = false;
	protected static $flush = false;
	protected $sassFiles = [];
	protected $log = [];
	protected $sassFilesCache;

	protected function sassFilesCache() {
		if (!is_array($this->sassFilesCache)) {
			$fileName = Director::getAbsFile($this->getCombinedFilesFolder() . '/_sass_files_cache.json');
			if (file_exists($fileName)) {
				$this->sassFilesCache = json_decode(
					file_get_contents($fileName),
					true
				);
			} else {
				$this->sassFilesCache = [];
			}
		}
		return $this->sassFilesCache;
	}

	protected function sassFilesCacheSave() {
		file_put_contents(Director::getAbsFile($this->getCombinedFilesFolder() . '/_sass_files_cache.json'), json_encode($this->sassFilesCache()));
	}

	protected function sassFilesCacheIsChanged($fileName) {
		$fileName = Director::getAbsFile($fileName);
		$info = $this->sassFilesCache();
		$time = file_exists($fileName) ? filemtime($fileName) : false;
		if (!isset($info[$fileName]) || !$time || $info[$fileName] != $time) {
			return true;
		}
		return false;
	}

	protected function sassFilesCacheAdd($fileName) {
		$fileName = Director::getAbsFile($fileName);
		$info = $this->sassFilesCache();
		$info[$fileName] = filemtime($fileName);
		$this->sassFilesCache = $info;
	}

	/**
	 * This function is triggered early in the request if the "flush" query
	 * parameter has been set. Each class that implements Flushable implements
	 * this function which looks after it's own specific flushing functionality.
	 *
	 * @see FlushRequestFilter
	 */
	public static function flush() {
		static::$flush = true;
	}

	public function includeInHTML($templateFile, $content) {
		$this->compile();
		return parent::includeInHTML($templateFile, $content);
	}

	public function include_in_response(SS_HTTPResponse $response) {
		$this->compile();
		return parent::include_in_response($response);
	}


	public function css($file, $media = null) {
		$file = $this->handleSassFile($file);
		parent::css($file, $media);
	}

	public function combine_files($combinedFileName, $files, $media = null) {
		foreach ($files as $i => $fileName) {
			$files[$i] = $this->handleSassFile($fileName);
		}
		return parent::combine_files($combinedFileName, $files, $media);
	}

	protected function handleSassFile($fileName) {
		$_fileName = explode('.', $fileName);
		$ext = array_pop($_fileName);
		$fileNameNoExt = implode('.', $_fileName);
		if (in_array(strtolower($ext), ['scss', 'sass'])) {
			$ext = 'css';
			$cssFileName = "$fileNameNoExt.$ext";
			$cssFileName = str_ireplace(['/sass/','/scss/'], '/css/', $cssFileName);
			$this->sassFiles[$fileName] = $cssFileName;
			return $cssFileName;
		}
		return $fileName;
	}

	protected function compile() {
		if (
			!$this->compiled && (
				static::$flush ||
				Config::inst()->get(__CLASS__, 'compile_in_live') ||
				(Director::isDev() && Config::inst()->get(__CLASS__, 'compile_in_dev'))
			)
		) {
			// only allow compile to run once
			$this->compiled = true;
			foreach ($this->sassFiles as $sassFileName => $cssFileName) {
				$this->compileFile($sassFileName, $cssFileName);
			}
			$js = [];
			foreach ($this->log as $line) {
				$type = 'log';
				if (is_array($line)) {
					$type = $line[1];
					$line = $line[0];
				}
				$line = str_replace("'", "\\'", $line);
				$js[] = sprintf("console.%s('%s');", $type, $line);
			}
			Requirements::customScript(implode(PHP_EOL, $js));
			$this->log = [];
			$this->sassFilesCacheSave();
		}
	}

	protected function compileFile($sassFile, $cssFile) {
		$sassc = 'sassc';
		if (defined('SS_SASSC_PATH')) {
			$sassc = SS_SASSC_PATH;
		}
		$cssFilePath = trim(Director::getAbsFile($cssFile));
		$sassFilePath = trim(Director::getAbsFile($sassFile));
		if (static::$flush || $this->sassFilesCacheIsChanged($sassFile)) {
			$command = $sassc . " " . escapeshellarg($sassFilePath);

			$this->log[] = ["compiling $sassFile", 'info'];

			$process = new \Symfony\Component\Process\Process($command);
			$process->run();

			if ($process->isSuccessful()) {
				$css = $process->getOutput();
				if (!file_exists(dirname($cssFilePath))) {
					mkdir(dirname($cssFilePath), null, true);
				}
				file_put_contents($cssFilePath, $css);
				$this->sassFilesCacheAdd($sassFile);
			} else {
				throw new Exception("failed to compile stylesheets with command \"$command\": non-zero exit code {$process->getExitCode()} '{$process->getExitCodeText()}'. (Output: '{$process->getErrorOutput()}')");
			}
		}
	}
}
