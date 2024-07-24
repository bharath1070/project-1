<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") 
{
    $subjectName = $_POST['subjectName'];
    $subjectCode = $_POST['subjectCode'];
	
    $target_directory = "Notes/";

    $newFileName = $subjectCode . "_" . str_replace(' ', '_', $subjectName) . "." . strtolower(pathinfo($_FILES["subjectFile"]["name"], PATHINFO_EXTENSION));

    $target_file = $target_directory . basename($newFileName);
    $uploadOk = 1;
    $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    if (file_exists($target_file)) 
	{
        $uploadOk = 0;
		header("Location: fileExist.php");
        exit;
    }

    // Check file size
    if ($_FILES["subjectFile"]["size"] > 500000) 
	{
        $uploadOk = 0;
		header("Location: fileSize.php");
        exit;
    }

    // Allow only certain file formats
    $allowed_extensions = array('txt');
    if (!in_array($fileType, $allowed_extensions)) 
	{
        $uploadOk = 0;
		header("Location: fileType.php");
        exit;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) 
	{
        echo "Sorry, your file was not uploaded.";
    } 
	else 
	{
        // Move the uploaded file to the specified directory
        if (move_uploaded_file($_FILES["subjectFile"]["tmp_name"], $target_file)) 
		{
            //echo "The file " . htmlspecialchars($newFileName) . " has been uploaded successfully.";
            // Read the content of the uploaded file
            $fileContent = file_get_contents($target_file);
			$delimiters = [
						'*****Unit I*****',
						'*****Unit II*****',
						'*****Unit III*****',
						'*****Unit IV*****',
						'*****Unit V*****'
					];
			$sections = explode($delimiters[0], $fileContent);
			// Split the content into chunks of 500 words
			$wordChunks = preg_split('/(\W+)/', $fileContent, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
			$chunks = array_chunk($wordChunks, 500);
			
			$tempCTID = 0;
			// Loop through each chunk and make API request
			foreach ($chunks as $index => $chunk) 
			{
				// Construct the payload
				$payload = json_encode([
					"input_text" => implode(' ', $chunk),
					"language" => "English",
					"qag_type" => "Multitask",
					"model" => "Flan-T5 BASE",
					"highlight" => "",
					"num_beams" => 4,
					"split" => "Paragraph",
					"use_gpu" => false,
					"do_sample" => true,
					"top_p" => 0.95,
					"max_length" => 64,
				]);

				// Set the endpoint URL
				$url = "https://lmqg-api-ijnzg4eymq-uc.a.run.app/question_generation";

				// Set up cURL to make the POST request
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

				// Execute the cURL request
				$response = curl_exec($ch);

				// Check for cURL errors
				if (curl_errno($ch)) {
					header("Location: QgenAPIError.php");
					exit;
				}

				// Close cURL session
				curl_close($ch);

				// Decode and display the API response
				$responseData = json_decode($response, true);
				if($responseData != NULL && isset($responseData['qa']))
				{
					$questionsOnly = array_column($responseData['qa'], 'question');
					$qaSize = count($questionsOnly);
					if($qaSize % 3 != 0 ) 
					{
						$qaSize = $qaSize - $qaSize % 3;
					}
					$questionsOnly = array_slice($questionsOnly, 0, $qaSize);
					if($qaSize >=3 and $tempCTID ==0)
					{
						createTableInDB($subjectCode, $subjectName);
						$tempCTID = 1;
					}
					if($qaSize >=3)
					{
						storeQuestionsInDatabase($questionsOnly);
					}
				}
			}
        } 
		else 
		{
            header("Location: UploadFail.php");
			exit;
        }
    }
} else {
    header('Location: index.php');
    exit();
}
function storeQuestionsInDatabase($questionsOnly) {
    $servername = "localhost";
    $username = "541907";
    $password = "Hari10513";
    $dbname = "541907";

    // Create a database connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
	$query = "SELECT MAX(id) AS max_id FROM tableDetails";
    $result = $conn->query($query);
	$row = $result->fetch_assoc();
    $maxId = $row['max_id'];
	$tableName = "question_$maxId";
	for ($row = 0; $row < count($questionsOnly) / 3; $row++) 
	{
		$secA = $questionsOnly[$row * 3];
		$secB = $questionsOnly[$row * 3 + 1];
		$secC = $questionsOnly[$row * 3 + 2];
		$Insertquery = "INSERT INTO $tableName (Section_A, Section_B, Section_C) VALUES ('$secA', '$secB', '$secC')";
		$result = $conn->query($Insertquery);
	}
	// Close the database connection
    $conn->close();
}
function createTableInDB($subjectCode, $subjectName) {
    $servername = "localhost";
    $username = "541907";
    $password = "Hari10513";
    $dbname = "541907";

    // Create a database connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check the connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
	$query = "SELECT MAX(id) AS max_id FROM tableDetails";
    $result = $conn->query($query);
	$row = $result->fetch_assoc();
    $maxId = $row['max_id'] + 1;
	$tableName = "question_$maxId";

    // Create a new table if it doesn't exist
    $createTableQuery = "CREATE TABLE IF NOT EXISTS $tableName (
        Section_A VARCHAR(1500) NOT NULL,
        Section_B VARCHAR(1500) NOT NULL,
        Section_C VARCHAR(1500) NOT NULL
    )";

    if ($conn->query($createTableQuery) != TRUE) {
        header("Location: DBTableCreationError.php");
		exit;
    }
	
	$insertQuery = "INSERT INTO tableDetails(`Id`, `subject_code`, `tableName`) VALUES ($maxId,'$subjectCode', '$tableName')";
	if ($conn->query($insertQuery) != TRUE) {
        header("Location: DBTableCreationError.php");
		exit;
    }
	$insertQuery2 = "INSERT INTO subjects(`subject_name`, `subject_code`) VALUES ('$subjectName', '$subjectCode')";
	if ($conn->query($insertQuery2) != TRUE) {
        header("Location: DBTableCreationError.php");
		exit;
    }
    // Close the database connection
    $conn->close();
}
?>
