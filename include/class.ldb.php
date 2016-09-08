<?php
/** @file
 * LDB database layer class
 *
 * Written by Peter,
 * mailto:petro@petro.ws
 * http://petro.ws/projects/web/php-light-classes/
 */

/*
 * Object name (index in $GLOBALS) to store DB object(s)
 */
if (!defined('LDB_OBJECT_VAR'))
	define('LDB_OBJECT_VAR', 'ldb');
/*
 * Exit on error or not (criticals - connection, etc)
 */
if (!defined('LDB_EXIT_ON_ERROR'))
	define('LDB_EXIT_ON_ERROR', true);
/*
 * Default config index
 */
if (!defined('LDB_DEFAULT_CONFIG'))
	define('LDB_DEFAULT_CONFIG', 'main');

/*
 * Init global vars. Redefine if needed after inclusion
 */
if (isset($GLOBALS['config']['ldb']))
	$GLOBALS['ldb_config'] = &$GLOBALS['config']['ldb'];

/*
 * Instanses counter. Closing all connections ONLY if counter == 0
 */
$GLOBALS['ldb_instanses'] = 0;
$GLOBALS['ldb_connections'] = array();

/*
 * Some internal constants
 */
define('LDB_LOG_INFO', 1);
define('LDB_LOG_ERROR', 2);
define('LDB_LOG_QUERY', 3);

/*
 * = Main LDB class
 * =============================================================================
 * Wrapper for drivers and global scope object
 */

class ldb
{
	public $current_cfg = false;
	
	public $log = array();
	public $log_enable = true;
	public $log_max_size = 1000;
	
	static $instances = 0;
	public $instance;
	
	public $connections = array();
	public $drivers = array();
	
	public $tms_start = 0;
	
	function __construct()
	{
		$GLOBALS['ldb_instanses']++;
		$this->current_cfg = LDB_DEFAULT_CONFIG;
		
		$this->instance = ++self::$instances;
		
		# Create new connection data
		$GLOBALS['ldb_connections'][ $this->instance ] = array(
			'instances' => 1,
			'drivers'   => array(),
		);
		
		$this->connections = &$GLOBALS['ldb_connections'][ $this->instance ];
		$this->drivers = &$GLOBALS['ldb_connections'][ $this->instance ]['drivers'];
	}
	
	function __destruct()
	{
		# Unregister session
		$this->connections['instances']--;
		
		# Close all opened connections if we - last
		if ($this->connections['instances'] <= 0) {
			# We are Last (for this session) - clear all connections
			if ($this->drivers) {
				foreach ($this->drivers as $drv) {
					$drv->close();
				}
			}
			
			# Unregister global object
			unset($GLOBALS['ldb_connections'][ $this->instance ]);
		}
	}
	
	public function __clone()
	{
		# Clone object
		# Register new instance
		$this->connections['instances']++;
		$this->log = array();
	}
	
	static function get_instance($cfg_name = false)
	{
		# Has global object?
		if (!isset($GLOBALS[ LDB_OBJECT_VAR ])) {
			# Need new object!
			
			$GLOBALS[ LDB_OBJECT_VAR ] = new ldb ();
		}
		# Change driver if needed
		if ($cfg_name !== false)
			$GLOBALS[ LDB_OBJECT_VAR ]->select_cfg($cfg_name);
		
		return $GLOBALS[ LDB_OBJECT_VAR ];
	}
	
	/**
	 * Get exitsting global or creates new (copy) of it
	 * @param <string> $cfg_name Which config to use
	 * @param <bool or object> $new_object if bool: true/false - create or not new object (if global exists, copy from it); object - creates new copy of given object
	 * @return <object> - LDB instanse
	 */
	function get_object($cfg_name = false, $new_object = false, $new_instance = false)
	{
		if (!$new_object) {
			# Create new object at global scope (and it stands as default)
			$obj = ldb::get_instance($cfg_name);
		} else {
			# Create new local object
			# From existing?
			if (is_object($new_object)) {
				$obj = clone $new_object;
			} else {
				if ($new_object) {
					if (!isset($GLOBALS[ LDB_OBJECT_VAR ]) || $new_instance) {
						$obj = new ldb ();
						$obj->init($cfg_name);
					} else {
						$obj = clone $GLOBALS[ LDB_OBJECT_VAR ];
					}
				} else {
					$obj = &$GLOBALS[ LDB_OBJECT_VAR ];
				}
			}
		}
		# Change driver if needed
		if ($cfg_name !== false)
			$obj->select_cfg($cfg_name);
		
		return $obj;
	}
	
