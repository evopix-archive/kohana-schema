<?php defined('SYSPATH') OR die('No direct script access.');

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Schema\AbstractSchemaManager as SchemaManager;

abstract class Kohana_Schema_Grammar {

	/**
	 * The grammar table prefix.
	 *
	 * @var string
	 */
	protected $_table_prefix = '';

	/**
	 * Wrap an array of values.
	 *
	 * @param  array $values
	 * @return array
	 */
	public function wrap_array(array $values)
	{
		return array_map(array($this, 'wrap'), $values);
	}

	/**
	 * Wrap a table in keyword identifiers.
	 *
	 * @param  string $table
	 * @return string
	 */
	public function wrap_table($table)
	{
		if ($table instanceof Schema_Blueprint) $table = $table->get_table();

		return $this->wrap($this->_table_prefix.$table);
	}

	/**
	 * Wrap a value in keyword identifiers.
	 *
	 * @param  string $value
	 * @return string
	 */
	public function wrap($value)
	{
		if ($value instanceof Fluent) $value = $value->name;

		// If the value being wrapped has a column alias we will need to separate out
		// the pieces so we can wrap each of the segments of the expression on it
		// own, and then joins them both back together with the "as" connector.
		if (strpos(strtolower($value), ' as ') !== FALSE)
		{
			$segments = explode(' ', $value);

			return $this->wrap($segments[0]).' as '.$this->wrap($segments[2]);
		}

		$wrapped = array();

		$segments = explode('.', $value);

		// If the value is not an aliased table expression, we'll just wrap it like
		// normal, so if there is more than one segment, we will wrap the first
		// segments as if it was a table and the rest as just regular values.
		foreach ($segments as $key => $segment)
		{
			if ($key == 0 && count($segments) > 1)
			{
				$wrapped[] = $this->wrap_table($segment);
			}
			else
			{
				$wrapped[] = $this->wrap_value($segment);
			}
		}

		return implode('.', $wrapped);
	}

	/**
	 * Wrap a single string in keyword identifiers.
	 *
	 * @param  string $value
	 * @return string
	 */
	protected function wrap_value($value)
	{
		return $value !== '*' ? sprintf($this->_wrapper, $value) : $value;
	}

	/**
	 * Convert an array of column names into a delimited string.
	 *
	 * @param  array $columns
	 * @return string
	 */
	public function columnize(array $columns)
	{
		return implode(', ', array_map(array($this, 'wrap'), $columns));
	}

	/**
	 * Get the format for database stored dates.
	 *
	 * @return string
	 */
	public function get_date_format()
	{
		return 'Y-m-d H:i:s';
	}

	/**
	 * Get the grammar's table prefix.
	 *
	 * @return string
	 */
	public function get_table_prefix()
	{
		return $this->_table_prefix;
	}

	/**
	 * Set the grammar's table prefix.
	 *
	 * @param  string $prefix
	 * @return Schema_Grammar
	 */
	public function set_table_prefix($prefix)
	{
		$this->_table_prefix = $prefix;

		return $this;
	}

	/**
	 * Compile a rename column command.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $command
	 * @param  array            $connection
	 * @return array
	 */
	public function compile_rename_column(Schema_Blueprint $blueprint, Fluent $command, array $connection)
	{
		$schema = $this->_get_doctrine_schema_manager($connection);

		$table = $this->get_table_prefix().$blueprint->get_table();

		$column = $this->_get_doctrine_column($table, $command->from, $connection);

		$table_diff = $this->_get_renamed_diff($blueprint, $command, $column, $schema);

		return (array) $schema->getDatabasePlatform()->getAlterTableSQL($table_diff);
	}

	/**
	 * Get a new column instance with the new column name.
	 *
	 * @param  Schema_Blueprint                            $blueprint
	 * @param  Fluent                                      $command
	 * @param  \Doctrine\DBAL\Schema\Column                $column
	 * @param  \Doctrine\DBAL\Schema\AbstractSchemaManager $schema
	 * @return \Doctrine\DBAL\Schema\TableDiff
	 */
	protected function _get_renamed_diff(Schema_Blueprint $blueprint, Fluent $command, Column $column, SchemaManager $schema)
	{
		$table_diff = $this->_get_doctrine_table_diff($blueprint, $schema);

		return $this->_set_renamed_columns($table_diff, $command, $column);
	}

