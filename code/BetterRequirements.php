<?php

/**
 * @author zauberfisch
 */
class BetterRequirements_Backend extends Requirements_Backend implements Flushable {
	private static $compile_in_live = false;
	private static $compile_in_dev = true;
	private static $compile_in_test = true;
	private static $compile_in_flush = true;
	private static $cache_key_method = 'filemtime';
	protected $compiled = false;
	protected static $flush = false;
	protected $log = [];
	protected $files = [];
	protected $filesCache;

	protected function cacheKey($fileName) {
		return call_user_func(Config::inst()->get(__CLASS__, 'cache_key_method'), $fileName);
	}

	protected function filesCache() {
		if (!is_array($this->filesCache)) {
			$fileName = Director::getAbsFile($this->getCombinedFilesFolder() . '/_better_requirements_files_cache.json');
			if (file_exists($fileName)) {
				$this->filesCache = json_decode(
					file_get_contents($fileName),
					true
				);
			} else {
				$this->filesCache = [];
			}
		}
		return $this->filesCache;
	}

	protected function filesCacheSave() {
		file_put_contents(Director::getAbsFile($this->getCombinedFilesFolder() . '/_better_requirements_files_cache.json'), json_encode($this->filesCache()));
	}

	protected function filesCacheIsChanged($fileName) {
		$fileName = Director::getAbsFile($fileName);
		$info = $this->filesCache();
		$cacheKey = file_exists($fileName) ? $this->cacheKey($fileName) : false;
		if (!isset($info[$fileName]) || !$cacheKey || $info[$fileName] != $cacheKey) {
			return true;
		}
		return false;
	}

	protected function filesCacheAdd($fileName) {
		$fileName = Director::getAbsFile($fileName);
		$info = $this->filesCache();
		$info[$fileName] = $this->cacheKey($fileName);
		$this->filesCache = $info;
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
		$file = $this->collectFile($file);
		parent::css($file, $media);
	}

	public function combine_files($combinedFileName, $files, $media = null) {
		foreach ($files as $i => $fileName) {
			$files[$i] = $this->collectFile($fileName);
		}
		return parent::combine_files($combinedFileName, $files, $media);
	}

	protected function collectFile($fileName) {
		$_fileName = explode('.', $fileName);
		$ext = array_pop($_fileName);
		if (in_array($ext, ['scss', 'sass', 'less'])) {
			$this->files[$ext][$fileName] = str_ireplace("/$ext/", '/css/', $fileName) . '.css';
			return $this->files[$ext][$fileName];
		}
		return $fileName;
	}

	protected function compile() {
		if (
			!$this->compiled && (
				(static::$flush && Config::inst()->get(__CLASS__, 'compile_in_flush')) ||
				(Director::isLive() && Config::inst()->get(__CLASS__, 'compile_in_live')) ||
				(Director::isTest() && Config::inst()->get(__CLASS__, 'compile_in_test')) ||
				(Director::isDev() && Config::inst()->get(__CLASS__, 'compile_in_dev'))
			)
		) {
			// only allow compile to run once
			$this->compiled = true;
			foreach ($this->files as $category => $files) {
				foreach ($files as $source => $target) {
					$this->{"compile{$category}File"}($source, $target);
				}
			}
			if ($this->log) {
				$js = [];
				foreach ($this->log as $line) {
					$type = 'log';
					if (is_array($line)) {
						$type = $line[1];
						$line = $line[0];
					}
					$js[] = sprintf("console.%s(%s);", $type, json_encode($line));
				}
				Requirements::customScript(implode(PHP_EOL, $js));
			}
			$this->log = [];
			$this->filesCacheSave();
		}
	}

	protected function compileLESSFile($lessFile, $cssFile) {
		if (static::$flush || $this->filesCacheIsChanged($lessFile)) {
			$bin = 'lessc';
			if (defined('SS_LESSC_PATH')) {
				$bin = SS_LESSC_PATH;
			}
			$this->compileWithCommand($bin, $lessFile, $cssFile);
		}
	}

	protected function compileSCSSFile($scssFile, $cssFile) {
		$this->compileSASSFile($scssFile, $cssFile);
	}

	protected function compileSASSFile($sassFile, $cssFile) {
		if (static::$flush || $this->filesCacheIsChanged($sassFile)) {
			$bin = 'sassc';
			if (defined('SS_SASSC_PATH')) {
				$bin = SS_SASSC_PATH;
			}
			$this->compileWithCommand($bin, $sassFile, $cssFile);
		}
	}

	protected function compileWithCommand($bin, $source, $target) {
		$sourceFilePath = trim(Director::getAbsFile($source));
		$targetFilePath = trim(Director::getAbsFile($target));
		$command = $bin . " " . escapeshellarg($sourceFilePath);
		$process = new \Symfony\Component\Process\Process($command);
		$process->run();
		if ($process->isSuccessful()) {
			$css = $process->getOutput();
			if (!file_exists(dirname($targetFilePath))) {
				mkdir(dirname($targetFilePath), null, true);
			}
			file_put_contents($targetFilePath, $css);
			$this->filesCacheAdd($source);
			$this->log[] = ["compiled $source to $target", 'info'];
		} else {
			$message = $process->getErrorOutput();
			if ($process->getExitCode() != 1 || !$message) {
				$message = "\"$command\": non-zero exit code {$process->getExitCode()} '{$process->getExitCodeText()}'. (Output: '$message')";
			}
			$this->log[] = ["failed to compile $source with $bin: $message", 'error'];
			SS_Log::log(new Exception($message), SS_Log::ERR);
		}
	}
}
