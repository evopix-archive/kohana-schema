<?php defined('SYSPATH') OR die('No direct access allowed.');

class Kohana_Schema_Blueprint {

	/**
	 * The storage engine that should be used for the table.
	 *
	 * @var string
	 */
	public $engine;

	/**
	 * The table the blueprint describes.
	 *
	 * @var string
	 */
	protected $_table;

	/**
	 * The columns that should be added to the table.
	 *
	 * @var array
	 */
	protected $_columns = array();

	/**
	 * The commands that should be run for the table.
	 *
	 * @var array
	 */
	protected $_commands = array();

	/**
	 * Create a new schema blueprint.
	 *
	 * @param  string  $table
	 * @param  Closure $callback
	 */
	public function __construct($table, Closure $callback = NULL)
	{
		$this->_table = $table;

		if (!is_null($callback)) $callback($this);
	}

	/**
	 * Execute the blueprint against the database.
	 *
	 * @param  array          $connection
	 * @param  Schema_Grammar $grammar
	 */
	public function build(array $connection, Schema_Grammar $grammar)
	{
		foreach ($this->to_sql($connection, $grammar) as $statement)
		{
			echo Debug::vars($statement);
			$connection['db']->query(NULL, $statement);
		}
	}

	/**
	 * Get the raw SQL statements for the blueprint.
	 *
	 * @param  array          $connection
	 * @param  Schema_Grammar $grammar
	 * @return array
	 */
	public function to_sql(array $connection, Schema_Grammar $grammar)
	{
		$this->_add_implied_commands();

		$statements = array();

		// Each type of command has a corresponding compiler function on the schema
		// grammar which is used to build the necessary SQL statements to build
		// the blueprint element, so we'll just call that compilers function.
		foreach ($this->_commands as $command)
		{
			$method = 'compile_'.$command->name;

			if (method_exists($grammar, $method))
			{
				if (!is_null($sql = $grammar->$method($this, $command, $connection)))
				{
					$statements = array_merge($statements, (array) $sql);
				}
			}
		}

		return $statements;
	}

	/**
	 * Indicate that the table needs to be created.
	 *
	 * @return Fluent
	 */
	public function create()
	{
		return $this->_add_command('create');
	}

	/**
	 * Indicate that the table should be dropped.
	 *
	 * @return Fluent
	 */
	public function drop()
	{
		return $this->_add_command('drop');
	}

	/**
	 * Indicate that the table should be dropped if it exists.
	 *
	 * @return Fluent
	 */
	public function drop_if_exists()
	{
		return $this->_add_command('drop_if_exists');
	}

	/**
	 * Indicate that the given columns should be dropped.
	 *
	 * @param  string|array $columns
	 * @return Fluent
	 */
	public function drop_column($columns)
	{
		$columns = is_array($columns) ? $columns : (array) func_get_args();

		return $this->_add_command('drop_column', compact('columns'));
	}

	/**
	 * Indicate that the given columns should be renamed.
	 *
	 * @param  string $from
	 * @param  string $to
	 * @return Fluent
	 */
	public function rename_column($from, $to)
	{
		return $this->_add_command('rename_column', compact('from', 'to'));
	}

	/**
	 * Indicate that the given column should be modified.
	 *
	 * @param  string $name
	 * @param  string $type
	 * @param  array  $parameters
	 * @return Fluent
	 */
	public function modify_column($name, $type, array $parameters = array())
	{
		$attributes = array_merge(compact('type', 'name'), $parameters);

		$column = new Fluent($attributes);

		return $this->_add_command('modify_column', compact('column'));
	}

	/**
	 * Indicate that the given primary key should be dropped.
	 *
	 * @param  string|array $index
	 * @return Fluent
	 */
	public function drop_primary($index = NULL)
	{
		return $this->_drop_index_command('drop_primary', 'primary', $index);
	}

	/**
	 * Indicate that the given unique key should be dropped.
	 *
	 * @param  string|array $index
	 * @return Fluent
	 */
	public function drop_unique($index)
	{
		return $this->_drop_index_command('drop_unique', 'unique', $index);
	}

	/**
	 * Indicate that the given index should be dropped.
	 *
	 * @param  string|array $index
	 * @return Fluent
	 */
	public function drop_index($index)
	{
		return $this->_drop_index_command('drop_index', 'index', $index);
	}

