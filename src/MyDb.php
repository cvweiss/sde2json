<?php

namespace cvweiss\sde;

use \PDO;
use \Project\Base\Config;

/**
	Stolen from cvweiss/zlibrary :)
*/
class MyDb
{
	/**
	 * @var int Stores the number of Query executions and inserts
	 */
	protected static $queryCount = 0;

	/**
	 * Creates and returns a PDO object.
	 *
	 * @static
	 * @return PDO
	 */
	protected static function getPDO()
	{
		$dbUser = Config::get('mysql_user', 'user');
		$dbPass = Config::get('mysql_password', 'password');
		$dbHost = Config::get('mysql_host', '127.0.0.1');
		$dbName = Config::get('mysql_database', 'database');
		$dbSock = Config::get('mysql_socket');

		if($dbSock)
			$dsn = "mysql:dbname=$dbName;unix_socket=$dbSock";
		else
			$dsn = "mysql:dbname=$dbName;host=$dbHost";

		try
		{
			$pdo = new PDO($dsn, $dbUser, $dbPass, array(
				PDO::ATTR_PERSISTENT => true, // Keep the connection open, so it can be reused
				PDO::ATTR_EMULATE_PREPARES => true, // Use native prepares, since they and the execution plan is cached in MySQL, and thus generate faster queries, but more garbled errors if we make any.
				PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Used buffered queries
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Error mode
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET time_zone = \'+00:00\'' // Default to using UTC as timezone for all queries.. Since EVE is UTC, so should we be!
				)
			);
		}
		catch (Exception $e)
		{
			Log::log("Unable to connect to the database: " . $e->getMessage());
			throw new Exception("Unable to connect to database!");
		}

		return $pdo;
	}

	/**
	 * Executes an SQL query, returns the full result
	 *
	 * @static
	 * @param string $query The query to be executed.
	 * @param array $parameters (optional) A key/value array of parameters.
	 * @param int $cacheTime The time, in seconds, to cache the result of the query.	Default: 30
	 * @param bool selectCheck If true, does a strict check that the query is using a select.  Default: true
	 * @return array Returns the full resultset as an array.
	 */
	public static function query($query, $parameters = array(), $cacheTime = 30, $selectCheck = true)
	{
		// Sanity check
		if(strpos($query, ";") !== false)
			throw new Exception("Semicolons are not allowed in queries. Use parameters instead.");

		// Disallow update, insert etc. with this, they have to use execute
		if ($selectCheck && strpos(trim(strtolower($query)), "select") !== 0) 
			throw new Exception("You are not to use Db::query with update or insert queries. Use Db::execute for that");

		// Cache time of 0 seconds means skip all caches. and just do the query
		$key = self::getKey($query, $parameters);

		// If cache time is above 0 seconds, lets try and get it from that.
		if($cacheTime > 0)
		{
			// Try the cache system
			$result = null; //Cache::get($key);
		}

		try
		{
			// Start the timer
			//$timer = new Timer();
			// Increment the queryCounter
			self::$queryCount++;
			// Open the databse connection
			$pdo = self::getPDO();
			// Make sure PDO is set
			if($pdo == NULL)
				return;
			// Prepare the query
			$stmt = $pdo->prepare($query);
			// Execute the query, with the parameters
			$stmt->execute($parameters);

			// Check for errors
			if($stmt->errorCode() != 0)
				self::processError($stmt, $query, $parameters);

			// Fetch an associative array
			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			// Close the cursor
			$stmt->closeCursor();

			// Stop the timer
			//$duration = $timer->stop();

			// If cache time is above 0 seconds, lets store it in the cache.
			//if($cacheTime > 0) Cache::set($key, $result, min(3600, $cacheTime)); // Store it in the cache system

			//self::log($query, $parameters, $duration);

			// now to return the result
			return $result;
		}
		catch (Exception $e)
		{
			// There was some sort of nasty nasty nasty error..
			throw $e;
		}
	}

	/**
	 * Executes an SQL query, and returns a single row
	 *
	 * @static
	 * @param string $query The query to be executed
	 * @param array $parameters (optional) A key/value array of parameters
	 * @param int $cacheTime The time, in seconds, to cache the result of the query.	Default: 30
	 * @return array Returns the first row of the result set. Returns an empty array if there are no rows.
	 */
	public static function queryRow($query, $parameters = array(), $cacheTime = 30, $selectCheck = true)
	{
		// Get the result
		$result = self::query($query, $parameters, $cacheTime, $selectCheck);
		// Figure out if it has more than one result and return it
		if(sizeof($result) >= 1)
			return $result[0];

		// No results at all
		return array();
	}

