<?php
	session_start();
	error_reporting(1);
    

    $filename = basename(__FILE__);

	class HistoryRecord{
		private $size;
		private $min_size = 1;
		private $max_size = 100;
		private $data;
		private $data_length;
		private $data_cursor;
		private $is_recycled = false;

		public function __construct($size){
			$this->set_size($size);
			$this->data =  array_fill(0, $this->get_size(), "");
			$this->data_cursor = 0;
		}

		public function get_size(){
			return $this->size;
		}

		public function set_size($size){
			if ($this->invalid_size($size)){
				$this->size = $size;
			}else{
				if ($this->get_size() === NULL){
					$this->size = $this->max_size;
				}
			}
		}

		public function invalid_size($size){
			return $size >= $this->min_size && $size <= $this->max_size;
		}

		public function get_data(){
			return $this->data;
		}

		public function insert_data($data){
			$this->data[$this->get_data_cursor()] = $data;

			$this->inc_data_cursor();
			if ($this->get_data_cursor() === 0 && !$this->is_recycled){
				$this->is_recycled = true;
			}
		}

		public function inc_data_cursor(){
			$this->data_cursor = ($this->data_cursor + 1) % $this->get_size();
		}

		public function get_data_cursor(){
			return $this->data_cursor;
		}

		public function show_history(){
			$content = "";
			if (!$this->is_recycled){
				for ($index=0; $index < $this->get_data_cursor(); $index=$index+1){
					$content .= "$index: ".$this->get_data()[$index];
				}
			}else{
				$cur = $this->get_data_cursor();
				$end = ($cur - 1) % $this->get_size();
				while ($cur !== $end){
					$content .= (($cur - $this->get_data_cursor() + $this->get_size()) % $this->get_size()).": ".$this->data[$cur]. "<br />";
					$cur = ($cur + 1) % $this->get_size();
				}
				$content .= (($cur - $this->get_data_cursor() + $this->get_size()) % $this->get_size()).": ".$this->data[$cur];
			}
			return $content;
		}
	}

	class DBObject{
		private $host;
		private $port;
		private $dbname;
		private $username;
		private $password;
		private $history;
		private $status;
		private $last_record;

		public function __construct(
			$host, $username, $password, $dbname, $port, $size=10
		){
			
			$this->host = isset($host) && !empty($host)?$host:"localhost";
			$this->port = isset($port) && !empty($port)?$port:"3306";
			$this->dbname = isset($dbname) && !empty($dbname)?$dbname:null;
			$this->username = isset($username) && !empty($username)?$username:"root";
			$this->password = isset($password) && !empty($password)?$password:"root";
			$this->history = new HistoryRecord($size);
			$this->last_record = "";
		}

		public function get_last_record(){
			return $this->last_record;
		}

		public function get_host(){
			return $this->host;
		}

		public function set_host($host){
			$this->host = $host;
		}

		public function get_port(){
			return $this->port;
		}

		public function set_port($port){
			$this->port = $port;
		}

		public function get_dbname(){
			return $this->dbname;
		}

		public function set_dbname($dbname){
			$this->dbname = $dbname;
		}

		public function get_username(){
			return $this->username;
		}

		public function set_username($username){
			$this->username = $username;
		}

		public function get_password(){
			return $this->password;
		}

		public function set_password($password){
			$this->password = $password;
		}

		public function connect(){
			return new mysqli(
				$this->host, 
				$this->username, 
				$this->password,  
				$this->dbname,  
				$this->port
			);
		}

		public function close($conn){
			if (isset($conn) && $conn){
				if ($conn instanceof mysqli) {
					$conn->close();
				}
			}
		}

		public function clear_one_history_record(){

		}

		public function show_history(){
			return $this->history->show_history();
		}

		public function run_command($conn, $sql){
			return $conn->query("$sql;");
		}

		public function show_databases(){
			$content = array();
			$conn = $this->connect();
			$result = $this->run_command($conn, "show databases");
			while ($row = $result->fetch_assoc()) {
		        foreach ($row as $key=>$value){
		        	$content[$value] = array();
		        }
		    }
		    $this->close($conn);
		    return $content;
		}

		public function show_tables($dbname){
			$content = array();
			$content[$dbname] = array();
			$conn = $this->connect();
			$conn->select_db($dbname);
			$result = $this->run_command($conn, "show tables");
			while ($row = $result->fetch_assoc()) {
		        foreach ($row as $key=>$value){
		        	$content[$dbname][$value] = array();
		        }
		    }
		    $this->close($conn);
		    return $content;
		}

		public function show_columns($dbname, $table_name){
			$content = array();
			$content[$dbname] = array();
			$content[$dbname][$table_name] = array();
			$conn = $this->connect();
			$result = $this->run_command($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$dbname}' AND TABLE_NAME = '{$table_name}'");
			while ($row = $result->fetch_assoc()) {
		        foreach ($row as $key=>$value){
		        	$content[$dbname][$table_name][$value] = array();
		        }
		    }
		    $this->close($conn);
		    return $content;
		}

		public function show_datas_columns($dbname, $table_name)
		{
			$content = array();
			$conn = $this->connect();
			$result = $this->run_command($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$dbname}' AND TABLE_NAME = '{$table_name}'");
			while ($row = $result->fetch_assoc()) {
		        foreach ($row as $key=>$value){
		        	array_push($content, $value);
		        }
		    }
		    $this->close($conn);
		    return $content;
		}

		public function show_datas($dbname, $table_name)
		{
			$content = array();
			$conn = $this->connect();
			$result = $this->run_command($conn, "SELECT * from $dbname.$table_name");
			while ($row = $result->fetch_assoc()) {
		        foreach ($row as $key=>$value){
		        	array_push($content, $value);
		        }
		    }
		    $this->close($conn);
		    return $content;
		}

		public function run_one_command($step, $dbname=NULL, $table_name=NULL){
			if ($step === 1){
				return $this->show_databases();
			}elseif ($step === 2){
				return $this->show_tables($dbname);
			}elseif ($step === 3){
				$result = $this->show_columns($dbname, $table_name);
				return $this->show_columns($dbname, $table_name);
			}elseif (intval($step) === 4){
				$columns = $this->show_datas_columns($dbname, $table_name);
				$datas = $this->show_datas($dbname, $table_name);
				$cnt = count($columns);
				$result = array_merge($columns, $datas);
				return array_chunk($result, $cnt);
			}
		}

		public function run_commands($sql){
			$this->last_record = $sql;
			$content = "";
			$log = "";
			$conn = $this->connect();
			//$conn = new mysqli($this->host, $this->username, $this->password,  $this->dbname,  $this->port);
			//echo "$this->host, $this->username, $this->password,  $this->dbname,  $this->port";
			//$conn = new mysqli("127.0.0.1", "root","root", null, "3306");
			//var_dump($conn);
			$cmdline = explode(";", $sql);
			foreach ($cmdline as $cmd_key => $cmd_value) {
				if (isset($cmd_value) && !!$cmd_value){
					$_cmd_value = trim($cmd_value);
					if ($_cmd_value !== ""){
						$this->history->insert_data("$_cmd_value;");
						if (strpos($_cmd_value, "use ") === 0) {
							$dbname = ltrim(substr($_cmd_value, 4));
							if ($conn->select_db($dbname)){
								$this->set_dbname($dbname);
								$log .= "Succeed to Swith to $dbname.\n--------****----------------****----------------****----------------****--------";
							}else{	
								$log .= "Failed to Swith to $dbname.\n--------****----------------****----------------****----------------****--------";
							}
						}else {
							$result = $this->run_command($conn, "$_cmd_value");
							if (!$result){
								$log .= "Failed to run $_cmd_value; due to $conn->error\n--------****----------------****----------------****----------------****--------";
							}else{
								$log .= "Succeed to run $_cmd_value;\n";
		                        while ($row = $result->fetch_assoc()) {
		                            foreach ($row as $key=>$value){
		                                $content .= "$key $value ";
		                                $log .= "$key $value ";
		                            }
		                            $content .= "<br />";
									$log .= "\n";
		                        }
		                        $log .= "--------****----------------****----------------****----------------****--------\n";
		                    }
						}
					}
				}
			}
			$this->close($conn);
			$data = array();
			$data["content"] = $content;
			$data["log"] = $log;
			return $data;
		}
	}

	function check_mysql_connection_statu(){
		// check user whether is signed in or not
        return isset($_SESSION['conn']) && $_SESSION['conn'];
    }

    function init_mysql_variables(){
    	$_SESSION['conn'] = false;
    	$_SESSION["data"] = NULL;
    	$_SESSION["search_data"] = NULL;
    	$_SESSION['search_data_value'] = NULL;
    	$_SESSION['basic'] = new DBObject("localhost", "root", "", NULL, "3306");
    }

    if(!check_mysql_connection_statu()){
        init_mysql_variables();
    }

    function refresh_page($resource){
		header("Location: $resource");
		exit;
	}

	function return_error_mas($status, $message){
		$error = array();
		$error["status"] = $status;
		$error["msg"] = $message;
		return $error;
	}

	function combine_arr($dst, $src){
		if (empty($dst) || !$dst){
			return $src;
		}elseif (empty($src) || !$src){
			return $dst;
		}
		foreach ($src as $key => $value) {
			if (array_key_exists($key, $dst)){
				$dst[$key] = combine_arr($dst[$key], $src[$key]);
			}else{
				$dst[$key] = $value;
			}
		}
		return $dst;
	}

	$type = isset($_GET['type'])?$_GET['type']:"";
    $mod = isset($_GET['mod'])?$_GET['mod']:"";
    $act = isset($_GET['act'])?$_GET['act']:"";

    $execute_result_data = null;

    if (!empty($type) && $type ==="sql"){
        if (!empty($mod) && $mod ==="mysql"){
           	if (!empty($act) && $act === "connection") {
           		if (check_mysql_connection_statu()) {
           			
                }else {
                    $host = isset($_POST["host"])?$_POST["host"]:"";
                    $port = isset($_POST["port"])?$_POST["port"]:"";
                    $dbname = isset($_POST["dbname"])?$_POST["dbname"]:null;
                    $username = isset($_POST["username"])?$_POST["username"]:"";
                    $password = isset($_POST["password"])?$_POST["password"]:"";
                    if (!$host || !$port || !$username){
                    	
                    }else{
                    	$obj = new DBObject($host, $username, $password, $dbname, $port);
                    	$conn = $obj->connect();
                    	if ($conn->connect_error) {
                    		
                        }else{
                        	$_SESSION['conn'] = true;
                        	$_SESSION["search_data"] = $obj->run_one_command(1, NULL, NULL);
                        	$_SESSION['basic'] = $obj;
                        	
						}
						$obj->close($conn);
					}  
				}
				refresh_page($filename);
           	}elseif (!empty($act) && $act === "disconnection") {
                if (check_mysql_connection_statu()) {
                    $_SESSION['conn'] = false; 
                }else{
                	
                }   
                refresh_page($filename);
            }elseif (!empty($act) && $act === "execute") {
				if (!check_mysql_connection_statu()){
					$_SESSION["data"] = NULL;
				}else{
					$cmdline = isset($_POST["cmdline"])?$_POST["cmdline"]:"";
					if (isset($cmdline) && !empty($cmdline)){
						$_SESSION["data"] = $_SESSION['basic']->run_commands($cmdline);
					}else{
						$_SESSION["data"] = NULL;
					}
				}
				refresh_page($filename);
			}elseif (!empty($act) && $act === "search") {
				if (!check_mysql_connection_statu()){
					$_SESSION["search_data"] = NULL;
				}else{
					$cmdline = isset($_POST["search_command"])?intval($_POST["search_command"]):1;
					$dbname = isset($_POST["dbname"])?$_POST["dbname"]:NULL;
					$table_name = isset($_POST["table_name"])?$_POST["table_name"]:NULL;
					if (isset($cmdline) && !empty($cmdline)){
						if ($cmdline >=1 && $cmdline < 4){
							$tmp_data = $_SESSION['basic']->run_one_command($cmdline, $dbname, $table_name);
							$_SESSION['search_data'] = combine_arr($_SESSION['search_data'], $tmp_data);
						}elseif ($cmdline === 4){
							$_SESSION['search_data_value'] = $_SESSION['basic']->run_one_command($cmdline, $dbname, $table_name);
						}
					}
				}
				refresh_page($filename);
			}
       }
   }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Mysql SQLI Connection</title>
    <style>
    	* {
    		box-sizing: border-box;
    	}

    	html, body {
		    height: 100%;
		    margin: 0;
		    padding: 0;
		}

		body{
			position: relative;
		}

        td {
            vertical-align: middle; 
            text-align: center; 
        }

        table#basic_msg_table{
        	border-collapse: collapse;
        }

        table#basic_msg_table th,td {
        	border: 1px solid #808080;
        }

        .tree {
            list-style-type: none;
            margin: 0;
            padding: 0;
        }
        
        .tree ul {
            list-style-type: none;
            margin: 0;
            padding: 0 20px;
            display: none; 
        }

        .tree li {
            margin: 5px 0;
        }

        .tree label {
            cursor: pointer;
        }

        .tree input[type="checkbox"] {
            margin-right: 5px;
        }

        .tree .expanded {
            display: block;
        }
    </style>