	function select_cfg($cfg)
	{
		if ($cfg && $this->current_cfg != $cfg) {
			$this->current_cfg = $cfg;
		}
	}
	
	function init($cfg_name = false)
	{
		if (!$cfg_name)
			$cfg_name = $this->current_cfg;
		else
			$this->current_cfg = $cfg_name;
		
		# Check config existance
		if (!isset($GLOBALS['ldb_config'][ $cfg_name ])) {
			# Error - no such config record
			if (LDB_EXIT_ON_ERROR) {
				exit("LDB Error: no such config '$cfg_name'\n");
			} else {
				$this->log(LDB_LOG_ERROR, array('error' => "No such config '$cfg_name'"));
			}
			
			return false;
		}
		
		# Check driver existance
		$driver_class = 'ldb_' . $GLOBALS['ldb_config'][ $cfg_name ]['type'];
		if (!class_exists($driver_class, false)) {
			# Error - no such config record
			if (LDB_EXIT_ON_ERROR) {
				exit("LDB Error: no such driver '" . $GLOBALS['ldb_config'][ $cfg_name ]['type'] . "'\n");
			} else {
				$this->log(LDB_LOG_ERROR, array('error' => "No such driver '" . $GLOBALS['ldb_config'][ $cfg_name ]['type'] . "'"));
			}
			
			return false;
		}
		
		# Okay, try to init our DB!
		if (!@$this->drivers[ $cfg_name ]) {
			$mt_start = microtime(true);
			
			# Try to init driver
			$this->drivers[ $cfg_name ] = new $driver_class ();
			# Save config
			$this->drivers[ $cfg_name ]->cfg = $GLOBALS['ldb_config'][ $cfg_name ];
			$this->drivers[ $cfg_name ]->ldb = &$this;
			# Init procedure (connection? depends on driver)
			if (!$this->drivers[ $cfg_name ]->init()) {
				if (LDB_EXIT_ON_ERROR) {
					exit("LDB Error: Driver '" . $GLOBALS['ldb_config'][ $cfg_name ]['type'] . "' error: " . $this->drivers[ $cfg_name ]->error() . "\n");
				} else {
					$this->log(LDB_LOG_ERROR, array('error' => $this->drivers[ $cfg_name ]->error()));
				}
				
				return false;
			}
			
			$mt_end = microtime(true) - $mt_start;
			$this->log(LDB_LOG_INFO, array('msg' => "Driver '$cfg_name' init OK", 'time' => $mt_end));
			
		}
		
		# All ok!
		return true;
	}
	
	function driver()
	{
		if (!@$this->drivers[ $this->current_cfg ] && !$this->init($this->current_cfg))
			return false; # Init driver falure
		return $this->drivers[ $this->current_cfg ];
	}
	
	function log($type, $data)
	{
		if (!$this->log_enable) return;
		
		$data['tms'] = time();
		$data['type'] = $type;
		$data['cfg'] = $this->current_cfg;
		$this->log[] = $data;
		
		if (count($this->log) >= $this->log_max_size) {
			# Truncate log
			$this->log = array_slice($this->log, 0, $this->log_max_size);
		}
	}
	