	/**
	 * Indicate that the given foreign key should be dropped.
	 *
	 * @param  string $index
	 * @return Fluent
	 */
	public function drop_foreign($index)
	{
		return $this->_drop_index_command('drop_foreign', 'foreign', $index);
	}

	/**
	 * Indicate that the timestamp columns should be dropped.
	 *
	 * @return void
	 */
	public function drop_timestamps()
	{
		$this->drop_column('created_at', 'updated_at');
	}

	/**
	 * Indicate that the soft delete column should be dropped.
	 *
	 * @return void
	 */
	public function drop_soft_deletes()
	{
		$this->drop_column('deleted_at');
	}

	/**
	 * Rename the table to a given name.
	 *
	 * @param  string $to
	 * @return Fluent
	 */
	public function rename($to)
	{
		return $this->_add_command('rename', compact('to'));
	}

	/**
	 * Specify the primary key(s) for the table.
	 *
	 * @param  string|array $columns
	 * @param  string       $name
	 * @return Fluent
	 */
	public function primary($columns, $name = NULL)
	{
		return $this->_index_command('primary', $columns, $name);
	}

	/**
	 * Specify a unique index for the table.
	 *
	 * @param  string|array $columns
	 * @param  string       $name
	 * @return Fluent
	 */
	public function unique($columns, $name = NULL)
	{
		return $this->_index_command('unique', $columns, $name);
	}

	/**
	 * Specify an index for the table.
	 *
	 * @param  string|array $columns
	 * @param  string       $name
	 * @return Fluent
	 */
	public function index($columns, $name = NULL)
	{
		return $this->_index_command('index', $columns, $name);
	}

	/**
	 * Specify a foreign key for the table.
	 *
	 * @param  string|array $columns
	 * @param  string       $name
	 * @return Fluent
	 */
	public function foreign($columns, $name = NULL)
	{
		return $this->_index_command('foreign', $columns, $name);
	}

	/**
	 * Create a new auto-incrementing integer column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function increments($column)
	{
		return $this->unsigned_integer($column, TRUE);
	}

	/**
	 * Create a new auto-incrementing big integer column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function big_increments($column)
	{
		return $this->unsigned_big_integer($column, TRUE);
	}

	/**
	 * Create a new char column on the table.
	 *
	 * @param  string $column
	 * @param  int    $length
	 * @return Fluent
	 */
	public function char($column, $length = 255)
	{
		return $this->_add_column('char', $column, compact('length'));
	}

	/**
	 * Create a new string column on the table.
	 *
	 * @param  string $column
	 * @param  int    $length
	 * @return Fluent
	 */
	public function string($column, $length = 255)
	{
		return $this->_add_column('string', $column, compact('length'));
	}

	/**
	 * Create a new text column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function text($column)
	{
		return $this->_add_column('text', $column);
	}

	/**
	 * Create a new medium text column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function medium_text($column)
	{
		return $this->_add_column('medium_text', $column);
	}

	/**
	 * Create a new long text column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function long_text($column)
	{
		return $this->_add_column('long_text', $column);
	}

	/**
	 * Create a new json column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function json($column)
	{
		return $this->_add_column('json', $column);
	}

	/**
	 * Create a new integer column on the table.
	 *
	 * @param  string $column
	 * @param  bool   $auto_increment
	 * @param  bool   $unsigned
	 * @return Fluent
	 */
	public function integer($column, $auto_increment = FALSE, $unsigned = FALSE)
	{
		return $this->_add_column('integer', $column, compact('auto_increment', 'unsigned'));
	}

	/**
	 * Create a new big integer column on the table.
	 *
	 * @param  string $column
	 * @param  bool   $auto_increment
	 * @param  bool   $unsigned
	 * @return Fluent
	 */
	public function big_integer($column, $auto_increment = FALSE, $unsigned = FALSE)
	{
		return $this->_add_column('big_integer', $column, compact('auto_increment', 'unsigned'));
	}

	/**
	 * Create a new medium integer column on the table.
	 *
	 * @param  string $column
	 * @param  bool   $auto_increment
	 * @param  bool   $unsigned
	 * @return Fluent
	 */
	public function medium_integer($column, $auto_increment = FALSE, $unsigned = FALSE)
	{
		return $this->_add_column('medium_integer', $column, compact('auto_increment', 'unsigned'));
	}

