<?php defined('SYSPATH') OR die('No direct script access.');

abstract class Kohana_Schema_Grammar_Postgres extends Schema_Grammar {

	/**
	 * The keyword identifier wrapper format.
	 *
	 * @var string
	 */
	protected $_wrapper = '"%s"';

	/**
	 * The possible column modifiers.
	 *
	 * @var array
	 */
	protected $_modifiers = array('increment', 'nullable', 'default');

	/**
	 * The columns available as serials.
	 *
	 * @var array
	 */
	protected $_serials = array('big_integer', 'integer');

	/**
	 * Compile the query to determine if a table exists.
	 *
	 * @return string
	 */
	public function compile_table_exists()
	{
		return "SELECT * FROM information_schema.tables WHERE table_name = :table";
	}

	/**
	 * Compile the query to determine the list of columns.
	 *
	 * @param  string $table
	 * @return string
	 */
	public function compile_column_exists($table)
	{
		return "SELECT column_name FROM information_schema.columns WHERE table_name = :table";
	}

	/**
	 * Compile a create table command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_create(Schema_Blueprint $blueprint, Fluent $command)
	{
		$columns = implode(', ', $this->_get_columns($blueprint));

		return 'create table '.$this->wrap_table($blueprint)." ($columns)";
	}

	/**
	 * Compile a create table command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_add(Schema_Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrap_table($blueprint);

		$columns = $this->prefix_array('add column', $this->_get_columns($blueprint));

		return 'alter table '.$table.' '.implode(', ', $columns);
	}

	/**
	 * Compile a primary key command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_primary(Schema_Blueprint $blueprint, Fluent $command)
	{
		$columns = $this->columnize($command->columns);

		return 'alter table '.$this->wrap_table($blueprint)." add primary key ({$columns})";
	}

	/**
	 * Compile a unique key command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_unique(Schema_Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrap_table($blueprint);

		$columns = $this->columnize($command->columns);

		return "ALTER TABLE $table ADD CONSTRAINT {$command->index} UNIQUE ($columns)";
	}

	/**
	 * Compile a plain index key command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_index(Schema_Blueprint $blueprint, Fluent $command)
	{
		$columns = $this->columnize($command->columns);

		return "create index {$command->index} on ".$this->wrap_table($blueprint)." ({$columns})";
	}

	/**
	 * Compile a drop table command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_drop(Schema_Blueprint $blueprint, Fluent $command)
	{
		return 'drop table '.$this->wrap_table($blueprint);
	}

	/**
	 * Compile a drop table (if exists) command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_drop_if_exists(Schema_Blueprint $blueprint, Fluent $command)
	{
		return 'drop table if exists '.$this->wrap_table($blueprint);
	}

	/**
	 * Compile a drop column command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_drop_column(Schema_Blueprint $blueprint, Fluent $command)
	{
		$columns = $this->prefix_array('drop column', $this->wrap_array($command->columns));

		$table = $this->wrap_table($blueprint);

		return 'alter table '.$table.' '.implode(', ', $columns);
	}

	/**
	 * Compile a modify column command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_modify_column(Schema_Blueprint $blueprint, Fluent $command)
	{
		$column = $command->column;
		$sql    = $this->wrap($column).' type '.$this->_get_type($column);
		$table  = $this->wrap_table($blueprint);

		return 'alter table '.$table.' alter column '.$this->_add_modifiers($sql, $blueprint, $column);
	}

	/**
	 * Compile a drop primary key command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_drop_primary(Schema_Blueprint $blueprint, Fluent $command)
	{
		$table = $blueprint->get_table();

		return 'alter table '.$this->wrap_table($blueprint)." drop constraint {$table}_pkey";
	}

	/**
	 * Compile a drop unique key command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_drop_unique(Schema_Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrap_table($blueprint);

		return "ALTER TABLE {$table} DROP constraint {$command->index}";
	}

	/**
	 * Compile a drop index command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_drop_index(Schema_Blueprint $blueprint, Fluent $command)
	{
		return "DROP INDEX {$command->index}";
	}

	/**
	 * Compile a drop foreign key command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_drop_foreign(Schema_Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrap_table($blueprint);

		return "ALTER TABLE {$table} DROP constraint {$command->index}";
	}

	/**
	 * Compile a rename table command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_rename(Schema_Blueprint $blueprint, Fluent $command)
	{
		$from = $this->wrap_table($blueprint);

		return "alter table {$from} rename to ".$this->wrap_table($command->to);
	}

	/**
	 * Create the column definition for a char type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_char(Fluent $column)
	{
		return "char({$column->length})";
	}

	/**
	 * Create the column definition for a string type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_string(Fluent $column)
	{
		return "varchar({$column->length})";
	}

	/**
	 * Create the column definition for a text type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_text(Fluent $column)
	{
		return 'text';
	}

	/**
	 * Create the column definition for a medium text type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_medium_text(Fluent $column)
	{
		return 'text';
	}

	/**
	 * Create the column definition for a long text type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_long_text(Fluent $column)
	{
		return 'text';
	}

	/**
	 * Create the column definition for a json type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_json(Fluent $column)
	{
		return 'json';
	}

	/**
	 * Create the column definition for a integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_integer(Fluent $column)
	{
		return $column->auto_increment ? 'serial' : 'integer';
	}

	/**
	 * Create the column definition for a big integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_big_integer(Fluent $column)
	{
		return $column->auto_increment ? 'bigserial' : 'bigint';
	}

	/**
	 * Create the column definition for a medium integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_medium_integer(Fluent $column)
	{
		return 'integer';
	}

	/**
	 * Create the column definition for a tiny integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_tiny_integer(Fluent $column)
	{
		return 'smallint';
	}

	/**
	 * Create the column definition for a small integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_small_integer(Fluent $column)
	{
		return 'smallint';
	}

	/**
	 * Create the column definition for a float type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_float(Fluent $column)
	{
		return 'real';
	}

	/**
	 * Create the column definition for a double type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_double(Fluent $column)
	{
		return 'double precision';
	}

	/**
	 * Create the column definition for a decimal type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_decimal(Fluent $column)
	{
		return "decimal({$column->total}, {$column->places})";
	}

	/**
	 * Create the column definition for a boolean type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_boolean(Fluent $column)
	{
		return 'boolean';
	}

	/**
	 * Create the column definition for an enum type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_enum(Fluent $column)
	{
		$allowed = array_map(function ($a)
		{
			return "'".$a."'";
		}, $column->allowed);

		return "varchar(255) check (\"{$column->name}\" in (".implode(', ', $allowed)."))";
	}

	/**
	 * Create the column definition for a date type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_date(Fluent $column)
	{
		return 'date';
	}

	/**
	 * Create the column definition for a date-time type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_datetime(Fluent $column)
	{
		return 'timestamp';
	}

	/**
	 * Create the column definition for a time type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_time(Fluent $column)
	{
		return 'time';
	}

	/**
	 * Create the column definition for a timestamp type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_timestamp(Fluent $column)
	{
		return 'timestamp';
	}

	/**
	 * Create the column definition for a binary type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_binary(Fluent $column)
	{
		return 'bytea';
	}

	/**
	 * Get the SQL for a nullable column modifier.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $column
	 * @return string|null
	 */
	protected function modify_nullable(Schema_Blueprint $blueprint, Fluent $column)
	{
		return $column->nullable ? ' null' : ' not null';
	}

	/**
	 * Get the SQL for a default column modifier.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $column
	 * @return string|null
	 */
	protected function modify_default(Schema_Blueprint $blueprint, Fluent $column)
	{
		if (!is_null($column->default))
		{
			return " default ".$this->_get_default_value($column->default);
		}
	}

	/**
	 * Get the SQL for an auto-increment column modifier.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $column
	 * @return string|null
	 */
	protected function modify_increment(Schema_Blueprint $blueprint, Fluent $column)
	{
		if (in_array($column->type, $this->_serials) && $column->auto_increment)
		{
			return ' primary key';
		}
	}

}