	function log_html()
	{
		$out = '';
		$out .= '<table width="100%" cellpadding="3" cellspacing="1">';
		
		$all_time = 0.00;
		
		for ($x = 0; $x < count($this->log); $x++) {
			$out .= '<tr style="' . ($x % 2 ? 'background-color:#EEE' : 'background-color:#FFF') . '">';
			$out .= '<td width="50" align="center">' . ($x + 1) . '</td>';
			
			$all_time += @$this->log[ $x ]['time'];
			
			if ($this->log[ $x ]['type'] == LDB_LOG_INFO) {
				$out .= '<td width="50" align="center">INFO</td>';
				$out .= '<td width="50" align="center">' . (@$this->log[ $x ]['cfg'] ? $this->log[ $x ]['cfg'] : '-') . '</td>';
				$out .= '<td width="80" align="right"><code>' . (@$this->log[ $x ]['time'] ? sprintf('%.5f', $this->log[ $x ]['time']) : '-') . ' s</code></td>';
				$out .= '<td align="left"><code>' . $this->log[ $x ]['msg'] . '</code></td>';
			}
			if ($this->log[ $x ]['type'] == LDB_LOG_QUERY) {
				$out .= '<td width="50" align="center">QUERY</td>';
				$out .= '<td width="50" align="center">' . (@$this->log[ $x ]['cfg'] ? $this->log[ $x ]['cfg'] : '-') . '</td>';
				$out .= '<td width="80" align="right"><code>' . (@$this->log[ $x ]['time'] ? sprintf('%.5f', $this->log[ $x ]['time']) : '-') . ' s</code></td>';
				$out .= '<td align="left"><code>';
				$out .= 'Query: <b>' . $this->log[ $x ]['msg'] . '</b><br/>';
				if (@$this->log[ $x ]['error'])
					$out .= 'Error: <b style="color:red;">' . $this->log[ $x ]['error'] . '</b><br/>';
				else
					$out .= 'Rows: <b>' . $this->log[ $x ]['rows'] . '</b><br/>';
				$out .= '</code></td>';
			}
			
			$out .= '</tr>';
		}
		
		$out .= '<tr style="background-color:#CEE">';
		$out .= '<td colspan="3"><b>Total:</b></td>';
		$out .= '<td align="right"><code>' . sprintf('%.5f', $all_time) . ' s</code></td>';
		$out .= '</tr>';
		
		$out .= '</table>';
		
		return $out;
	}
	
	function escape($s)
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		return $driver->escape($s);
	}
	
	function query($q)
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		$mt_start = microtime(true);
		$res = $driver->query($q);
		$mt_end = microtime(true) - $mt_start;
		$log_data = array();
		$log_data['type'] = LDB_LOG_QUERY;
		$log_data['time'] = $mt_end;
		$log_data['msg'] = $q;
		$log_data['error'] = $driver->error();
		$log_data['rows'] = $driver->num_rows();
		$this->log(LDB_LOG_QUERY, $log_data);
		
		return $res;
	}
	
	function select($table, $what = false, $where = false)
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		$query = $driver->construct_select($table, $what, $where);
		
		return $this->query($query);
	}
	
	function select_one($table, $what, $id, $id_text = 'id')
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		$query = $driver->construct_select_one($table, $what, $id, $id_text);
		$res = $this->query($query);
		
		return $res ? $res[0] : array();
	}
	
	function select_one_exp($table, $what, $where = false)
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		$query = $driver->construct_select($table, $what, $where);
		$res = $this->query($query);
		
		return $res ? $res[0] : array();
	}
	
	function insert($table, $db_data)
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		$query = $driver->construct_insert($table, $db_data);
		$res = $this->query($query);
		
		return $driver->error() ? false : $driver->insert_id();
	}
	
	function insert_multiply($table, $db_data)
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		$query = $driver->construct_insert_multiply($table, $db_data);
		$res = $this->query($query);
		
		return $driver->error() ? false : $driver->insert_id();
	}
	
	function replace($table, $db_data)
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		$query = $driver->construct_replace($table, $db_data);
		$res = $this->query($query);
		
		return $driver->error() ? false : $driver->insert_id();
	}
	
	function insert_id()
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		return $driver->insert_id();
	}
	
	function update_by_id($table, $id, $db_data, $id_text = 'id', $no_quote = array())
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		$query = $driver->construct_update_id($table, $id, $db_data, $id_text, $no_quote);
		$res = $this->query($query);
		
		return $driver->error() ? false : $driver->num_rows();
	}
	
	function tables($db = false)
	{
		$driver = $this->driver();
		if (!$driver)
			return;
		
		if (!$db)
			$db = $this->current_cfg['base'];
		
		return $driver->tables($db);
	}
	
	function count($table, $where = false)
	{
		$driver = $this->driver();
		if (!$driver) return;
		
		return intval($driver->count($table, $where));
	}
}

/*
 * = Public functions for fast and easy usage
 * =============================================================================
 */

/**
 * Get LDB object
 * @param <string> $cfg_name - Config name (defined as $GLOBALS['config'][$cfg_name])
 * @return obect - LDB class
 */