	/**
	 * Set the renamed columns on the table diff.
	 *
	 * @param  \Doctrine\DBAL\Schema\TableDiff $table_diff
	 * @param  Fluent                          $command
	 * @param  \Doctrine\DBAL\Schema\Column    $column
	 * @return \Doctrine\DBAL\Schema\TableDiff
	 */
	protected function _set_renamed_columns(TableDiff $table_diff, Fluent $command, Column $column)
	{
		$new_column = new Column($command->to, $column->getType(), $column->toArray());

		$table_diff->renamedColumns = array($command->from => $new_column);

		return $table_diff;
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
		$table = $this->wrap_table($blueprint);

		$on = $this->wrap_table($command->on);

		// We need to prepare several of the elements of the foreign key definition
		// before we can create the SQL, such as wrapping the tables and convert
		// an array of columns to comma-delimited strings for the SQL queries.
		$columns = $this->columnize($command->columns);

		$on_columns = $this->columnize((array) $command->references);

		$sql = "ALTER TABLE {$table} ADD CONSTRAINT {$command->index} ";

		$sql .= "foreign key ({$columns}) references {$on} ({$on_columns})";

		// Once we have the basic foreign key creation statement constructed we can
		// build out the syntax for what should happen on an update or delete of
		// the affected columns, which will get something like "cascade", etc.
		if (!is_null($command->on_delete))
		{
			$sql .= " on delete {$command->on_delete}";
		}

		if (!is_null($command->on_update))
		{
			$sql .= " on update {$command->on_update}";
		}

		return $sql;
	}

	/**
	 * Compile the blueprint's column definitions.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @return array
	 */
	protected function _get_columns(Schema_Blueprint $blueprint)
	{
		$columns = array();

		foreach ($blueprint->get_columns() as $column)
		{
			// Each of the column types have their own compiler functions which are tasked
			// with turning the column definition into its SQL format for this platform
			// used by the connection. The column's modifiers are compiled and added.
			$sql = $this->wrap($column).' '.$this->_get_type($column);

			$columns[] = $this->_add_modifiers($sql, $blueprint, $column);
		}

		return $columns;
	}

	/**
	 * Add the column modifiers to the definition.
	 *
	 * @param  string           $sql
	 * @param  Schema_Blueprint $blueprint
	 * @param  Fluent           $column
	 * @return string
	 */
	protected function _add_modifiers($sql, Schema_Blueprint $blueprint, Fluent $column)
	{
		foreach ($this->_modifiers as $modifier)
		{
			if (method_exists($this, $method = "modify_{$modifier}"))
			{
				$sql .= $this->{$method}($blueprint, $column);
			}
		}

		return $sql;
	}

	/**
	 * Get the primary key command if it exists on the blueprint.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  string           $name
	 * @return Fluent|null
	 */
	protected function _get_command_by_name(Schema_Blueprint $blueprint, $name)
	{
		$commands = $this->_get_commands_by_name($blueprint, $name);

		if (count($commands) > 0)
		{
			return reset($commands);
		}
	}

	/**
	 * Get all of the commands with a given name.
	 *
	 * @param  Schema_Blueprint $blueprint
	 * @param  string           $name
	 * @return array
	 */
	protected function _get_commands_by_name(Schema_Blueprint $blueprint, $name)
	{
		return array_filter($blueprint->get_commands(), function ($value) use ($name)
		{
			return $value->name == $name;
		});
	}

	/**
	 * Get the SQL for the column data type.
	 *
	 * @param  Fluent $column
	 * @return string
	 */
	protected function _get_type(Fluent $column)
	{
		return $this->{"type_".$column->type}($column);
	}

	/**
	 * Add a prefix to an array of values.
	 *
	 * @param  string $prefix
	 * @param  array  $values
	 * @return array
	 */
	public function prefix_array($prefix, array $values)
	{
		return array_map(function ($value) use ($prefix)
		{
			return $prefix.' '.$value;

		}, $values);
	}

