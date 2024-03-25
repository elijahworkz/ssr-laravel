<?php

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Renderer
{
	public $context;

	public $env;

	public $tempPath;

	public function __construct(public string $scriptPath, public string $renderer, ?string $tempPath = null)
	{
		$this->tempPath = $tempPath ?? rtrim(sys_get_temp_dir());
	}

	public static function entry(...$args)
	{
		return new static(...$args);
	}

	public function context(array $context)
	{
		foreach ($context as $key => $value) {
			$this->context[$key] = $value;
		}

		return $this;
	}

	public function env($env, $value = null)
	{
		if (!is_array($env)) {
			$env = [$env => $value];
		}

		foreach ($env as $key => $value) {
			$this->env[$key] = $value;
		}

		return $this;
	}

	public function render()
	{
		// We will collect scripts here and run them trough reder engine
		$server_script = implode(';', [
			$this->environmentScript(),
			$this->applicationScript(),
		]);

		return $this->run($server_script);
	}

	public function environmentScript()
	{
		$context = empty($this->context) ? '{}' : json_encode($this->context);
		$envAssignments = array_map(function ($value, $key) {
			return "process.env.{$key} = " . json_encode($value);
		}, $this->env, array_keys($this->env));

		return implode(';', [
			'(function () { if (this.process != null) { return; } this.process = { env: {}, argv: [] }; }).call(null)',
			implode(';', $envAssignments),
			"var context = {$context}",
		]);
	}

	public function applicationScript()
	{
		return file_get_contents($this->scriptPath);
	}

	protected function run($script)
	{
		$tempFilePath = $this->createTempFilePath();
		file_put_contents($tempFilePath, $script);
		$command = "{$this->renderer} {$tempFilePath}";

		$process = Process::fromShellCommandline($command);

		try {
			return substr($process->mustRun()->getOutput(), 0, -1);
		} catch (ProcessFailedException $exception) {
			echo $exception->getMessage();
		} finally {
			unlink($tempFilePath);
		}
	}

	private function createTempFilePath()
	{
		return implode(DIRECTORY_SEPARATOR, [$this->tempPath, md5(intval(microtime(true) * 1000) . random_bytes(5)) . '.js']);
	}
}
