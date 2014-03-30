<?php defined('SYSPATH') OR die('No direct access allowed.');

class Kohana_Schema_Builder {

	/**
	 * The table instance the blueprint describes.
	 *
	 * @var string
	 */
	protected $_table;

	/**
	 * Database instance used for querying.
	 *
	 * @var Database
	 */
	protected $_db;

	/**
	 * The schema grammar instance.
	 *
	 * @var Schema_Grammar
	 */
	protected $_grammar;

	/**
	 * Connection details
	 *
	 * @var array
	 */
	protected $_connection;

	public function __construct($table, Closure $callback = NULL, $db = NULL)
	{
		$this->_table = $table;

		$db = $db ?: Database::$default;
		$this->_db = ($db instanceof Database) ? $db : Database::instance($db);

		$this->_connection = Kohana::$config->load('database')->$db;
		$this->_connection['db'] = $this->_db;

		$this->_grammar = $this->_get_schema_grammar();
	}

	/**
	 * Modify a table on the schema.
	 *
	 * @param  string   $table
	 * @param  Closure  $callback
	 * @return Schema_Blueprint
	 */
	public function table($table, Closure $callback)
	{
		return $this->build(new Schema_Blueprint($table, $callback));
	}

	/**
	 * Create a new table on the schema.
	 *
	 * @param  string   $table
	 * @param  Closure  $callback
	 * @return Schema_Blueprint
	 */
	public function create($table, Closure $callback)
	{
		$blueprint = new Schema_Blueprint($table);

		$blueprint->create();

		$callback($blueprint);

		return $this->build($blueprint);
	}

	/**
	 * Drop a table from the schema.
	 *
	 * @param  string  $table
	 * @return Schema_Blueprint
	 */
	public function drop($table)
	{
		$blueprint = new Schema_Blueprint($table);

		$blueprint->drop();

		return $this->build($blueprint);
	}

	/**
	 * Rename a table on the schema.
	 *
	 * @param  string  $from
	 * @param  string  $to
	 * @return Schema_Blueprint
	 */
	public function rename($from, $to)
	{
		$blueprint = new Schema_Blueprint($from);

		$blueprint->rename($to);

		return $this->build($blueprint);
	}


	/**
	 * Execute the blueprint to build / modify the table.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @return void
	 */
	protected function build(Schema_Blueprint $blueprint)
	{
		$blueprint->build($this->_connection, $this->_grammar);
	}

	protected function _get_schema_grammar()
	{
		$connection = $this->_connection['connection'];

		$grammar = '';
		if ($this->_connection['type'] === 'PDO')
		{
			$dsn = explode(';', $connection['dsn']);
			list($type, $host) = explode(':', $dsn[0]);
			switch (strtolower($type))
			{
				case 'mysql':
					$grammar = 'MySQL';
					break;
				case 'pgsql':
					$grammar = 'Postgres';
					break;
				case 'sqlsrv':
					$grammar = 'SQLServer';
					break;
				case 'sqlite':
					$grammar = 'SQLite';
					break;
			}
		}
		else
		{
			$grammar = $this->_connection['type'];
			if ($grammar === 'MySQLi')
			{
				$grammar = 'MySQL';
			}
			elseif ($grammar === 'PostgreSQL')
			{
				$grammar = 'Postgres';
			}
		}

		$driver = "Schema_Grammar_$grammar";
		if ( ! class_exists($driver))
		{
			throw new Kohana_Exception('Unsupported Schema Grammar :grammar.', [':grammar' => $grammar]);
		}

		return new $driver;
	}

}