	/**
	 * Format a value so that it can be used in "default" clauses.
	 *
	 * @param  mixed $value
	 * @return string
	 */
	protected function _get_default_value($value)
	{
		if ($value instanceof Expression) return $value;

		if (is_bool($value)) return "'".intval($value)."'";

		return "'".strval($value)."'";
	}

	/**
	 * Create an empty Doctrine DBAL TableDiff from the Blueprint.
	 *
	 * @param  Schema_Blueprint                            $blueprint
	 * @param  \Doctrine\DBAL\Schema\AbstractSchemaManager $schema
	 * @return \Doctrine\DBAL\Schema\TableDiff
	 */
	protected function _get_doctrine_table_diff(Schema_Blueprint $blueprint, SchemaManager $schema)
	{
		$table = $this->get_table_prefix().$blueprint->get_table();

		$table_diff = new TableDiff($table);

		$table_diff->fromTable = $schema->listTableDetails($table);

		return $table_diff;
	}

	/**
	 * Gets a Doctrine Schema Manager instance.
	 *
	 * @param array $connection
	 * @return mixed
	 */
	protected function _get_doctrine_schema_manager(array $connection)
	{
		$driver = $this->_get_doctrine_driver($connection);

		return $driver->getSchemaManager($this->_get_doctrine_connection($connection));
	}

	/**
	 * Get a Doctrine Schema Column instance.
	 *
	 * @param string $table
	 * @param string $column
	 * @param array  $connection
	 * @return \Doctrine\DBAL\Schema\Column
	 */
	protected function _get_doctrine_column($table, $column, array $connection)
	{
		$schema = $this->_get_doctrine_schema_manager($connection);

		return $schema->listTableDetails($table)->getColumn($column);
	}

	/**
	 * Gets a Doctrine Connection instance.
	 *
	 * @param array $connection
	 * @return \Doctrine\DBAL\Connection
	 */
	protected function _get_doctrine_connection(array $connection)
	{
		$driver = strtolower($connection['type']);
		if ($driver === 'pdo')
		{
			$dsn     = explode(';', $connection['connection']['dsn']);
			$db_name = explode('=', $dsn[1])[1];
			list($type, $host) = explode(':', $dsn[0]);
			$host   = explode('=', $host)[1];
			$driver = 'pdo_'.strtolower($type);

			$data = [
				'dbname'   => $db_name,
				'user'     => $connection['connection']['username'],
				'password' => $connection['connection']['password'],
				'host'     => $host,
				'driver'   => $driver,
			];
		}
		else
		{
			if ($driver === 'mysql')
			{
				$driver = 'mysqli';
			}

			$data = [
				'dbname'   => $connection['connection']['database'],
				'user'     => $connection['connection']['username'],
				'password' => $connection['connection']['password'],
				'host'     => $connection['connection']['hostname'],
				'driver'   => $driver,
			];
		}

		return new \Doctrine\DBAL\Connection($data, $this->_get_doctrine_driver($connection));
	}

	/**
	 * Gets a Doctrine Driver instance.
	 *
	 * @param array $connection
	 * @return mixed
	 */
	protected function _get_doctrine_driver(array $connection)
	{
		$driver = strtolower($connection['type']);
		if ($driver === 'pdo')
		{
			$dsn = explode(';', $connection['connection']['dsn']);
			list($type, $host) = explode(':', $dsn[0]);
			$driver = 'pdo_'.strtolower($type);
		}

		switch ($driver)
		{
			case 'mysql':
			case 'mysqli':
				$driver = 'Mysqli';
				break;
			case 'pdo_mysql':
				$driver = 'PDOMySql';
				break;
			case 'pdo_sqlite':
				$driver = 'PDOSqlite';
				break;
			case 'pdo_sqlsrv':
				$driver = 'PDOSqlsrv';
				break;
			case 'pdo_pgsql':
				$driver = 'PDOPgSql';
				break;
		}

		$driver = "\\Doctrine\\DBAL\\Driver\\$driver\\Driver";

		return new $driver;
	}

}