<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/* Bulk SQL Upsert

This class is used to build a PDO SQL Query that is intended to 'UPSERT' by attempting to insert and handling
Duplicate Key to Update.  This is useful for high volumes of data.  Its less code for you, which reduces chance
of errors or mismatched fields.

NOTE: Since this will Update, it will also SOFT DELETE / SOFT RESTORE objects where deleted_at is updated.

This class uses PDO against SQL Injection Attacks.  It generates named and numbered bindings for you.  PDO
has a limit of 65000 bindings, which wont affect you.  If more than 65k bindings occur, this script will
automagically handle breaking these up into smaller queries for you.

Create a New BulkQueryGenerator.  It expects a table name, a string or array of fields (id, name, deleted_at, etc), and an array
of objects with properties that correspond to fields.  Either 'tpv.products' or just 'rates' is fine.

The 'Fields' corresponds to table columns in the database, and the list of fields can be generated dynamically.

$peeps = [];
$peeps[1] = new Person(1, 'Bob');
$peeps[2] = new Person(1, 'Tom');

$fields = 'id, name';  alternately $fields = ['id','name'];
$bulk_query = new BulkQueryGenerator('friends', $fields, $peeps);
$bulk_query->save();

Query will look something like:

INSERT INTO friends (id, name)
VALUES
    (:id1, :name1),
    (:id2, :name2)
ON DUPLICATE KEY UPDATE
	id = VALUES(id),
	name = VALUES(name)

$bulk_query->save() has some built in options:

$bulk_query->save(true)       - will log your query with PDO strings (:id1, :name1)
$bulk_query->save('bindings') - will also log the bindings for values
$bulk_query->save('noPDO')    - will try to replace PDO strings with the values, but probably is not appropriate for copying and pasting into MySQL Workbench

You can Prepend strings like this:

$bulk_query->save(true, ' ### this starts the logfile line');

If you want to check data, there are several public functions

$bulk_query->getQueryNonPDO()
$bulk_query->getFields()
$bulk_query->getTable()
$bulk_query->getQuery()
$bulk_query->getBindings()

In the event you have multiple queries, getQuery returns a query that is not broken into smaller queries, and getBindings returns
all bindings.  When there are multiple queries, these are not run against the database, only the smaller chunks are.  If you need
to see a specific child query, use getQuery(1) or getBindings(1) to return query or bindings that will run against the DB.

*/

class BulkSqlUpsert {
    private $fields;
    private $fields_array;
    private $table;
    private $bindings;
    private $objects;
    private $query;
    private $queries;
    private $bindings_array;
    private $queryNonPDO;
    private $chunks;
    private $binding_data;
    private $log;
    
    public function __construct(string $table, $fields, array $objects, $log = false, $chunks = 60000){
        $this->table = $table;
        $this->log = $log;
        $this->setupFieldsArray($fields);
        $this->queries = [];
        $this->bindings = [];
        $this->bindings_array = [];
        // Creates a new array from $objects array.  Array Keys for $this->objects starts at 0.  $objects param is not modified
        $this->objects = array_values($objects); 
        // Default 60000 to stay reasonably well within PDO limit of 65000 bindings
        $this->chunks = $chunks; 

        if ($this->chunks > 65000){
            throw new \Exception('Fatal Error on BulkSqlUpsert: \$chunks $chunks will exceed PDO Limit of 65000.  Execution Halted.');
            die();
        }

        // Generate the SQL query string and PDO Bindings for the query
        $this->generateQuery();
    }

    /** save() Optional Params
     * NOTE: noPDO Option may not be suitable to copy and paste to MySQL Workbench as it sets NULL and INTS to quoted strings
     * @param $log string true, 'bindings' or 'noPDO'
     * @param $log_prepend string: A string that may help you find the query, like '### myQuery ' rest of query
     * @param $multi_query array: DO NOT USE, internal
     */
    public function save($log = false, string $log_prepend = null, $multi_query = null){
        $this->log = $log;

        // If there are too many bindings, then call saveMultiple and stop running this function.
        // saveMultiple will call this method several times for each of the smaller queries with bindings within PDOs binding limit ($this->chunks)
        if ($multi_query === null && count($this->bindings) > $this->chunks){
            $this->saveMultiple($log, $log_prepend);
            return;
        }

        $query = ($multi_query) ? $multi_query['query'] : $this->query;
        $bindings = ($multi_query) ? $multi_query['bindings'] : $this->bindings;
        $i = ($multi_query) ? $multi_query['i'] : null;

        if ($log){
            if      ($log === 'bindings')  Log::info("$log_prepend \$query$i:\n\n" . $query . "\n\nBindings:\n" . print_r($bindings, true));
            else if ($log === 'noPDO')     Log::info("$log_prepend \$ Non PDO query$i:\n\n" . $this->getQueryNonPDO($i));
            else                           Log::info("$log_prepend \$query$i:\n\n$query");
        }

        $benchmark_start = $this->getMicrotime();

        // Run the query and insert new items or update existing items
        $affected_rows = DB::update($query, $bindings);

        $benchmark_end = $this->getMicrotime();
        $benchmark_total = $benchmark_end - $benchmark_start;

        if ($log){
            Log::info("$log_prepend affected rows: $affected_rows in $benchmark_total seconds");
        }

        echo "\n$log_prepend affected rows: $affected_rows in $benchmark_total seconds\n";
    }