	/**
	 * Create a new tiny integer column on the table.
	 *
	 * @param  string $column
	 * @param  bool   $auto_increment
	 * @param  bool   $unsigned
	 * @return Fluent
	 */
	public function tiny_integer($column, $auto_increment = FALSE, $unsigned = FALSE)
	{
		return $this->_add_column('tiny_integer', $column, compact('auto_increment', 'unsigned'));
	}

	/**
	 * Create a new small integer column on the table.
	 *
	 * @param  string $column
	 * @param  bool   $auto_increment
	 * @param  bool   $unsigned
	 * @return Fluent
	 */
	public function small_integer($column, $auto_increment = FALSE, $unsigned = FALSE)
	{
		return $this->_add_column('small_integer', $column, compact('auto_increment', 'unsigned'));
	}

	/**
	 * Create a new unsigned integer column on the table.
	 *
	 * @param  string $column
	 * @param  bool   $auto_increment
	 * @return Fluent
	 */
	public function unsigned_integer($column, $auto_increment = FALSE)
	{
		return $this->integer($column, $auto_increment, TRUE);
	}

	/**
	 * Create a new unsigned big integer column on the table.
	 *
	 * @param  string $column
	 * @param  bool   $auto_increment
	 * @return Fluent
	 */
	public function unsigned_big_integer($column, $auto_increment = FALSE)
	{
		return $this->big_integer($column, $auto_increment, TRUE);
	}

	/**
	 * Create a new float column on the table.
	 *
	 * @param  string $column
	 * @param  int    $total
	 * @param  int    $places
	 * @return Fluent
	 */
	public function float($column, $total = 8, $places = 2)
	{
		return $this->_add_column('float', $column, compact('total', 'places'));
	}

	/**
	 * Create a new double column on the table.
	 *
	 * @param  string   $column
	 * @param  int|null $total
	 * @param  int|null $places
	 * @return Fluent
	 *
	 */
	public function double($column, $total = NULL, $places = NULL)
	{
		return $this->_add_column('double', $column, compact('total', 'places'));
	}

	/**
	 * Create a new decimal column on the table.
	 *
	 * @param  string $column
	 * @param  int    $total
	 * @param  int    $places
	 * @return Fluent
	 */
	public function decimal($column, $total = 8, $places = 2)
	{
		return $this->_add_column('decimal', $column, compact('total', 'places'));
	}

	/**
	 * Create a new boolean column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function boolean($column)
	{
		return $this->_add_column('boolean', $column);
	}

	/**
	 * Create a new enum column on the table.
	 *
	 * @param  string $column
	 * @param  array  $allowed
	 * @return Fluent
	 */
	public function enum($column, array $allowed)
	{
		return $this->_add_column('enum', $column, compact('allowed'));
	}

	/**
	 * Create a new date column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function date($column)
	{
		return $this->_add_column('date', $column);
	}

	/**
	 * Create a new date-time column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function datetime($column)
	{
		return $this->_add_column('datetime', $column);
	}

	/**
	 * Create a new time column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function time($column)
	{
		return $this->_add_column('time', $column);
	}

	/**
	 * Create a new timestamp column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function timestamp($column)
	{
		return $this->_add_column('timestamp', $column);
	}

	/**
	 * Add nullable creation and update timestamps to the table.
	 *
	 * @return void
	 */
	public function nullable_timestamps()
	{
		$this->timestamp('created_at')->nullable();

		$this->timestamp('updated_at')->nullable();
	}

	/**
	 * Add creation and update timestamps to the table.
	 *
	 * @return void
	 */
	public function timestamps()
	{
		$this->timestamp('created_at');

		$this->timestamp('updated_at');
	}

	/**
	 * Add a "deleted at" timestamp for the table.
	 *
	 * @return void
	 */
	public function soft_deletes()
	{
		$this->timestamp('deleted_at')->nullable();
	}

	/**
	 * Create a new binary column on the table.
	 *
	 * @param  string $column
	 * @return Fluent
	 */
	public function binary($column)
	{
		return $this->_add_column('binary', $column);
	}

	/**
	 * Add the proper columns for a polymorphic table.
	 *
	 * @param  string $name
	 * @return void
	 */
	public function morphs($name)
	{
		$this->unsigned_integer("{$name}_id");

		$this->string("{$name}_type");
	}

