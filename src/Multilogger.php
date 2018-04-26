<?php

namespace Webartdesign\Multilogger;

use Carbon\Carbon;
use Exception;
use GrahamCampbell\Flysystem\FlysystemManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;

/**
 * Class Multilogger
 * @package Webartdesign\Multilogger
 */
class Multilogger
{
	/**
	 *
	 */
	/**
	 *
	 */
	/**
	 *
	 */
	/**
	 *
	 */
	/**
	 *
	 */
	/**
	 *
	 */
	const IDENTIFIER = 'id', DATA_NAME = 'logs', CREATED_AT = 'created_at', UPDATED_AT = 'updated_at', FINISHED_AT = 'finished_at', DB_NAME = 'multilogger';

	/**
	 * @var Repository
	 */
	protected $config;

	/**
	 * @var string
	 */
	protected $storage_path;

	/**
	 * @var string
	 */
	protected $file_name;

	/**
	 * @var string
	 */
	protected $extension;

	/**
	 * @var string
	 */
	protected $unf_extension;

	/**
	 * @var string
	 */
	protected $full_path;

	/**
	 * @var FlysystemManager
	 */
	protected $file;

	/**
	 * Multilogger constructor.
	 * @param Repository $config
	 * @param FlysystemManager $file
	 */
	public function __construct(Repository $config, FlysystemManager $file)
	{
		$this->config = $config;

		$this->storage_path = '/multilogger/';

		$this->file_name = 'file';

		$this->extension = 'multilogger';

		$this->unf_extension = 'multilogger_unf';

		$this->full_path = $this->storage_path . $this->file_name . '.' . $this->unf_extension;

		$this->file = $file;

		$this->file->setDefaultConnection('local');
	}

	/**
	 * @param $id
	 * @param array $data
	 * @return $this
	 * @throws Exception
	 */
	public function log($id, $data = [])
	{
		if ($this->checkIfDone($id)) {
			throw new Exception('Log is already finished');
		}
		$this->file_name = $id;

		$this->full_path = $this->storage_path . $this->file_name . '.' . $this->unf_extension;

		if ( !$this->checkIfFileExist($id)) {
			$this->setContents($data);
		} else {
			$this->setContents($data, true);
		}

		return $this;
	}

	/**
	 * @return array
	 */
	private function getDecodedFileContents() : array
	{
		return (array) json_decode($this->file->read($this->full_path));
	}

	/**
	 * @return string
	 */
	private function getFileContents() : string
	{
		return $this->file->read($this->full_path);
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function done() : bool
	{
		$default_path_name = str_replace(
			'.' . $this->unf_extension,
			'.' . $this->extension,
			$this->full_path
		);

		if ($this->setLogFinishedDate()) {
			$this->file->rename($this->full_path, $default_path_name);
			$this->full_path = $this->storage_path . $this->file_name . '.' . $this->extension;

			$this->saveToDb();
		}

		return true;
	}

	/**
	 * Saves the contents of the file to the database
	 */
	public function saveToDb()
	{
		DB::transaction(function ()
		{
			DB::table(self::DB_NAME)->insert(
				[
					'file_name'  => $this->file_name,
					'full_path'  => $this->full_path,
					'data'       => $this->getFileContents(),
					'finished_at' => Carbon::now()->toDateTimeString()
				]
			);
		});
	}

	/**
	 * @return bool
	 */
	public function setLogFinishedDate() : bool
	{
		$file_contents = $this->getDecodedFileContents();

		$file_contents = array_merge($file_contents, [
			self::FINISHED_AT => Carbon::now()->toDateTimeString(),
		]);

		return $this->saveFile($file_contents);
	}

	/**
	 * @param $data
	 * @param bool $update
	 * @param bool $done
	 * @return bool
	 * @throws Exception
	 */
	private function setContents($data, $update = false, $done = false)
	{
		if ($update && !$data && !$done) return false;

		$dataArray = [];
		$now = Carbon::now()->toDateTimeString();
		$created_at = $now;
		$updated_at = $now;

		if ($update) {
			$decoded = $this->getDecodedFileContents();

			if (array_key_exists(self::DATA_NAME, $decoded)) {

				if ( !$done) {
					array_push($decoded[self::DATA_NAME], $data);
				}

				$created_at = $decoded[self::CREATED_AT];
				$dataArray = $decoded[self::DATA_NAME];
			}

		} else {
			if (!empty($data)) {
				array_push($dataArray, $data);
			}
		}

		$contentsArr = [
			self::IDENTIFIER => $this->file_name,
			self::DATA_NAME  => $dataArray,
			self::CREATED_AT => $created_at,
			self::UPDATED_AT => $updated_at,
		];

		return $this->saveFile($contentsArr);
	}

	/**
	 * @param array $contents
	 * @return bool
	 */
	public function saveFile($contents = []): bool
	{
		return $this->file->put($this->full_path, json_encode($contents));
	}

	/**
	 * @param $name
	 * @return bool
	 */
	public function checkIfDone($name)
	{
		if ($this->file->has($this->storage_path . $name . '.' . $this->extension)) {
			return true;
		}

		return false;
	}

	/**
	 * @param $name
	 * @return bool
	 */
	public function checkIfNotDone($name)
	{
		if ($this->file->has($this->storage_path . $name . '.' . $this->unf_extension)) {
			return true;
		}

		return false;
	}

	/**
	 * @param $name
	 * @return bool
	 */
	public function checkIfFileExist($name)
	{
		if ($this->file->has($this->storage_path . $name . '.' . $this->unf_extension) ||
			$this->file->has($this->storage_path . $name . '.' . $this->extension)) {
			return true;
		}

		return false;
	}
}