    private function saveMultiple($log, $log_prepend){
        // if saveMultiple is called, then smaller queries have already been generated, just run each of those queries
        for ($i = 0, $max = count($this->queries); $i < $max; $i++){
            $query_and_bindings = ['query' => $this->queries[$i], 'bindings' => $this->bindings_array[$i], 'i' => $i];
            $this->save($log, $log_prepend, $query_and_bindings);
        }
    }

    // This handles trying to use an instance of this class where typeof string is expected, returns value $this->query (string)
    // public function someFunc(string $myString) ... someFunc($bulk_query) uses the $query here
    public function __toString(){
        return $this->query;
    }

    // This handles trying to use an instance of this class where typeof array is expected, returns value $this->bindings
    // public function someFunc(array $myArray) ... someFunc($bulk_query) uses the $bindings here
    public function __toArray(){
        return $this->bindings;
    }

    // Returns a SQL Query string with values like `name = 'Bob'` instead of PDO Bindings like `:firstName1`
    /** getQueryNonPDO()
     * @param @i int or null - DO NOT USE Internal recursive iterator
     */
    public function getQueryNonPDO($i = null){
        if ($this->queryNonPDO) return $this->queryNonPDO;

        $pattern = [];
        $replace = [];
        $query = ($i !== null) ? $this->queries[$i] : $this->query;
        $bindings = ($i !== null) ? $this->bindings_array[$i] : $this->bindings;

        foreach ($bindings as $k => $v){
            array_push($pattern, $k);
            array_push($replace, ($v === null ? 'NULL' : "'$v'"));
        }

        return $this->queryNonPDO = str_replace($pattern, $replace, $query);
    }

    // Returns string for fields in query.  Example: "id, name, email, address, phone" without the quotes.  Does not contian parens () in this string
    public function getFields(){
        return $this->fields;
    }

    // MySQL table you specified when instance created.  Example 'tpv.rates'
    public function getTable(){
        return $this->table;
    }

    // @param $i int Optional - use if query hit PDO 65k limit and you need to see a specific query
    public function getQuery($i = null){
        if ($i !== null){
            if (!array_key_exists($i, $this->bindings_array)){
                return ["Warning: getQuery $i query does not exist in \$this->queries array"];
            }
            return $this->bindings_array[$i];
        }

        return $this->query;
    }

    // @param $i int Optional - use if query hit PDO 65k limit and you need to see a specific set of bindings
    public function getBindings($i = null){
        if ($i !== null){
            if (!array_key_exists($i, $this->bindings_array)){
                return ["Warning: getBindings $i array does not exist in \$this->bindings_array"];
            }
            return $this->bindings_array[$i];
        }

        return $this->bindings;
    }