	/**
	 * Create a new drop index command on the blueprint.
	 *
	 * @param  string       $command
	 * @param  string       $type
	 * @param  string|array $index
	 * @return Fluent
	 */
	protected function _drop_index_command($command, $type, $index)
	{
		$columns = array();

		// If the given "index" is actually an array of columns, the developer means
		// to drop an index merely by specifying the columns involved without the
		// conventional name, so we will built the index name from the columns.
		if (is_array($index))
		{
			$columns = $index;

			$index = $this->_create_index_name($type, $columns);
		}

		return $this->_index_command($command, $columns, $index);
	}

	/**
	 * Add a new index command to the blueprint.
	 *
	 * @param  string       $type
	 * @param  string|array $columns
	 * @param  string       $index
	 * @return Fluent
	 */
	protected function _index_command($type, $columns, $index)
	{
		$columns = (array) $columns;

		// If no name was specified for this index, we will create one using a basic
		// convention of the table name, followed by the columns, followed by an
		// index type, such as primary or index, which makes the index unique.
		if (is_null($index))
		{
			$index = $this->_create_index_name($type, $columns);
		}

		return $this->_add_command($type, compact('index', 'columns'));
	}

	/**
	 * Create a default index name for the table.
	 *
	 * @param  string $type
	 * @param  array  $columns
	 * @return string
	 */
	protected function _create_index_name($type, array $columns)
	{
		$index = strtolower($this->_table.'_'.implode('_', $columns).'_'.$type);

		return str_replace(array('-', '.'), '_', $index);
	}

	/**
	 * Add a new column to the blueprint.
	 *
	 * @param  string $type
	 * @param  string $name
	 * @param  array  $parameters
	 * @return Fluent
	 */
	protected function _add_column($type, $name, array $parameters = array())
	{
		$attributes = array_merge(compact('type', 'name'), $parameters);

		$this->_columns[] = $column = new Fluent($attributes);

		return $column;
	}

	/**
	 * Remove a column from the schema blueprint.
	 *
	 * @param  string $name
	 * @return Schema_Blueprint
	 */
	public function remove_column($name)
	{
		$this->_columns = array_values(array_filter($this->_columns, function ($c) use ($name)
		{
			return $c['attributes']['name'] != $name;
		}));

		return $this;
	}

	/**
	 * Add a new command to the blueprint.
	 *
	 * @param  string $name
	 * @param  array  $parameters
	 * @return Fluent
	 */
	protected function _add_command($name, array $parameters = array())
	{
		$this->_commands[] = $command = $this->_create_command($name, $parameters);

		return $command;
	}

	/**
	 * Create a new Fluent command.
	 *
	 * @param  string $name
	 * @param  array  $parameters
	 * @return Fluent
	 */
	protected function _create_command($name, array $parameters = array())
	{
		return new Fluent(array_merge(compact('name'), $parameters));
	}

	/**
	 * Get the table the blueprint describes.
	 *
	 * @return string
	 */
	public function get_table()
	{
		return $this->_table;
	}

	/**
	 * Get the columns that should be added.
	 *
	 * @return array
	 */
	public function get_columns()
	{
		return $this->_columns;
	}

	/**
	 * Get the commands on the blueprint.
	 *
	 * @return array
	 */
	public function get_commands()
	{
		return $this->_commands;
	}

	/**
	 * Add the commands that are implied by the blueprint.
	 *
	 * @return void
	 */
	protected function _add_implied_commands()
	{
		if (count($this->_columns) > 0 && !$this->_creating())
		{
			array_unshift($this->_commands, $this->_create_command('add'));
		}

		$this->_add_fluent_indexes();
	}

	/**
	 * Add the index commands fluently specified on columns.
	 *
	 * @return void
	 */
	protected function _add_fluent_indexes()
	{
		foreach ($this->_columns as $column)
		{
			foreach (array('primary', 'unique', 'index') as $index)
			{
				// If the index has been specified on the given column, but is simply
				// equal to "true" (boolean), no name has been specified for this
				// index, so we will simply call the index methods without one.
				if ($column->$index === TRUE)
				{
					$this->$index($column->name);

					continue 2;
				}

				// If the index has been specified on the column and it is something
				// other than boolean true, we will assume a name was provided on
				// the index specification, and pass in the name to the method.
				elseif (isset($column->$index))
				{
					$this->$index($column->name, $column->$index);

					continue 2;
				}
			}
		}
	}

	/**
	 * Determine if the blueprint has a create command.
	 *
	 * @return bool
	 */
	protected function _creating()
	{
		foreach ($this->_commands as $command)
		{
			if ($command->name == 'create') return TRUE;
		}

		return FALSE;
	}

}