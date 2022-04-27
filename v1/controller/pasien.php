<?php 

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/Pasien.php');

try {
	$writeDb = DB::connectWriteDb();
	$readDb = DB::connectReadDb();
} catch (Exception $e) {
	$res = new Response();
	$res->setHttpStatusCode(500);
	$res->setSuccess(false);
	$res->setMessages($e);
	$res->send();
	exit;
}

if (array_key_exists('pasien_id', $_GET)) {
	$id = $_GET['pasien_id'];

	if ($id == '' || !is_numeric($id)) {
		$res = new Response();
		$res->setHttpStatusCode(400);
		$res->setSuccess(false);
		$res->setMessages('ID Pasien tidak boleh kosong atau harus berupa angka!');
		$res->send();
		exit;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		try {
			$query = $readDb->prepare('SELECT id, nama, jk, hp FROM pasien WHERE id = :id');
			$query->bindParam(':id', $id, PDO::PARAM_INT);
			$query->execute();

			$row = $query->rowCount();
			if ($row === 0) {
				$res = new Response();
				$res->setHttpStatusCode(400);
				$res->setSuccess(false);
				$res->setMessages('ID Pasien tidak ditemukan!');
				$res->send();
				exit;
			}

			$pasienArray = null;
			while ($v = $query->fetch(PDO::FETCH_ASSOC)) {
				$pasien = new Pasien($v['id'], $v['nama'], $v['jk'], $v['hp']);
				$pasienArray = $pasien->returnPasienAsArray();
			}

			$returnData = [];
			$returnData['row_returned'] = $row;
			$returnData['data'] = $pasienArray;

			$res = new Response();
			$res->setHttpStatusCode(200);
			$res->setSuccess(true);
			$res->toCache(true);
			$res->setData($returnData);
			$res->send();
			exit;
		} catch (PDOException $e) {
			$res = new Response();
			$res->setHttpStatusCode(500);
			$res->setSuccess(false);
			$res->setMessages('Gagal mendapatkan data pasien');
			$res->send();
			exit;
		} catch (PasienException $e) {
			$res = new Response();
			$res->setHttpStatusCode(500);
			$res->setSuccess(false);
			$res->setMessages($e->getMessage());
			$res->send();
			exit;
		}
	} else {
		$res = new Response();
		$res->setHttpStatusCode(405);
		$res->setSuccess(false);
		$res->setMessages('Request method not allowed');
		$res->send();
		exit;
	}
} else if(empty($_GET)){
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		try{
			// cek content type header apakah JSON
			if(empty($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
				// respon gagal
				$response = new Response();
				$response->setHttpStatusCode(400);
				$response->setSuccess(false);
				$response->setMessages("Content Type header not set to JSON");
				$response->send();
				exit;
			}
			// get POST request body berformat JSON
			$rawPostData = file_get_contents('php://input');
	      
			if(!$jsonData = json_decode($rawPostData)) {
			  // respon gagal
			  $response = new Response();
			  $response->setHttpStatusCode(400);
			  $response->setSuccess(false);
			  $response->setMessages("Request body is not valid JSON");
			  $response->send();
			  exit;
			}
			//decode json format to array
			$jsonData = json_decode($rawPostData);
			// validasi inputan
			if(isset($jsonData)) {
				//validasi inputan required nama pasien
				if(!isset($jsonData->nama_pasien)){
					$response = new Response();
					$response->setHttpStatusCode(400);
					$response->setSuccess(false);
					$response->setMessages("nama_pasien field is required");
					$response->send();
					exit;
				}
				//validasi inputan format number nama pasien
				if(is_numeric($jsonData->nama_pasien)){
					$response = new Response();
					$response->setHttpStatusCode(400);
					$response->setSuccess(false);
					$response->setMessages("nama_pasien must be of type string");
					$response->send();
					exit;
				}
				//validasi inputan max lenght field nama pasien
				if(strlen($jsonData->nama_pasien) > 255){
					$response = new Response();
					$response->setHttpStatusCode(400);
					$response->setSuccess(false);
					$response->setMessages("nama_pasien max lenght is 255");
					$response->send();
					exit;
				}

				//validasi inputan required jenis kelamin pasien
				if(!isset($jsonData->jk_pasien)){
					$response = new Response();
					$response->setHttpStatusCode(400);
					$response->setSuccess(false);
					$response->setMessages("jk_pasien field is required");
					$response->send();
					exit;
				}
				//validasi inputan format jenis kelamin pasien
				if(!in_array($jsonData->jk_pasien, ['L','P'])){
					$response = new Response();
					$response->setHttpStatusCode(400);
					$response->setSuccess(false);
					$response->setMessages("jk_pasien must be worth 'L' for male and 'P' for female");
					$response->send();
					exit;
				}
				//validasi inputan required hp pasien
				if(!isset($jsonData->hp_pasien)){
					$response = new Response();
					$response->setHttpStatusCode(400);
					$response->setSuccess(false);
					$response->setMessages("hp_pasien field is required");
					$response->send();
					exit;
				}
				//validasi inputan format number hp pasien
				if(!is_numeric($jsonData->hp_pasien)){
					$response = new Response();
					$response->setHttpStatusCode(400);
					$response->setSuccess(false);
					$response->setMessages("invalid format input hp_pasien must be numeric example : 6281234567890 OR 081234567890");
					$response->send();
					exit;
				}
				//validasi inputan max lenght field hp pasien
				if(strlen($jsonData->hp_pasien) > 14 || strlen($jsonData->hp_pasien) < 9){
					$response = new Response();
					$response->setHttpStatusCode(400);
					$response->setSuccess(false);
					$response->setMessages("hp_pasien max lenght is 14 and min lenght is 9");
					$response->send();
					exit;
				}
			}
			//insert input to database
			$newPasien = new Pasien(null,$jsonData->nama_pasien, $jsonData->jk_pasien, $jsonData->hp_pasien);
			$nama_pasien = $newPasien->getNama();
			$jk_pasien = $newPasien->getJk();
			$hp_pasien = $newPasien->getHp();
			// create db query
			$query = $writeDb->prepare('insert into pasien (nama, jk, hp) values (:nama_pasien, :jk_pasien, :hp_pasien)');
			$query->bindParam(':nama_pasien', $nama_pasien, PDO::PARAM_STR);
			$query->bindParam(':jk_pasien', $jk_pasien, PDO::PARAM_STR);
			$query->bindParam(':hp_pasien', $hp_pasien, PDO::PARAM_STR);
			$query->execute();

			//cek query
			if ($query->rowCount() === 0) {
				$response = new Response();
				$response->setHttpStatusCode(500);
				$response->setSuccess(false);
				$response->setMessages("Error creating pasien");
				$response->send();
				exit;
			  }
			  // get row count
			  $rowCount = $query->rowCount();
	
			  if($rowCount === 0) {
				// set up response for unsuccessful return
				$response = new Response();
				$response->setHttpStatusCode(500);
				$response->setSuccess(false);
				$response->setMessages("Gagal pasien created");
				$response->send();
				exit;
			  }
			  
			  // get last task id so we can return the Task in the json
			  $lastpasienID = $writeDb->lastInsertId();
	
			  $query = $writeDb->prepare('SELECT * from pasien where id = :lastpasienID');
			  $query->bindParam(':lastpasienID', $lastpasienID, PDO::PARAM_INT);
			  $query->execute();
	
			  // get row count
			  $rowCount = $query->rowCount();
			  
			  // make sure that the new task was returned
			  if($rowCount === 0) {
				// set up response for unsuccessful return
				$response = new Response();
				$response->setHttpStatusCode(500);
				$response->setSuccess(false);
				$response->setMessages("Failed to retrieve pasien after creation");
				$response->send();
				exit;
			  }

			// create empty array to store tasks
			$PasienArray = array();

			// for each row returned - should be just one
			while($row = $query->fetch(PDO::FETCH_ASSOC)) {
			  // create new pasien object
			  $pasien = new Pasien($row['id'], $row['nama'], $row['jk'], $row['hp']);
  
			  // create pasien and store in array for return in json data
			  $PasienArray[] = $pasien->returnPasienAsArray();
			}
			// bundle pasien and rows returned into an array to return in the json data
			$returnData = array();
			$returnData['rows_returned'] = $rowCount;
			$returnData['pasien'] = $PasienArray;

			//set up response for successful return
			$response = new Response();
			$response->setHttpStatusCode(201);
			$response->setSuccess(true);
			$response->setMessages("Pasien created");
			$response->setData($returnData);
			$response->send();
			exit;      
		}
		// if pasien fails to create due to data types, missing fields or invalid data then send error json
	    catch(RegistException $ex) {
			$response = new Response();
			$response->setHttpStatusCode(400);
			$response->setSuccess(false);
			$response->setMessages($ex->getMessage());
			$response->send();
			exit;
		}
	}else{
		// HTTP VERB GET, PUT, DELETE DIBLOCK
		$res = new Response();
		$res->setHttpStatusCode(405);
		$res->setSuccess(false);
		$res->setMessages('Request method not allowed');
		$res->send();
		exit;
	}
}
else{
	// 404 Error alias endpoint tidak ditemukan!
  	$response = new Response();
  	$response->setHttpStatusCode(404);
  	$response->setSuccess(false);
  	$response->setMessages("Endpoint not found");
  	$response->send();
  	exit;
}

?>