    /*

    KEEP THIS COMMENTED OUT BLOCK

    THIS FUNCTION IS INTENDED TO HANDLE INSERTING BULK DATA TO THE AUDITS TABLE

    IT IS NOT COMPLETE

    The version here needs to be rewritten to handle PDO Limits and actually use PDO stuff.  When it was originally written it was
    only handling numeric values

    // This is a Method Call
    $this->insertIntoAudits($this->newPhoneNumbersToSave,    // $auditable_array
    'BulkSqlInsert',                 // $event
    'id',                            // $auditable_id_name, 'cl_id' is CustomerList ID, aliased in Select due to join
    'App/Models/PhoneNumber',        // $auditable_type
    'cronjob',                       // $url
    null,                            // $old_value_field_name, no old value because BulkSqlInsert has no old value
    'phone_number');                 // $new_value_field_name    

    // This is the function declaration

    private function insertIntoAudits(Array  $auditable_array, 
                                        string $event, 
                                        string $auditable_id_name,
                                        string $auditable_type,
                                        string $url,
                                        ?string $old_value_field_name,
                                        ?string $new_value_field_name) : Void {
        // Double Assignment, $base_query doesnt change but $query does change but it is modified each iteration
        $base_query = $query = "INSERT INTO tpv.audits (id, created_at, event, auditable_id, auditable_type, url, old_values, new_values)\n\tVALUES";
        // $i and $count are checked against $this->pdo_chunk_limit so we know how many rows we can add before we need to run the query
        $i = 0;
        $count = count($auditable_array);

        // Build and run a SQL Query String.  If we hit the PDO Limit then run the query on this iteration and reset the query
        foreach ($auditable_array as $row) {
            // Nullable parameter - If field is null value is null, otherwise get the value from the row either object or string|int|etc
            $old_value = ($old_value_field_name === null) ? null : (is_object($row) ? $row[$old_value_field_name] : $row);
            // Take the old values and convert them to JSON
            $old_value_json = $old_value_field_name !== null ? json_encode([ $old_value_field_name => $old_value]) : null;
            // Nullable parameter - If field is null value is null, otherwise get the value from the row either object or string|int|etc
            $new_value = ($new_value_field_name === null) ? null : (is_object($row) ? $row[$new_value_field_name] : $row);
            // Take the new values and convert them to JSON
            $new_value_json = $new_value_field_name !== null ? json_encode([ $new_value_field_name => $new_value]) : null;            

            // $row can be either a single string value or an object
            $auditable_id = (is_object($row) ? $row[$auditable_id_name] : $row);

            // Append query string
            $query .= "\n("
            . "'" . $this->NewGuid() . "',"   // id
            . "'$this->startTime',"           // created_at
            . "'$event',"                     // event column
            . "'$auditable_id',"              // auditable_id
            . "'$auditable_type',"            // auditable_type
            . "'$url',"                       // url
            . "'$old_value_json',"            // old_values
            . "'$new_value_json'),";          // new_values

            // Increment iteration counter
            $i++;

            // If the remainder % of the current iteration is 0 then we are at the PDO LIMIT () so run the query
            // Example: 100 % 100 results in 0, but 99 % 100 results in 1
            // or, if the iteration counter is last element of the array
            if ($i % $this->pdo_chunk_limit == 0 || $i == $count){
                // Cleanup the trailing comma, and run the query
                $query = rtrim($query, ',') . "\n\n";
                DB::insert($query);

                // Reset the Query String to only the base query so we have a fresh string
                $query = $base_query;
            }            

        }
    } 
    */   

    //====  PRIVATE METHODS  ====
    // These are not intended to be called externally

    private function generateQuery(){
        if ($this->log){ Log::info("BulkSqlUpsert is beginning query generation..."); }

        $query = "INSERT INTO $this->table ($this->fields)\nVALUES";

        $count = 1; // This is used to generate PDO Parameter Names, such as :myCol4
        foreach($this->objects as $object){
            if ($count % 100 === 0){
                if ($this->log){ Log::info("BulkSqlUpsert generateQuery iterating objects at a count of $count"); }
                echo "BulkSqlUpsert generateQuery iterating objects at a count of $count\n";
            }

            $query .= $this->getQueryValueRowString($object, $count);
            $count++;
        }

        // getQueryValueRowString always returns a comma at the end.  Trim off the last comma for proper SQL syntax.
        $query = rtrim($query, ',');

        $query .= "\nON DUPLICATE KEY UPDATE";

        $i = 0;
        $count_fields = count($this->fields_array);
        foreach ($this->fields_array as $field){

            if ($this->log && $i % 100 === 0){ 
                Log::info("BulkSqlUpsert generateQuery iterating fields at a count of $i"); 
                echo "BulkSqlUpsert generateQuery iterating fields at a count of $i\n";

            }

            $query .= "\n\t$field = VALUES($field)";
            $query .= ($count_fields - 1 != $i) ? ',' : '';
            $i++;
        }

        $this->query = $query;

        if ($this->log){ Log::info("BulkSqlUpsert count of bindings: " . count($this->bindings) . ", Defined Chunk Limit: " . $this->chunks); }

        // PDO has a limit of 65000 on the total number of bindings that can be processed, default to 60000
        if (count($this->bindings) > $this->chunks){
            if ($this->log){ Log::info("BulkSqlUpsert has found the number of PDO Bindings exceeds the chunk limit of " .
                    $this->chunks . ", generating Multiple Queries to handle PDO Binding Chunk Limit");

                echo "BulkSqlUpsert has found the number of PDO Bindings exceeds the chunk limit of " .
                    $this->chunks . ", generating Multiple Queries to handle PDO Binding Chunk Limit";
            }

            $this->generateMultiQuery();
        }
    }

