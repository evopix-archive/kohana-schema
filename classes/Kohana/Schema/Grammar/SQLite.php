<?php defined('SYSPATH') OR die('No direct script access.');

abstract class Kohana_Schema_Grammar_SQLite extends Schema_Grammar {

	/**
	 * The keyword identifier wrapper format.
	 *
	 * @var string
	 */
	protected $wrapper = '"%s"';

	/**
	 * The possible column modifiers.
	 *
	 * @var array
	 */
	protected $modifiers = array('nullable', 'default', 'increment');

	/**
	 * The columns available as serials.
	 *
	 * @var array
	 */
	protected $serials = array('big_integer', 'integer');

	/**
	 * Compile the query to determine if a table exists.
	 *
	 * @return string
	 */
	public function compile_table_exists()
	{
		return "SELECT * FROM sqlite_master WHERE type = 'table' AND name = :table";
	}

	/**
	 * Compile the query to determine the list of columns.
	 *
	 * @param  string $table
	 * @return string
	 */
	public function compile_column_exists($table)
	{
		return 'pragma table_info('.str_replace('.', '__', $table).')';
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

		$sql = 'create table '.$this->wrap_table($blueprint)." ($columns";

		// SQLite forces primary keys to be added when the table is initially created
		// so we will need to check for a primary key commands and add the columns
		// to the table's declaration here so they can be created on the tables.
		$sql .= (string) $this->add_foreign_keys($blueprint);

		$sql .= (string) $this->add_primary_keys($blueprint);

		return $sql .= ')';
	}

	/**
	 * Get the foreign key syntax for a table creation statement.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @return string|null
	 */
	protected function add_foreign_keys(Schema_Blueprint $blueprint)
	{
		$sql = '';

		$foreigns = $this->_get_commands_by_name($blueprint, 'foreign');

		// Once we have all the foreign key commands for the table creation statement
		// we'll loop through each of them and add them to the create table SQL we
		// are building, since SQLite needs foreign keys on the tables creation.
		foreach ($foreigns as $foreign)
		{
			$sql .= $this->get_foreign_key($foreign);

			if (!is_null($foreign->onDelete))
			{
				$sql .= " on delete {$foreign->on_delete}";
			}

			if (!is_null($foreign->onUpdate))
			{
				$sql .= " on update {$foreign->on_update}";
			}
		}

		return $sql;
	}

	/**
	 * Get the SQL for the foreign key.
	 *
	 * @param  Fluent $foreign
	 * @return string
	 */
	protected function get_foreign_key($foreign)
	{
		$on = $this->wrap_table($foreign->on);

		// We need to columnize the columns that the foreign key is being defined for
		// so that it is a properly formatted list. Once we have done this, we can
		// return the foreign key SQL declaration to the calling method for use.
		$columns = $this->columnize($foreign->columns);

		$onColumns = $this->columnize((array) $foreign->references);

		return ", foreign key($columns) references $on($onColumns)";
	}

	/**
	 * Get the primary key syntax for a table creation statement.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @return string|null
	 */
	protected function add_primary_keys(Schema_Blueprint $blueprint)
	{
		$primary = $this->_get_command_by_name($blueprint, 'primary');

		if (!is_null($primary))
		{
			$columns = $this->columnize($primary->columns);

			return ", primary key ({$columns})";
		}
	}

	/**
	 * Compile alter table commands for adding columns
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return array
	 */
	public function compile_add(Schema_Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrap_table($blueprint);

		$columns = $this->prefix_array('add column', $this->_get_columns($blueprint));

		foreach ($columns as $column)
		{
			$statements[] = 'alter table '.$table.' '.$column;
		}

		return $statements;
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
		$columns = $this->columnize($command->columns);

		$table = $this->wrap_table($blueprint);

		return "CREATE UNIQUE INDEX {$command->index} ON {$table} ({$columns})";
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

		$table = $this->wrap_table($blueprint);

		return "CREATE INDEX {$command->index} ON {$table} ({$columns})";
	}

	/**
	 * Compile a foreign key command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @return string
	 */
	public function compile_foreign(Schema_Blueprint $blueprint, Fluent $command)
	{
		// Handled on table creation...
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
	 * @param  array            $connection
	 * @return array
	 */
	public function compile_drop_column(Schema_Blueprint $blueprint, Fluent $command, array $connection)
	{
		$schema = $this->_get_doctrine_schema_manager($connection);

		$table_diff = $this->_get_doctrine_table_diff($blueprint, $schema);

		foreach ($command->columns as $name)
		{
			$column = $this->_get_doctrine_column($blueprint->get_table(), $name, $connection);

			$table_diff->removedColumns[$name] = $column;
		}

		return (array) $schema->getDatabasePlatform()->getAlterTableSQL($table_diff);
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
		return "DROP INDEX {$command->index}";
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
		return 'varchar';
	}

	/**
	 * Create the column definition for a string type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_string(Fluent $column)
	{
		return 'varchar';
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
	 * Create the column definition for a integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_integer(Fluent $column)
	{
		return 'integer';
	}

	/**
	 * Create the column definition for a big integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_big_integer(Fluent $column)
	{
		return 'integer';
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
		return 'integer';
	}

	/**
	 * Create the column definition for a small integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_small_integer(Fluent $column)
	{
		return 'integer';
	}

	/**
	 * Create the column definition for a float type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_float(Fluent $column)
	{
		return 'float';
	}

	/**
	 * Create the column definition for a double type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_double(Fluent $column)
	{
		return 'float';
	}

	/**
	 * Create the column definition for a decimal type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_decimal(Fluent $column)
	{
		return 'float';
	}

	/**
	 * Create the column definition for a boolean type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_boolean(Fluent $column)
	{
		return 'tinyint';
	}

	/**
	 * Create the column definition for an enum type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_enum(Fluent $column)
	{
		return 'varchar';
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
		return 'datetime';
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
		return 'datetime';
	}

	/**
	 * Create the column definition for a binary type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_binary(Fluent $column)
	{
		return 'blob';
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
		if (in_array($column->type, $this->serials) && $column->auto_increment)
		{
			return ' primary key autoincrement';
		}
	}

}