function ldb_object($cfg_name = false, $new_object = false, $new_instance = false)
{
	$core = ldb::get_instance();
	
	return $core->get_object($cfg_name, $new_object);
}

function ldb_cfg_select($name = false)
{
	$core = ldb::get_instance();
	$core->current_cfg = $name;
}

function ldb_select($table, $what = false, $where = false)
{
	# Get core & start it if needed
	$core = ldb::get_instance();
	
	return $core->select($table, $what, $where);
}

function ldb_select_one($table, $what, $id, $id_text = 'id')
{
	# Get core & start it if needed
	$core = ldb::get_instance();
	
	return $core->select_one($table, $what, $id, $id_text);
}

function ldb_select_one_exp($table, $what, $where = false)
{
	# Get core & start it if needed
	$core = ldb::get_instance();
	
	return $core->select_one_exp($table, $what, $where);
}

function ldb_insert($table, $db_data)
{
	$core = ldb::get_instance();
	
	return $core->insert($table, $db_data);
}

function ldb_insert_multiply($table, $db_data)
{
	$core = ldb::get_instance();
	
	return $core->insert_multiply($table, $db_data);
}

function ldb_replace($table, $db_data)
{
	$core = ldb::get_instance();
	
	return $core->replace($table, $db_data);
}

function ldb_insert_id()
{
	$core = ldb::get_instance();
	
	return $core->insert_id();
}

function ldb_update_by_id($table, $id, $db_data, $id_text = 'id', $no_quote = array())
{
	# Get core & start it if needed
	$core = ldb::get_instance();
	
	return $core->update_by_id($table, $id, $db_data, $id_text, $no_quote);
}

function ldb_query($query)
{
	$core = ldb::get_instance();
	
	return $core->query($query);
}

function ldb_tables($db = false)
{
	$core = ldb::get_instance();
	
	return $core->tables($db);
}

function ldb_count($table, $where = false)
{
	$core = ldb::get_instance();
	
	return $core->count($table, $where);
}

function ldb_escape($s)
{
	$core = ldb::get_instance();
	
	return $core->escape($s);
}

/*
 * Info functions
 */

function ldb_log_enable($state)
{
	$core = ldb::get_instance();
	$core->log_enable = (bool)$state;
}

function ldb_log()
{
	# Check - we have instance?
	if (!isset($GLOBALS[ LDB_OBJECT_VAR ])) return array();
	
	$core = ldb::get_instance();
	
	return $core->log;
}

function ldb_log_html()
{
	# Check - we have instance?
	if (!isset($GLOBALS[ LDB_OBJECT_VAR ])) return '<p>LDB is not active now.</p>';
	
	$core = ldb::get_instance();
	
	return $core->log_html();
}

function ldb_error()
{
	$drv = ldb::get_instance()->driver();
	if (!$drv) return 'Driver init error';
	
	return $drv->last_error;
}

/*
 * Service functions
 */

/**
 * Creates assotiative array from ldb result. Indexes are made by keys (`id` by default)
 * @param <array> $res - Entrie result from ldb_select_* family
 * @param <string> $id_key - Key to sort by, default: id
 * @return array - Assotiative array
 */
function ldb_id2key($res, $id_key = 'id')
{
	$out = array();
	if (!$res) return $out;
	for ($x = 0; $x < count($res); $x++)
		$out[ $res[ $x ][ $id_key ] ] = $res[ $x ];
	
	return $out;
}

/**
 * Get array of values from result (list of ID's for example)
 * @param <array> $res - Entrie result from ldb_select_* family
 * @param <string> $id_key - Key to search, default: id
 * @return array - Indexed array of values
 */
function ldb_ids($res, $id_key = 'id')
{
	$out = array();
	if (!$res) return $out;
	for ($x = 0; $x < count($res); $x++)
		$out[] = $res[ $x ][ $id_key ];
	
	return $out;
}

/*
 * = Drivers section
 * =============================================================================
 * Classes for support mysql, pgsql, etc.. Add your own!
 */

/*
 * Base class for driver
 */

abstract class ldb_driver
{
	public $ldb; # Link to the mother
	
	public $link;
	public $cfg;
	public $db_name;
	public $log;
	
	public $last_error;
	