    // Produces a string "(':id1', ':name1')," with trailing comma, or NULL if we would hit PDO 65k limit
    private function getQueryValueRowString($obj, int $n){
        $result = "\n\t(";
        $bindings = [];

        $i = 0;
        $count_fields = count($this->fields_array);

        foreach($this->fields_array as $field){
            $param_name = ":$field" . $n;
            $result .= $param_name;
            $result .= ($count_fields - 1 != $i) ? "," : "),";
            $bindings[$param_name] = $obj->$field;

            $i++;
        }

        if ($this->binding_data != null){
            if ($this->binding_data->i + $i > $this->chunks){
                return null;
            }

            $this->binding_data->i += $i;
            $this->binding_data->total += $i;

            if (!array_key_exists($this->binding_data->query_i, $this->bindings_array)){
                $this->bindings_array[$this->binding_data->query_i] = [];                
            }

            // This is slow with large datasets, DONT USE            
            //$this->bindings_array[$this->binding_data->query_i] = $this->bulkArrayMerge($this->bindings_array[$this->binding_data->query_i], $bindings);

            foreach ($bindings as $bv){
                $this->bindings_array[$this->binding_data->query_i][] = $bv;
            }
        } else {
            // This is slow with large datasets, DONT USE
            //$this->bindings = $this->bulkArrayMerge($this->bindings, $bindings); 

            foreach ($bindings as $bv){
                $this->bindings[] = $bv;
            }
        }

        // returns a string "(:my_id1, :my_id2, :my_id3)," with a trailing comma
        return $result;
    }

    /** bulkArrayMerge(array $arr1, array $arr2)
     * PHP array_merge is terribly slow because it copies the array arguments.  This uses pointers so no copying.
     * MASSIVE performance gain.  On one test import, time reduced from 1.5 hours to 3 minutes.
     * NOTE: DESTRUCTIVE - Original array is modified, but this is also where the performance gain occurs
     * Returns modified $array1 so although $array1 is already modified, it is returned also, which allows it to be set to another variable
     */

    private function bulkArrayMerge(array &$array1, array &$array2) {
        foreach($array2 as $v) { $array1[] = $v; }
        return $array1;
    }    

    private function generateMultiQuery(){
        $run = true;
        $n = 1;
        // Binding Data just retains a set of counters.  query_i only incremented when new query is added. i is reset each new query, total not reset
        $this->binding_data = new \StdClass();        
        $this->binding_data->query_i = 0;
        $this->binding_data->total = 0;

        while ($run){
            $this->binding_data->i = 0;
            $this->generateSingleQuery($run, $n);
            $this->binding_data->query_i++;
        }
    }
    
    private function generateSingleQuery(&$run, &$n){
        $query = "INSERT INTO $this->table ($this->fields)\nVALUES";
        $query_has_values = false;
        
        for ($i = $n; true; $i++){

            
            if ($n % 100 === 0){
                if ($this->log){ Log::info("BulkSqlUpsert generateSingleQuery $i"); }
                echo "BulkSqlUpsert generateSingleQuery $i\n";
            }

            // If we reached the end of the objects array, set referenced $run to false so no more queries are generated, and break from loop
            if (!array_key_exists($i, $this->objects)){
                $run = false;
                break;
            }

            $object = $this->objects[$i];
            $result = $this->getQueryValueRowString($object, $n);

            // If we hit a PDO limit, result will be null intentionally
            if ($result === null){ break; }

            $query .= $result;
            $n++;
            $query_has_values = true;
        }

        // If the query has not had any values added, then prevent further execution
        if (!$query_has_values){
            return;
        }

        $query = rtrim($query, ',');

        $query .= "\nON DUPLICATE KEY UPDATE";

        $i = 0;
        $count_fields = count($this->fields_array);
        foreach ($this->fields_array as $field){
            $query .= "\n\t$field = VALUES($field)";
            $query .= ($count_fields - 1 != $i) ? ',' : '';
            $i++;
        }

        $this->queries[$this->binding_data->query_i] = $query;
    }

    // Internal, sets up fields and fields_array with trimmed string values
    private function setupFieldsArray($fields){
        $type = gettype($fields);

        if ($type == 'string'){
            $this->fields_array = explode(',', $fields);
            $this->fields_array = array_map('trim', $this->fields_array);
            $this->fields = implode(', ', $this->fields_array);
        }
        else if ($type == 'array'){
            $this->fields_array = array_map('trim', $fields);
            $this->fields = implode(', ', $fields);
        }
        else {
            throw new \Exception("Error on BulkQueryGenerator - \$fields must be either a String or Array");
        }
    }

    private function getMicrotime(){
        // Floats need to be rounded
        return round(microtime(1) * 1000) / 1000;
    }
}