	/**
	 * Executes an SQL query, and returns a single result
	 *
	 * @static
	 * @param string $query The query to be executed
	 * @param string $field The name of the field to return
	 * @param array $parameters (optional) A key/value array of parameters
	 * @param int $cacheTime The time, in seconds, to cache the result of the query.	Default: 30
	 * @return mixed Returns the value of $field in the first row of the resultset. Returns null if there are no results
	 */
	public static function queryField($query, $field, $parameters = array(), $cacheTime = 30, $selectCheck = true)
	{
		// Get the result
		$result = self::query($query, $parameters, $cacheTime, $selectCheck);
		// Figure out if it has no results
		if(sizeof($result) == 0)
			return null;

		// Bind the first result to $resultRow
		$resultRow = $result[0];

		// Return the result + the field requested
		return $resultRow[$field];
	}

	/**
	 * Executes an SQL command and returns the number of rows affected.
	 * Good for inserts, updates, deletes, etc.
	 *
	 * @static
	 * @param string $query The query to be executed.
	 * @param array $parameters (optional) A key/value array of parameters.
	 * @param boolean $reportErrors Log the query and throw an exception if the query fails. Default: true
	 * @return int The number of rows affected by the sql query.
	 */
	public static function execute($query, $parameters = array(), $reportErrors = true, $returnID = false)
	{
		self::validateQuery($query);

		// Start the timer
		$timer = new Timer();
		// Increment the queryCounter
		self::$queryCount++;
		// Open the databse connection
		$pdo = self::getPDO();

		// Begin the transaction
		$pdo->beginTransaction();
		// Prepare the query
		$stmt = $pdo->prepare($query);
		// Execute the query, with the parameters
		$stmt->execute($parameters);

		// An error happened
		if($stmt->errorCode() != 0)
		{
			// Report the error
			self::processError($stmt, $query, $parameters, $reportErrors);
			// Rollback the query
			$pdo->rollBack();
			// Return false
			return false;
		}

		// return the last inserted id
		$lastInsertID = $returnID ? $pdo->lastInsertId() : 0;

		// No error, time to commit
		$pdo->commit();
		// Stop the timer
		$duration = $timer->stop();

		self::log($query, $parameters, $duration);

		// Get the amount of rows that was altered
		$rowCount = $stmt->rowCount();
		// Close the cursor
		$stmt->closeCursor();

		if($returnID) return $lastInsertID;

		// Return the amount of rows that was altered
		return $rowCount;
	}

	/**
	 * Validates a query to ensure it contains no semicolons
	 *
	 * @static
	 * @param string $query The query to be executed.
	 * @return void
	*/
	private static function validateQuery($query)
	{
		if(strpos($query, ";") !== false) throw new Exception("Semicolons are not allowed in queryes. Use parameters instead.");
	}

	/**
	 * Retrieve the number of queries executed so far.
	 *
	 * @static
	 * @return int Number of queries executed so far
	 */
	public static function getQueryCount()
	{
		return self::$queryCount;
	}

	/**
	 * @static
	 * @throws Exception
	 * @param	PDOStatement $statement
	 * @param	string $query
	 * @param	array $parameters
	 @ @param	bool  $reportErrors
	 * @return void
	 */
	public static function processError($statement, $query, $parameters = array(), $reportErrors = true)
	{
		if ($reportErrors == false) return;
		$errorCode = $statement->errorCode();
		$errorInfo = $statement->errorInfo();
		self::log("$errorCode - " . $errorInfo[2] . "\n$query", $parameters, 1000);
		throw new Exception($errorInfo[0] . " - " . $errorInfo[1] . " - " . $errorInfo[2]);
	}

	/**
	 * Logs a query, its parameters, and the amount of time it took to execute.
	 * The original query is modified through simple search and replace to create
	 * the query as close to the execution as PDO would have the query.	This
	 * logging function doesn't take any care to escape any parameters, so take
	 * caution if you attempt to execute any logged queries.
	 *
	 * @param string $query The query.
	 * @param array $parameters A key/value array of parameters
	 * @param int $duration The length of time it took for the query to execute.
	 * @return void
	 */
	public static function log($query, $parameters = array(), $duration = 0)
	{
		if ($duration < 5000) return; // Don't log short queries
		global $baseAddr;
		foreach ($parameters as $k => $v) {
			$query = str_replace($k, "'" . $v . "'", $query);
		}
		$uri = isset($_SERVER["REQUEST_URI"]) ? "Query page: https://$baseAddr" . $_SERVER["REQUEST_URI"] . "\n": "";
		Log::log(($duration != 0 ? number_format($duration / 1000, 3) . "s " : "") . " Query: \n$query;\n$uri");
	}

	/**
	 * @static
	 * @param string $query The query.
	 * @param array $parameters The parameters
	 * @return string The query and parameters as a hashed value.
	 */
	public static function getKey($query, $parameters = array())
	{
		foreach($parameters as $key => $value)
			$query .= "|$key|$value";

		return "Db:" . md5($query);
	}
}
