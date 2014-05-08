<?php defined('SYSPATH') OR die('No direct script access.');

abstract class Kohana_Schema_Grammar_MySQL extends Schema_Grammar {

	/**
	 * The keyword identifier wrapper format.
	 *
	 * @var string
	 */
	protected $_wrapper = '`%s`';

	/**
	 * The possible column modifiers.
	 *
	 * @var array
	 */
	protected $_modifiers = array('unsigned', 'nullable', 'default', 'increment', 'after');

	/**
	 * The possible column serials
	 *
	 * @var array
	 */
	protected $_serials = array('big_integer', 'integer', 'medium_integer', 'small_integer', 'tiny_integer');

	/**
	 * Compile the query to determine the list of tables.
	 *
	 * @return string
	 */
	public function compile_table_exists()
	{
		return 'SELECT * FROM information_schema.tables WHERE table_schema = :db AND table_name = :table';
	}

	/**
	 * Compile the query to determine the list of columns.
	 *
	 * @return string
	 */
	public function compile_column_exists()
	{
		return "SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?";
	}

	/**
	 * Compile a create table command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @param  array            $connection
	 * @return string
	 */
	public function compile_create(Schema_Blueprint $blueprint, Fluent $command, array $connection)
	{
		$columns = implode(', ', $this->_get_columns($blueprint));

		$sql = 'create table '.$this->wrap_table($blueprint)." ($columns)";

		// Once we have the primary SQL, we can add the encoding option to the SQL for
		// the table.  Then, we can check if a storage engine has been supplied for
		// the table. If so, we will add the engine declaration to the SQL query.
		$sql = $this->compile_create_encoding($sql, $connection);

		if (isset($blueprint->engine))
		{
			$sql .= ' engine = '.$blueprint->engine;
		}

		return $sql;
	}

	/**
	 * Append the character set specifications to a command.
	 *
	 * @param  string $sql
	 * @param  array  $connection
	 * @return string
	 */
	protected function compile_create_encoding($sql, array $connection)
	{
		if (!is_null($charset = $connection['charset']))
		{
			$sql .= ' default character set '.$charset;
		}

		if (!is_null($collation = Arr::get($connection, 'collation')))
		{
			$sql .= ' collate '.$collation;
		}

		return $sql;
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

		$columns = $this->prefix_array('add', $this->_get_columns($blueprint));

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
		$command->name(NULL);

		return $this->_compile_key($blueprint, $command, 'primary key');
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
		return $this->_compile_key($blueprint, $command, 'unique');
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
		return $this->_compile_key($blueprint, $command, 'index');
	}

	/**
	 * Compile an index creation command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @param  string           $type
	 * @return string
	 */
	protected function _compile_key(Schema_Blueprint $blueprint, Fluent $command, $type)
	{
		$columns = $this->columnize($command->columns);

		$table = $this->wrap_table($blueprint);

		return "ALTER TABLE {$table} ADD {$type} {$command->index}($columns)";
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
		$columns = $this->prefix_array('drop', $this->wrap_array($command->columns));

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
		$sql    = $this->wrap($column).' '.$this->_get_type($column);
		$table  = $this->wrap_table($blueprint);

		return 'alter table '.$table.' modify column '.$this->_add_modifiers($sql, $blueprint, $column);
	}

	/**
	 * Compile a modify column command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @param  array            $connection
	 * @return string
	 */
	public function compile_modify_nullable(Schema_Blueprint $blueprint, Fluent $command, array $connection)
	{
		$column   = $command->column;
		$nullable = $command->nullable;
		$sql      = $this->wrap($column);
		$table    = $this->wrap_table($blueprint);

		if ($nullable)
		{
			$sql .= ' null';
		}
		else
		{
			$sql .= ' not null';
		}

		return 'alter table '.$table.' modify column '.$sql;
	}

	/**
	 * Compile a modify column command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @param  array            $connection
	 * @return string
	 */
	public function compile_modify_default(Schema_Blueprint $blueprint, Fluent $command, array $connection)
	{
		$column  = $command->column;
		$default = $command->default;
		$sql     = $this->wrap($column);
		$table   = $this->wrap_table($blueprint);

		if ($default === FALSE)
		{
			$sql .= ' drop default';
		}
		else
		{
			$sql .= ' set default '.$this->_get_default_value($default);
		}

		return 'alter table '.$table.' alter column '.$sql;
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
		return 'alter table '.$this->wrap_table($blueprint).' drop primary key';
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

		return "ALTER TABLE {$table} DROP INDEX {$command->index}";
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
		$table = $this->wrap_table($blueprint);

		return "ALTER TABLE {$table} DROP INDEX {$command->index}";
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

		return "ALTER TABLE {$table} DROP FOREIGN KEY {$command->index}";
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

		return "rename table {$from} to ".$this->wrap_table($command->to);
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
		return 'mediumtext';
	}

	/**
	 * Create the column definition for a long text type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_long_text(Fluent $column)
	{
		return 'longtext';
	}

	/**
	 * Create the column definition for a big integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_big_integer(Fluent $column)
	{
		return 'bigint';
	}

	/**
	 * Create the column definition for a integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_integer(Fluent $column)
	{
		return 'int';
	}

	/**
	 * Create the column definition for a medium integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_medium_integer(Fluent $column)
	{
		return 'mediumint';
	}

	/**
	 * Create the column definition for a tiny integer type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_tiny_integer(Fluent $column)
	{
		return 'tinyint';
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
		return "float({$column->total}, {$column->places})";
	}

	/**
	 * Create the column definition for a double type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_double(Fluent $column)
	{
		if ($column->total && $column->places)
		{
			return "double({$column->total}, {$column->places})";
		}
		else
		{
			return 'double';
		}
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
		return 'tinyint(1)';
	}

	/**
	 * Create the column definition for an enum type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function type_enum(Fluent $column)
	{
		return "enum('".implode("', '", $column->allowed)."')";
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
		if (!$column->nullable) return 'timestamp default 0';

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
		return 'blob';
	}

	/**
	 * Get the SQL for an unsigned column modifier.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $column
	 * @return string|null
	 */
	protected function modify_unsigned(Schema_Blueprint $blueprint, Fluent $column)
	{
		if ($column->unsigned) return ' unsigned';
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
			return ' auto_increment primary key';
		}
	}

	/**
	 * Get the SQL for an "after" column modifier.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $column
	 * @return string|null
	 */
	protected function modify_after(Schema_Blueprint $blueprint, Fluent $column)
	{
		if (!is_null($column->after))
		{
			return ' after '.$this->wrap($column->after);
		}
	}

}