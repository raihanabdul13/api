<?php

// load file koneksi
require_once('db.php');
// load model response json
require_once('../model/Response.php');
// load class jadwal
require_once('../model/Jadwal.php');

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

// Cek inputan masuk
if (array_key_exists('jadwal_id', $_GET)) {
	$id = $_GET['jadwal_id'];

	// VALIDASI ID HARUS BERUPA ANGKA
	if ($id == '' || !is_numeric($id)) {
		$res = new Response();
		$res->setHttpStatusCode(400);
		$res->setSuccess(false);
		$res->setMessages('ID Jadwal tidak boleh kosong atau harus berupa angka!');
		$res->send();
		exit;
	}

	// CEK HTTP METHOD / VERB
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		try {
			$query = $readDb->prepare('SELECT * FROM jadwal WHERE id_jadwal = :id');
			$query->bindParam(':id', $id, PDO::PARAM_INT);
			$query->execute();

			$row = $query->rowCount();
			if ($row === 0) {
				$res = new Response();
				$res->setHttpStatusCode(400);
				$res->setSuccess(false);
				$res->setMessages('ID Jadwal tidak ditemukan!');
				$res->send();
				exit;
			}

			$JadwalArray = null;
			while ($v = $query->fetch(PDO::FETCH_ASSOC)) {
				$jadwal = new Jadwal($v['id_jadwal'], $v['hari'], $v['kuota']);
				$jadwalArray = $jadwal->returnJadwalAsArray();
			}

			$returnData = [];
			$returnData['row_returned'] = $row;
			$returnData['data'] = $jadwalArray;

			$res = new Response();
			$res->setHttpStatusCode(200);
			$res->setSuccess(true);
			$res->toCache(true);
			$res->setData($returnData);
			$res->send();
			exit;
		} catch (PDOException $e) {
			// Perlu direkam ke error_log, karena kesalahan dari backend yang tidak diketahui
			error_log($e->getMessage());
			$res = new Response();
			$res->setHttpStatusCode(500);
			$res->setSuccess(false);
			$res->setMessages('Failed to get jadwal!');
			$res->send();
			exit;
		} catch (JadwalException $e) {
			// Tidak perlu direkam ke error_log, karena pure kesalahan dr user
			$res = new Response();
			$res->setHttpStatusCode(500);
			$res->setSuccess(false);
			$res->setMessages($e->getMessage());
			$res->send();
			exit;
		}
	} else {
		// HTTP VERB POST, PUT, DELETE DIBLOCK
		$res = new Response();
		$res->setHttpStatusCode(405);
		$res->setSuccess(false);
		$res->setMessages('Request method not allowed');
		$res->send();
		exit;
	}
} else if (empty($_GET)) {
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		try {
			$query = $readDb->prepare('SELECT * FROM jadwal');
			$query->execute();
			$row = $query->rowCount();

			if ($row === 0) {
				$res = new Response();
				$res->setHttpStatusCode(400);
				$res->setSuccess(false);
				$res->setMessages('Jadwal tidak ditemukan!');
				$res->send();
				exit;
			}

			$JadwalArray = null;
			while ($v = $query->fetch(PDO::FETCH_ASSOC)) {
				$jadwal = new Jadwal($v['id_jadwal'], $v['hari'], $v['kuota']);
				$jadwalArray[] = $jadwal->returnJadwalAsArray();
			}

			$returnData = [];
			$returnData['row_returned'] = $row;
			$returnData['data'] = $jadwalArray;

			$res = new Response();
			$res->setHttpStatusCode(200);
			$res->setSuccess(true);
			$res->toCache(true);
			$res->setData($returnData);
			$res->send();
			exit;
		} catch (PDOException $e) {
			// Perlu direkam ke error_log, karena kesalahan dari backend yang tidak diketahui
			error_log($e->getMessage());
			$res = new Response();
			$res->setHttpStatusCode(500);
			$res->setSuccess(false);
			$res->setMessages('Failed to get jadwal!');
			$res->send();
			exit;
		} catch (JadwalException $e) {
			// Tidak perlu direkam ke error_log, karena pure kesalahan dr user
			$res = new Response();
			$res->setHttpStatusCode(500);
			$res->setSuccess(false);
			$res->setMessages($e->getMessage());
			$res->send();
			exit;
		}
	}else if($_SERVER['REQUEST_METHOD'] === 'POST'){
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
				
			}
			//insert input to database
			$newJadwal = new Jadwal(null,$jsonData->hari_jadwal, $jsonData->kuota_jadwal);
			$hari_jadwal = $newJadwal->getHari();
			$kuota_jadwal = $newJadwal->getKuota();
			// create db query
			$query = $writeDb->prepare('insert into jadwal (hari, kuota) values (:hari_jadwal, :kuota_jadwal)');
			$query->bindParam(':hari_jadwal', $hari_jadwal, PDO::PARAM_STR);
			$query->bindParam(':kuota_jadwal', $kuota_jadwal, PDO::PARAM_STR);
			$query->execute();

			//cek query
			if ($query->rowCount() === 0) {
				$response = new Response();
				$response->setHttpStatusCode(500);
				$response->setSuccess(false);
				$response->setMessages("Error creating jadwal");
				$response->send();
				exit;
			  }
			  
			  // get last task id so we can return the Task in the json
			  $lastjadwalID = $writeDb->lastInsertId();
	
			  $query = $writeDb->prepare('SELECT * from jadwal where id_jadwal = :lastjadwalID');
			  $query->bindParam(':lastjadwalID', $lastjadwalID, PDO::PARAM_INT);
			  $query->execute();
	
			  // get row count
			  $rowCount = $query->rowCount();
			  
			  // make sure that the new task was returned
			  if($rowCount === 0) {
				// set up response for unsuccessful return
				$response = new Response();
				$response->setHttpStatusCode(500);
				$response->setSuccess(false);
				$response->setMessages("Failed to retrieve jadwal after creation");
				$response->send();
				exit;
			  }

			// create empty array to store tasks
			$JadwalArray = array();

			// for each row returned - should be just one
			while($row = $query->fetch(PDO::FETCH_ASSOC)) {
			  // create new jadwal object
			  $jadwal = new Jadwal($row['id_jadwal'], $row['hari'], $row['kuota']);
			  // create jadwal and store in array for return in json data
			  $JadwalArray[] = $jadwal->returnJadwalAsArray();
			}
			// bundle jadwal and rows returned into an array to return in the json data
			$returnData = array();
			$returnData['rows_returned'] = $rowCount;
			$returnData['jadwal'] = $JadwalArray;

			//set up response for successful return
			$response = new Response();
			$response->setHttpStatusCode(201);
			$response->setSuccess(true);
			$response->setMessages("Jadwal created");
			$response->setData($returnData);
			$response->send();
			exit;      
		}
		// if jadwal fails to create due to data types, missing fields or invalid data then send error json
	    catch(RegistException $ex) {
			$response = new Response();
			$response->setHttpStatusCode(400);
			$response->setSuccess(false);
			$response->setMessages($ex->getMessage());
			$response->send();
			exit;
		}
	}else{

		// HTTP VERB POST, PUT, DELETE DIBLOCK
		$res = new Response();
		$res->setHttpStatusCode(405);
		$res->setSuccess(false);
		$res->setMessages('Request method not allowed');
		$res->send();
		exit;
	}
} else {
	// 404 Error alias endpoint tidak ditemukan!
  	$response = new Response();
  	$response->setHttpStatusCode(404);
  	$response->setSuccess(false);
  	$response->setMessages("Endpoint not found");
  	$response->send();
  	exit;
}