	function __construct()
	{
	}
	
	function __destruct()
	{
	}
	
	function init()
	{
	}
	
	function close()
	{
	}
	
	function error()
	{
	}
	
	function escape($s)
	{
		return $s;
	}
	
	function query($query)
	{
	}
	
	function num_rows()
	{
	}
	
	function insert_id()
	{
	}
	
	function tables($db)
	{
		$query = 'SHOW TABLES IN `' . $db . '`';
		$res = $this->query($query);
		
		$tables = array();
		foreach ($res as $row) {
			$row = array_values($row);
			$tables[] = $row[0];
		}
		
		return $tables;
	}
	
	function count($table, $where = false)
	{
	}
	
	function construct_select($table, $what = false, $where = false)
	{
	}
	
	function construct_select_one($table, $what, $id, $id_text = 'id')
	{
	}
	
	function construct_insert($table, $db_data)
	{
		$query = 'INSERT INTO ';
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		$query .= $table . ' ';
		
		$ins_index = array();
		$ins_data = array();
		
		foreach ($db_data as $k => $v) {
			$ins_index[] = '`' . $k . '`';
			if (!(is_int($v) || is_float($v)))
				$v = '\'' . $this->escape($v) . '\'';
			$ins_data[] = $v;
		}
		
		$query .= '(' . implode(',', $ins_index) . ') VALUES (' . implode(',', $ins_data) . ')';
		
		return $query;
	}
	
	function construct_insert_multiply($table, $db_data)
	{
		$query = 'INSERT INTO ';
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		$query .= $table . ' ';
		
		$ins_all = array();
		$ins_index = array();
		$ins_data = array();
		
		# print_r($db_data);
		
		# 1. Construct indexes and correct order
		foreach ($db_data as $item) {
			foreach ($item as $k => $v) {
				$ins_index[] = $k;
			}
		}
		
		$ins_index = array_unique($ins_index);
		
		foreach ($db_data as $item) {
			$ins_data = array();
			$v = 'NULL';
			foreach ($ins_index as $index) {
				if (!isset($item[ $index ])) {
					$v = 'NULL';
				} else {
					$v = $item[ $index ];
					if (!(is_int($v) || is_float($v)))
						$v = '\'' . $this->escape($v) . '\'';
					
				}
				$ins_data[] = $v;
			}
			$ins_all[] = '(' . implode(',', $ins_data) . ')';
		}
		
		$query .= '(`' . implode('`,`', $ins_index) . '`) VALUES ' . implode(',', $ins_all);
		
		return $query;
	}
	
	function construct_replace($table, $db_data)
	{
	}
	
	function construct_update_id($table, $id, $db_data, $id_text = 'id', $no_quote = array())
	{
	}
	
	function construct_update_exp($table, $where, $no_quote)
	{
	}
	
	function construct_delete($table, $what, $where, $no_quote)
	{
	}
}

/*
 * = MySQLi driver
 * =============================================================================
 */

class ldb_mysqli extends ldb_driver
{
	public $mysqli;
	public $db_name;
	
	function error()
	{
		return $this->last_error;
	}
	
	function init()
	{
		if (!class_exists('mysqli', false)) {
			$this->last_error = 'Enable to load MySQLi extension!';
			
			return false;
		}
		# Try to init new MySQLi connection!
		$this->mysqli = new mysqli();
		$this->mysqli->connect(@$this->cfg['host'], @$this->cfg['user'], @$this->cfg['pass']);
		if (mysqli_connect_error()) {
			# Workaround. Some versions of PHP has mysql->connect_error broken
			$this->last_error = 'Connection error: ' . mysqli_connect_error();
			
			return false; # Connect error
		}
		
		# Set charset?
		if (@$this->cfg['charset']) {
			$this->ldb->query('SET NAMES \'' . $this->cfg['charset'] . '\'');
		}
		
		# Select DB?
		if (@$this->cfg['base']) {
			if (!$this->mysqli->select_db($this->cfg['base'])) {
				$this->last_error = $this->mysqli->error;
				
				return false; # Connect error
			}
			$this->db_name = $this->cfg['base'];
		}
		
		return true;
	}
	
