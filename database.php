<?php
defined('DBX_SERVER') ? null : define("DBX_SERVER", "localhost");
defined('DBX_USER')   ? null : define("DBX_USER", "root");
defined('DBX_PASS')   ? null : define("DBX_PASS", "");
defined('DBX_NAME')   ? null : define("DBX_NAME", "tubapp");


class MySQLDatabase {
	
	private $connection;
	public $last_query;
	private $magic_quotes_active;
	private $real_escape_string_exists;
	public $filename;
	public $table;
	public $img_field;
	public $where;
	private $temp_path;
	protected $upload_dir="../content/ann";
	public $errors=array();
  
  protected $upload_errors = array(
		// http://www.php.net/manual/en/features.file-upload.errors.php
		UPLOAD_ERR_OK 				=> "No errors.",
		UPLOAD_ERR_INI_SIZE  	=> "Larger than upload_max_filesize.",
	  UPLOAD_ERR_FORM_SIZE 	=> "Larger than form MAX_FILE_SIZE.",
	  UPLOAD_ERR_PARTIAL 		=> "Partial upload.",
	  UPLOAD_ERR_NO_FILE 		=> "No file.",
	  UPLOAD_ERR_NO_TMP_DIR => "No temporary directory.",
	  UPLOAD_ERR_CANT_WRITE => "Can't write to disk.",
	  UPLOAD_ERR_EXTENSION 	=> "File upload stopped by extension."
	);
	
  function __construct() {
    $this->open_connection();
		$this->magic_quotes_active = get_magic_quotes_gpc();
		$this->real_escape_string_exists = function_exists( "mysql_real_escape_string" );
  }

	public function open_connection() {
		$this->connection = mysql_connect(DBX_SERVER, DBX_USER, DBX_PASS);
		if (!$this->connection) {
			die("Database connection failed: " . mysql_error());
		} else {
			$db_select = mysql_select_db(DBX_NAME, $this->connection);
			mysql_query(" set char 'utf8' ");
			mysql_query("set names 'utf8'");
			if (!$db_select) {
				die("Database selection failed: " . mysql_error());
			}
		}
	}

	public function close_connection() {
		if(isset($this->connection)) {
			mysql_close($this->connection);
			unset($this->connection);
		}
	}

	public function query($sql) {
		$this->last_query = $sql;
		$result = mysql_query($sql, $this->connection);
		$this->confirm_query($result);
		return $result;
	}
	
	public function escape_value( $value ) {
		if( $this->real_escape_string_exists ) { // PHP v4.3.0 or higher
			// undo any magic quote effects so mysql_real_escape_string can do the work
			if( $this->magic_quotes_active ) { $value = stripslashes( $value ); }
			$value = mysql_real_escape_string( $value );
		} else { // before PHP v4.3.0
			// if magic quotes aren't already on then add slashes manually
			if( !$this->magic_quotes_active ) { $value = addslashes( $value ); }
			// if magic quotes are active, then the slashes already exist
		}
		return $value;
	}
	//"database CRUD"
	public function insert_into($table, $vars){
		$insert_keys = "";
		$insert_values = "";
		$insert_query = 'insert into '.$table.' (';
		foreach($vars as $key => $value){
			$insert_keys .= $key.", ";
			$insert_values .= "'".$this->escape_value($value)."', ";
			}
		$k = rtrim($insert_keys, ', ');
		$v = rtrim($insert_values, ', ');
		$k = strtolower($k);
		//$v = strtolower($v);
		$insert_query .= $k.')'.' values ('.$v.')';
		//for development mode purposes//echo $insert_query;
		$this -> query($insert_query);
		}
	//to select multi rows
	public function select_all($query){
		$i = 0;
		$data = array();
		$unfetched = $this->query($query);
		while($row = mysql_fetch_assoc($unfetched)){
			$data[$i] = $row;
			$i++;
			}
		//development mode//echo $query;
		return $data;
		}
	//to perform single query/row
	public function select_single($query){
		$data = $this->query($query);
		return $this->fetch_array($data);
		}
	//to update existed table
	public function update($table, $vars, $where){
		$update_query = 'update '.$table.' set ';
		foreach($vars as $key => $value){
			$update_query .= $key .' = "'. $this->escape_value($value).'" , ';
			}
		$u = rtrim($update_query,' , ');
		$u .= ' where '.$where;
		//$u = strtolower($u);
		//development mode//echo $u;
		$this -> query($u);
		}
	public function delete($from, $where){
		$delete_query = 'delete from '.$from.' where '.$where.'';
		$this->query($delete_query);
		}
	public function delete_photo($table, $image_field, $where){
		$image = mysql_fetch_array($this->query(' select '.$image_field.' from '.$table.' where '.$where.''));
		if(file_exists($this->upload_dir.'/'.$image[$image_field]))unlink($this->upload_dir.'/'.$image[$image_field]);
		//$delete_query = 'delete from '.$from.' where '.$where.'';
		//$this->query($delete_query);
		}
	//image uploading process	
		
			// Pass in $_FILE(['uploaded_file']) as an argument
	public function attach_file($file) {
		// Perform error checking on the form parameters
		if(!$file || empty($file) || !is_array($file)) {
		  // error: nothing uploaded or wrong argument usage
		  $this->errors[] = "No file was uploaded.";
		  return false;
		} elseif($file['error'] != 0) {
		  // error: report what PHP says went wrong
		  $this->errors[] = $this->upload_errors[$file['error']];
		  return false;
		} else {
			// Set object attributes to the form parameters.
		  $this->temp_path  = $file['tmp_name'];
		  $this->filename   = rand(0,121551147).'-'.basename($file['name']);
			// Don't worry about saving anything to the database yet.
			return true;

		}
	}
  
	public function save() {
			// Make sure there are no errors
			
			// Can't save if there are pre-existing errors
		  if(!empty($this->errors)) { return false; }
		
		  // Can't save without filename and temp location
		  if(empty($this->filename) || empty($this->temp_path)) {
		    $this->errors[] = "The file location was not available.";
		    return false;
		  }
			
			// Determine the target_path
		  $target_path = $this->upload_dir .'/'. $this->filename;
		  
		  // Make sure a file doesn't already exist in the target location
		  if(file_exists($target_path)) {
		    $this->errors[] = "The file {$this->filename} already exists.";
		    return false;
		  }
		
			// Attempt to move the file 
			if(move_uploaded_file($this->temp_path, $target_path)) {
		  	// Success
				// Save a corresponding entry to the database
				return $this->filename;
				}
			else {
				// File was not moved.
		    $this->errors[] = "The file upload failed, possibly due to incorrect permissions on the upload folder.";
		    return false;
			}
	}
	
	// "database-neutral" methods
  public function fetch_array($result_set) {
    return mysql_fetch_array($result_set);
  }
  
  public function num_rows($result_set) {
   return mysql_num_rows($result_set);
  }
  public function count_all($table){
	  $count = $this->query("select count(*) from ".$table);
	  $all = $this->fetch_array($count);
	  return $all[0];
	  }
  public function insert_id() {
    // get the last id inserted over the current db connection
    return mysql_insert_id($this->connection);
  }
  
  public function affected_rows() {
    return mysql_affected_rows($this->connection);
  }

	private function confirm_query($result) {
		if (!$result) {
	    $output = "Database query failed: " . mysql_error() . "<br /><br />";
	    //$output .= "Last SQL query: " . $this->last_query;
	    die( $output );
		}
	}
	
	public function set_session($admin){
		session_start();
		$_SESSION['username'] = $admin['admin_username'];
		}
	
}

$database = new MySQLDatabase();
$db =& $database;

?>
