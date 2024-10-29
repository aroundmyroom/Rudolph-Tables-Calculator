<?php
//
// this part is sending the output_[year].json file to the database
// adjust the #jsonFile here below
// in db.php you must have the valid connection string to your database
// you need to have created the db as provided
// There is a time alteration for SQL due to the TIME(2) database structure
// the format must match otherwise your data will not be inserted
// 
// 

// Include database connection
include 'db.php'; // Ensure this file contains your MySQL connection code

// Read the JSON file
$jsonFile = 'output_2023.json';
$jsonData = file_get_contents($jsonFile);

// Decode the JSON data
$dataArray = json_decode($jsonData, true);

// Check if the JSON data is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Debug: JSON decode error: " . json_last_error_msg());
}

// Loop through the data array and insert into the database
foreach ($dataArray as $entry) {
    // Extract values
    $year = $entry['year'];
    $point = $entry['point'];
    $gender = $entry['gender'];
    $age = (int)$entry['age']; // Cast age to integer
    $event = $entry['event'];
    $result = $entry['result'];

    // Convert the result format from "mm:ss,hh" to "hh:mm:ss"
    // Replace comma with a period for the fractional part
    $result = str_replace(',', '.', $result);

    // Use a regex to match the format mm:ss.hh
    if (preg_match('/^(\d{2}):(\d{2})\.(\d{2})$/', $result, $matches)) {
        $minutes = (int)$matches[1]; // mm
        $seconds = (int)$matches[2];  // ss
        $hundredths = (int)$matches[3]; // hh

        // Calculate hours from minutes
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60; // Remaining minutes

        // Prepare the MySQL TIME(2) format as hh:mm:ss
        // Here, we prepare the result in hh:mm:ss format, while keeping the hundredths for later processing
        $mysqlTime = sprintf('%02d:%02d:%02d.%02d', $hours, $minutes, $seconds, $hundredths);

        // Debugging output
        echo "Debug: Preparing to insert - Year: $year, Point: $point, Gender: $gender, Age: $age, Event: $event, Result: $mysqlTime\n";

        // Insert the data into the database
        $insertQuery = "INSERT INTO rudolphScore (year, point, gender, age, event, result) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($dbc, $insertQuery);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "iissss", $year, $point, $gender, $age, $event, $mysqlTime);
            if (mysqli_stmt_execute($stmt)) {
                echo "Debug: Successfully inserted entry for event '$event'.\n";
            } else {
                echo "Debug: Error executing statement: " . mysqli_error($dbc) . "\n";
            }
            mysqli_stmt_close($stmt);
        } else {
            echo "Debug: Error preparing statement: " . mysqli_error($dbc) . "\n";
        }
    } else {
        echo "Debug: Invalid result format for entry - Year: $year, Point: $point, Gender: $gender, Age: $age, Event: $event, Result: $result\n";
    }
}

// Close the database connection
mysqli_close($dbc);
?>