	function query($q)
	{
		$this->last_error = false;
		if (!$q) {
			$this->last_error = 'Empty query';
			
			return false;
		}
		
		$res = $this->mysqli->query($q);
		$this->last_error = $this->mysqli->error;
		
		# Get result
		$out = array();
		if (is_object($res)) {
			while ($row = $res->fetch_assoc()) {
				$out[] = $row;
			}
			
			$res->close();
		}
		
		return $out;
	}
	
	function escape($s)
	{
		return $this->mysqli->real_escape_string($s);
	}
	
	function num_rows()
	{
		return intval($this->mysqli->affected_rows);
	}
	
	function insert_id()
	{
		return intval($this->mysqli->insert_id);
	}
	
	function construct_select($table, $what = false, $where = false)
	{
		$query = 'SELECT ';
		
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		if (!$what) {
			$what = '*';
		} elseif (is_array($what)) {
			$what = '`' . implode('`,`', $what) . '`';
		} else {
			# Not changing 'what'
		}
		$query .= $what . ' FROM ' . $table;
		
		if ($where)
			$query .= ' WHERE ' . $where;
		
		return $query;
	}
	
	function construct_select_one($table, $what, $id, $id_text = 'id')
	{
		$query = 'SELECT ';
		
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		if (!$what) {
			$what = '*';
		} elseif (is_array($what)) {
			$what = '`' . implode('`,`', $what) . '`';
		} else {
			# Not changing 'what'
		}
		$query .= $what . ' FROM ' . $table;
		
		if (!is_int($id))
			$id = '\'' . $this->escape($id) . '\'';
		
		$query .= ' WHERE `' . $id_text . '`=' . $id . ' LIMIT 1';
		
		return $query;
	}
	
	function construct_replace($table, $db_data)
	{
		$query = 'REPLACE INTO ';
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		$query .= $table . ' ';
		
		$ins_index = array();
		$ins_data = array();
		
		foreach ($db_data as $k => $v) {
			$ins_index[] = '`' . $k . '`';
			if (!(is_int($v) || is_float($v)))
				$v = '\'' . $this->escape($v) . '\'';
			$ins_data[] = $v;
		}
		
		$query .= '(' . implode(',', $ins_index) . ') VALUES (' . implode(',', $ins_data) . ')';
		
		return $query;
	}
	
	function construct_update_id($table, $id, $db_data, $id_text = 'id', $no_quote = array())
	{
		$query = 'UPDATE ';
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		$query .= $table . ' ';
		
		$upd_values = array();
		foreach ($db_data as $k => $v) {
			if (!(is_int($v) || is_float($v)) && !in_array($k, $no_quote))
				$v = '\'' . $this->escape($v) . '\'';
			$upd_values[] = '`' . $k . '`=' . $v;
		}
		if (!is_numeric($id)) $id = '\'' . $this->escape($id) . '\'';
		$query .= 'SET ' . implode(',', $upd_values) . ' WHERE `' . $id_text . '`=' . $id . ' LIMIT 1';
		
		return $query;
	}
	
	function count($table, $where = false)
	{
		$query = 'SELECT ';
		
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		
		$query .= 'COUNT(*) AS `cnt` FROM ' . $table;
		
		if ($where)
			$query .= ' WHERE ' . $where;
		
		$res = $this->ldb->query($query);
		
		return intval(@$res[0]['cnt']);
	}
}

/*
 * = MySQL driver
 * =============================================================================
 */

class ldb_mysql extends ldb_driver
{
	public $mysql;
	public $db_name;
	
	function error()
	{
		return $this->last_error;
	}
	
	function init()
	{
		if (!function_exists('mysql_connect')) {
			$this->last_error = 'Enable to load MySQL extension!';
			
			return false;
		}
		# Try to init new MySQLi connection!
		$this->mysql = mysql_connect(@$this->cfg['host'], @$this->cfg['user'], @$this->cfg['pass']);
		if (!$this->mysql) {
			# Workaround. Some versions of PHP has mysql->connect_error broken
			$this->last_error = 'Connection error: ' . mysql_error($this->mysql);
			
			return false; # Connect error
		}
		
		# Set charset?
		if (@$this->cfg['charset']) {
			$this->ldb->query('SET NAMES \'' . $this->cfg['charset'] . '\'');
		}
		
		# Select DB?
		if (@$this->cfg['base']) {
			if (!mysql_select_db($this->cfg['base'], $this->mysql)) {
				$this->last_error = mysql_error($this->mysql);
				
				return false; # Connect error
			}
			$this->db_name = $this->cfg['base'];
		}
		
		return true;
	}
	