</head>
<body>

<div style="width:100%;height: 100%;overflow: hidden;display: flex;border: 10px solid #808080;box-sizing: border-box;background-color: #DCDCDC;">
	<div style="width:70%;height: 100%;float: left; border-right: 10px solid #808080;box-sizing: border-box;position:relative;">
		<?php if(!check_mysql_connection_statu()){ ?>
		<div style="width:70%;height: 100%;background-color: rgba(0, 0, 0, 0.5);position: fixed;z-index: 100;display: block;box-sizing: border-box;" id="overlay">
			<div style="position: absolute;background-color: white; width:540px;height: 360px;top: 50%;left:50%;transform: translate(-50%, -50%);box-sizing: border-box;" id="modal">
				<div style="width: 100%;height: 40px;text-align: right;padding: 10px;box-sizing: border-box;">
					<button id="closeModal" style="background-color: white; border:none;cursor:pointer;font-weight: bolder;font-size: 18px;">X</button>
				</div>
				<h2 style="text-align: center;font-weight: bolder;font-size: 28px;">
					Error
				</h2>
				<div style="width: 80%; height: 80%;position: relative;top: 50%;left:50%;transform: translate(-50%, -50%);box-sizing: border-box;">
					<p>
						You need to login at first!
					</p>
				</div>
				<script type="text/javascript">
					document.getElementById('closeModal').addEventListener('click', function() {
						document.getElementById('modal').style.display = 'none';
					});
				  </script>
			</div>
		</div>
		<?php } ?>

		<div style="width:40%;height: 100%;float: left; border-right: 10px solid #808080;box-sizing: border-box;">
			<div style="width:100%;height:40%; padding:10px;border-bottom: 10px solid #808080; overflow: auto">
				 <?php 
					$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http'; 
					$xhr_host = $_SERVER['SERVER_ADDR'];
					$xhr_port = $_SERVER['SERVER_PORT'];
				?>
				<ul id="tree" class="tree" style="overflow: auto;width:100%;height: 100%;"> 
			    <?php 
				    $html_data = "";
				    foreach($_SESSION["search_data"] as $schemata_key=>$schemata_value){
				        $html_data .= '<li style="width:100%;display: block;margin-bottom:5px;">' .
				                      '<label for="DBS' . $schemata_key . '">' . $schemata_key . '</label>' .
				                      '<input type="checkbox" id="DBS' . $schemata_key . '" class="search_table_li" schema_name="' . $schemata_key . '"/>' .
				                      '<ul style="width:100%;display: block;">';
				        if (is_array($schemata_value) && isset($schemata_value)){
				            foreach($schemata_value as $table_key=>$table_value){
				                $html_data .= '<li>' .
				                              '<label for="TBS' . $table_key . '">' . $table_key . '</label>' .
				                              '<input type="checkbox" id="TBS' . $table_key . '" class="search_column_li" schema_name="' . $schemata_key . '" table_name="' . $table_key . '"/>' .
				                              '<ul style="width:100%;display: block;">';
				        		if (is_array($table_value) && isset($table_value)){
					            	foreach($table_value as $column_key=>$column_value){
							            $html_data .= '<li>' .
							                              '<label for="CLS' . $column_key . '">' . $column_key . '</label>' .
							                              '<input type="checkbox" id="CLS' . $column_key . '" class="search_value_li" schema_name="' . $schemata_key . '" table_name="' . $table_key . '"/>' .
							                           '</li>';
							        }  
						        }
				        		$html_data .= '</ul></li>';
				            }  
				        }
				        $html_data .= '</ul></li>';
				    }
				?>
				</ul>
				<script type="text/javascript">
				    var tree = document.getElementById("tree");
				    window.addEventListener('load', function(){
				        tree.innerHTML = '<?php echo $html_data; ?>';
				        var search_table_lis = document.getElementsByClassName('search_table_li');
						for (var i = 0; i < search_table_lis.length; i++) {
							search_table_lis[i].onclick = function(event) {
			            		event.stopImmediatePropagation();
			     				var xhr = new XMLHttpRequest();
							    xhr.open('POST', "<?php echo "$protocol://$xhr_host:$xhr_port/$filename"; ?>?type=sql&mod=mysql&act=search", true);
						        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
						        xhr.onreadystatechange = function() {
									if (xhr.readyState === XMLHttpRequest.DONE) { 
										if (xhr.status === 200) {  
												
										} else {

										}
									}
								};
								var schema_name = this.getAttribute('schema_name');
								var data = "search_command=2&dbname=" + schema_name + "&table_name=";
								xhr.send(data);
								location.reload();
			            	}
			            }

			            var search_column_lis = document.getElementsByClassName('search_column_li');
						for (var i = 0; i < search_column_lis.length; i++) {
							search_column_lis[i].onclick = function(event) {
			            		event.stopImmediatePropagation();
			     				var xhr = new XMLHttpRequest();
							    xhr.open('POST', "<?php echo "$protocol://$xhr_host:$xhr_port/$filename"; ?>?type=sql&mod=mysql&act=search", true);
						        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
						        xhr.onreadystatechange = function() {
									if (xhr.readyState === XMLHttpRequest.DONE) { 
										if (xhr.status === 200) {  
												
										} else {

										}
									}
								};
								var schema_name = this.getAttribute('schema_name');
								var table_name = this.getAttribute('table_name');
								var data = "search_command=3&dbname=" + schema_name + "&table_name=" + table_name;
								xhr.send(data);
								location.reload();
			            	}
			            }

			            var search_value_lis = document.getElementsByClassName('search_value_li');
						for (var i = 0; i < search_value_lis.length; i++) {
							search_value_lis[i].onclick = function(event) {
			            		event.stopImmediatePropagation();
			     				var xhr = new XMLHttpRequest();
							    xhr.open('POST', "<?php echo "$protocol://$xhr_host:$xhr_port/$filename"; ?>?type=sql&mod=mysql&act=search", true);
						        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
						        xhr.onreadystatechange = function() {
									if (xhr.readyState === XMLHttpRequest.DONE) { 
										if (xhr.status === 200) {  
												
										} else {

										}
									}
								};
								var schema_name = this.getAttribute('schema_name');
								var table_name = this.getAttribute('table_name');
								var data = "search_command=4&dbname=" + schema_name + "&table_name=" + table_name;
								xhr.send(data);
								location.reload();
			            	}
			            }
				    });				
				</script>
			</div>
			<div style="height: 60%; width: 100%;box-sizing: border-box;overflow: auto;">
				<div style="font-size: 22px; font-weight: bolder; color: white; padding: 10px; text-align: center;">SQL Command Execute</div>
				<table style="height: 91%; width: 100%; border-top: 10px solid #808080;border-bottom: 10px solid #808080;overflow: hidden;" >
					
					<tbody style="overflow: auto;">
					<?php foreach ($_SESSION["search_data_value"] as $key => $value) { ?>
						<tr>
							<?php foreach ($value as $item_key => $item_value) { ?>
								<td><?php echo $item_value;?></td>
							<?php }?>
						</tr>
					<?php }?>
				</tbody>
				</table>
			</div>
		</div>
		
		<div style="width:60%;height: 100%;float: left; border-right: 10px solid #808080;box-sizing: border-box;">
			<div style="height: 40%; width: 100%; float: left;box-sizing: border-box;">
				<div style="height:100%;width:100%">
					<form action="./<?php echo $filename;?>?type=sql&mod=mysql&act=execute" method="POST" style="height: 100%; width: 100%;">
	                    <table style="height: 90%; width: 100%; border-top: 10px solid #808080;border-bottom: 10px solid #808080" >
	                    	<caption style="font-size: 22px; font-weight: bolder; color: white; padding: 10px;">SQL Command Execute</caption>
	                    	<tr style="height:70%; width: 100%;">
	                    		<td style="height:100%; width: 100%;">
	                    			<textarea style="height:100%; width: 100%;resize: none;" name="cmdline"><?php echo $_SESSION['basic']->get_last_record();?></textarea> 
	                    		</td>
	                    	</tr>
	                    	<tr style="height:10%; width: 100%;">
	                    		<td style="height:100%; width: 100%;"><input type="submit" value="execute" style="height:100%; width: 100%;" /></td>
	                    	</tr>
	                    </table>
	                </form>
	            </div>
			</div>
			<div style="height: 60%; width: 100%; float: left;;box-sizing: border-box;">
				<table style="height: 100%; width: 100%; border-top: 10px solid #808080;border-bottom: 10px solid #808080" >
	                <caption style="font-size: 22px; font-weight: bolder; color: white; padding: 10px;">Result</caption>
	                <tr style="height:70%; width: 100%;">
	                    <td style="height:100%; width: 100%;">
	                    	<textarea style="height:100%; width: 100%;resize: none;" name="cmdline"><?php echo $_SESSION["data"]["log"];?></textarea> 
	                    </td>
	                </tr>
	            </table>
			</div>
		</div>
	</div>

	<!-- basic message module -->
	<div style="height: 100%; width: 30%; float: left;;box-sizing: border-box;">
		<!-- test login mysql server -->
		<div style="height: 50%; width: 100%; float: left;box-sizing: border-box;">
			<?php if(!check_mysql_connection_statu()){
            ?>
			<div style="height:100%;width:100%">
				<form action="./<?php echo $filename;?>?type=sql&mod=mysql&act=connection" method="POST" style="height: 100%; width: 100%;">
                    <table style="height: 90%; width: 100%; border-top: 10px solid #808080;border-bottom: 10px solid #808080" >
                    	<caption style="font-size: 22px; font-weight: bolder; color: white; padding: 10px;">Mysql Login Page</caption>
	                    <tr>
	                        <td>hostname</td>
	                        <td><input name="host" value="<?php echo $_SESSION['basic']->get_host();?>" /></td>
	                    </tr>
						<tr>
							<td>port number</td>
							<td><input name="port" value="<?php echo $_SESSION['basic']->get_port();?>" /></td>
						</tr>
						<tr>
	                        <td>database name</td>
	                        <td><input name="dbname" value="<?php echo $_SESSION['basic']->get_dbname();?>" /></td>
	                    </tr>
	                    <tr>
	                        <td>username</td>
	                        <td><input name="username" value="<?php echo $_SESSION['basic']->get_username();?>" /></td>
	                    </tr>
	                    <tr>
	                        <td>password</td>
	                        <td><input name="password" value="<?php echo $_SESSION['basic']->get_password();?>" /></td>
	                    </tr>
	                    <tr>
	                    	<td></td>
	                        <td>
	                            <input type="submit" value="submit" />
	                            <input type="reset" value="reset" />
	                        </td>
	                    </tr>
	                </table>
	            </form>
			</div>
			<?php }else { ?>
			<div style="height:100%;width:100%">
				<form action="./<?php echo $filename;?>?type=sql&mod=mysql&act=disconnection" method="POST" style="height: 100%; width: 100%;">
                    <table id="basic_msg_table" style="height: 90%; width: 100%; border-top: 10px solid #808080;border-bottom: 10px solid #808080" >
                    	<caption style="font-size: 22px; font-weight: bolder; color: white; padding: 10px;">Mysql Basic Page</caption>
	                    <tr>
	                        <td>host</td>
	                        <td><?php echo $_SESSION['basic']->get_host().":".$_SESSION['basic']->get_port();?>"</td>
	                    </tr>
						<tr>
							<td>database name</td>
							<td><?php echo $_SESSION['basic']->get_dbname();;?></td>
						</tr>
						<tr>
	                        <td>authorizen</td>
	                        <td><?php echo $_SESSION['basic']->get_username();;?>:<?php echo $_SESSION['basic']->get_password();?></td>
	                    </tr>
	                    <tr>
	                        <td>current time</td>
	                        <td><?php echo date('Y-m-d H:i:s');;?></td>
	                    </tr>
	                    <?php 
	                    	$conn = $_SESSION['basic']->connect();
	                    	if ($conn->connect_error) {
	                    		init_mysql_variables();
	                    		$_SESSION['error'] = true;
	                    		$_SESSION['error_msg'] = "Faled to connect to Mysql Server due to $conn->connect_error";
	                    		$conn->close();
	                    		refresh_page($filename);
	                        }else{
	                    ?>
	                    <tr>
	                        <td>protocol version</td>
	                        <td><?php echo $conn->protocol_version;?></td>
	                    </tr>
	                    <tr>
	                        <td>thread id</td>
	                        <td><?php echo $conn->thread_id;?></td>
	                    </tr>
	                    <tr>
	                        <td>server info</td>
	                        <td><?php echo $conn->server_info;?></td>
	                    </tr>
	                    <tr>
	                        <td>client info</td>
	                        <td><?php echo $conn->client_info;?></td>
	                    </tr>
	                    <tr>
	                        <td>warning count</td>
	                        <td><?php echo $conn->warning_count;?></td>
	                    </tr>
	                    <?php
	                        	$conn->close();
	                        }
	                    ?>
	                    <tr>
	                    	<td>disconnect</td>
	                        <td><input type="submit" value="disconnect" /></td>
	                    </tr>
	                </table>
	            </form>
			</div>
			<?php }?>
		</div>
		<div style="height: 50%; width: 100%; float: left;;box-sizing: border-box;"></div>
	</div>
		
</div>
</body>
</html>
