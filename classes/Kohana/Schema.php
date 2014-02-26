<?php defined('SYSPATH') OR die('No direct access allowed.');

class Kohana_Schema {

	/**
	 * Modify a table on the schema.
	 *
	 * @param  string   $table
	 * @param  Closure  $callback
	 * @param  string   $db
	 * @return Schema_Blueprint
	 */
	public static function table($table, Closure $callback, $db = NULL)
	{
		return (new Schema_Builder($table, $callback, $db))->table($table, $callback);
	}

	/**
	 * Create a new table on the schema.
	 *
	 * @param  string   $table
	 * @param  Closure  $callback
	 * @param  string   $db
	 * @return Schema_Blueprint
	 */
	public static function create($table, Closure $callback, $db = NULL)
	{
		return (new Schema_Builder($table, $callback, $db))->create($table, $callback);
	}

	/**
	 * Drop a table from the schema.
	 *
	 * @param  string  $table
	 * @param  string   $db
	 * @return Schema_Blueprint
	 */
	public static function drop($table, $db = NULL)
	{
		return (new Schema_Builder($table, NULL, $db))->drop($table);

	}

	/**
	 * Rename a table on the schema.
	 *
	 * @param  string  $from
	 * @param  string  $to
	 * @param  string   $db
	 * @return Schema_Blueprint
	 */
	public static function rename($from, $to, $db = NULL)
	{
		return (new Schema_Builder($from, NULL, $db))->rename($from, $to);

	}

}