	function query($q)
	{
		$this->last_error = false;
		if (!$q) {
			$this->last_error = 'Empty query';
			
			return false;
		}
		
		$res = mysql_query($q, $this->mysql);
		$this->last_error = mysql_error($this->mysql);
		
		# Get result
		$out = array();
		while ($row = mysql_fetch_assoc($res))
			$out[] = $row;
		
		return $out;
	}
	
	function escape($s)
	{
		return mysql_real_escape_string($s, $this->mysql);
	}
	
	function num_rows()
	{
		return intval(mysql_affected_rows($this->mysql));
	}
	
	function insert_id()
	{
		return intval(mysql_insert_id($this->mysql));
	}
	
	function construct_select($table, $what = false, $where = false)
	{
		$query = 'SELECT ';
		
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		if (!$what) {
			$what = '*';
		} elseif (is_array($what)) {
			$what = '`' . implode('`,`', $what) . '`';
		} else {
			# Not changing 'what'
		}
		$query .= $what . ' FROM ' . $table;
		
		if ($where)
			$query .= ' WHERE ' . $where;
		
		return $query;
	}
	
	function construct_select_one($table, $what, $id, $id_text = 'id')
	{
		$query = 'SELECT ';
		
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		if (!$what) {
			$what = '*';
		} elseif (is_array($what)) {
			$what = '`' . implode('`,`', $what) . '`';
		} else {
			# Not changing 'what'
		}
		$query .= $what . ' FROM ' . $table;
		
		if (!is_numeric($id))
			$id = '\'' . $this->escape($id) . '\'';
		
		$query .= ' WHERE `' . $id_text . '`=' . $id . ' LIMIT 1';
		
		return $query;
	}
	
	function construct_insert($table, $db_data)
	{
		$query = 'INSERT INTO ';
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		$query .= $table . ' ';
		
		$ins_index = array();
		$ins_data = array();
		
		foreach ($db_data as $k => $v) {
			$ins_index[] = '`' . $k . '`';
			if (!(is_int($v) || is_float($v)))
				$v = '\'' . $this->escape($v) . '\'';
			$ins_data[] = $v;
		}
		
		$query .= '(' . implode(',', $ins_index) . ') VALUES (' . implode(',', $ins_data) . ')';
		
		return $query;
	}
	
	function construct_replace($table, $db_data)
	{
		$query = 'REPLACE INTO ';
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		$query .= $table . ' ';
		
		$ins_index = array();
		$ins_data = array();
		
		foreach ($db_data as $k => $v) {
			$ins_index[] = '`' . $k . '`';
			if (!(is_int($v) || is_float($v)))
				$v = '\'' . $this->escape($v) . '\'';
			$ins_data[] = $v;
		}
		
		$query .= '(' . implode(',', $ins_index) . ') VALUES (' . implode(',', $ins_data) . ')';
		
		return $query;
	}
	
	function construct_update_id($table, $id, $db_data, $id_text = 'id', $no_quote = array())
	{
		$query = 'UPDATE ';
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		$query .= $table . ' ';
		
		$upd_values = array();
		foreach ($db_data as $k => $v) {
			if (!(is_int($v) || is_float($v)) && !in_array($k, $no_quote))
				$v = '\'' . $this->escape($v) . '\'';
			$upd_values[] = '`' . $k . '`=' . $v;
		}
		if (!is_numeric($id)) $id = '\'' . $this->escape($id) . '\'';
		$query .= 'SET ' . implode(',', $upd_values) . ' WHERE `' . $id_text . '`=' . $id . ' LIMIT 1';
		
		return $query;
	}
	
	function count($table, $where = false)
	{
		$query = 'SELECT ';
		
		if (strpos($table, '`') === false) {
			# Quote our staff
			$table = '`' . $table . '`';
		}
		
		$query .= 'COUNT(*) AS `cnt` FROM ' . $table;
		
		if ($where)
			$query .= ' WHERE ' . $where;
		
		$res = $this->ldb->query($query);
		
		return intval(@$res[0]['cnt']);
	}
}

?>