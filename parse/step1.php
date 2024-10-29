<?php

// this script needs Composer with Smalot\PdfParser
// this scripts assumes that there is a file which is from year 2022, 2023 or 2024 to be parsed
// you can parse the years separately as the json is stored with year info
// earlier years are not being parsed correctly due to inconsistencies in the file
// there is a lot of debug information when starting this script in your CLI
// first the age and gender is being detected per page than the page is parsed
// from that parsing a json is created
//
// use step2.php for sending the json to the database
//
// check line 29 for the correct name of the pdf which must be in the root


require('vendor/autoload.php'); // Adjust if using a different PDF library

use Smalot\PdfParser\Parser;

// Check if the year is passed as a command-line argument
if ($argc < 2) {
    echo "Usage: php step1.php <year>\n";
    exit(1);
}

$year = $argv[1];

// Construct the PDF file path based on the provided year
$pdfPath = "[add-heree-the-file-what-needs-to-be-parsed_{$year}.pdf";

// Function to extract gender and age from the PDF pages
function parseGenderAndAge($pdfFilePath) {
    $parser = new Parser();
    $pdf = $parser->parseFile($pdfFilePath);
    $pages = $pdf->getPages();

    $data = [];

    // Gender mapping from German to English
    $genderMapping = [
        'männlich' => 'm',
        'weiblich' => 'f',
    ];

    foreach ($pages as $pageNumber => $page) {
        // Extract text from the page
        $text = $page->getText();
        echo "Debug: Page " . ($pageNumber + 1) . " text:\n" . $text . "\n"; // Debug output

        // Check for the gender and age using specific keywords
        if (preg_match('/Punkttabelle\s+(männlich|weiblich),\s*Altersklasse\s+(\d+|offen)/i', $text, $matches)) {
            $gender = trim($matches[1]); // 'Männlich' or 'Weiblich'
            $age = $matches[2]; // The age from 'Altersklasse' (can be a number or 'offen')

            // Convert gender to English
            $genderEnglish = isset($genderMapping[$gender]) ? $genderMapping[$gender] : $gender;

            // Store the data
            $data[] = [
                'page' => $pageNumber + 1,
                'gender' => $genderEnglish, // Use the English version of the gender
                'age' => $age
            ];
            echo "Debug: Found - Gender: {$genderEnglish}, Age: {$age} on Page: " . ($pageNumber + 1) . "\n"; // Debug output
        } else {
            echo "Debug: No match found on Page: " . ($pageNumber + 1) . "\n"; // Debug output
        }
    }

    return $data;
}

// Function to parse the PDF and convert all pages data to JSON
function parsePdfToJson($pdfPath, $genderAgeData, $year) {
    $parser = new Parser();
    echo "Parsing PDF file: {$pdfPath}\n";
    $pdf = $parser->parseFile($pdfPath);

    // Define events and distances based on your input
    $events = [
        ["stroke" => "Freestyle", "distances" => [50, 100, 200, 400, 800, 1500]],
        ["stroke" => "Breaststroke", "distances" => [50, 100, 200]],
        ["stroke" => "Butterfly", "distances" => [50, 100, 200]],
        ["stroke" => "Backstroke", "distances" => [50, 100, 200]],
        ["stroke" => "Medley", "distances" => [200, 400]]
    ];

    // Build the list of full events for easy indexing
    $eventNames = [];
    foreach ($events as $event) {
        foreach ($event['distances'] as $distance) {
            $eventNames[] = "{$distance} {$event['stroke']}";
        }
    }

    // Define parameters for extraction
    $data = [];

    // Loop through all pages (up to 24)
    for ($pageIndex = 0; $pageIndex < min(24, count($pdf->getPages())); $pageIndex++) {
        $pageText = $pdf->getPages()[$pageIndex]->getText(); // Get text for the current page
        echo "Parsing points and results on Page: " . ($pageIndex + 1) . "\n";

        // Split the page text into lines
        $lines = explode("\n", $pageText);

        // Extract points and results from rows below the header
        foreach ($lines as $line) {
            // Match a row where the first value is a point, followed by results
            if (preg_match('/^(\d{1,2})\s+((?:\d{2}:)?\d{2},\d{2})/', $line, $matches)) {
                $point = (int)$matches[1];
                $results = preg_split('/\s+/', trim(substr($line, strpos($line, $matches[2]))));

                // Map each result to its corresponding event
                foreach ($results as $key => $result) {
                    if (isset($eventNames[$key])) {
                        $gender = isset($genderAgeData[$pageIndex]) ? $genderAgeData[$pageIndex]['gender'] : '';
                        $age = isset($genderAgeData[$pageIndex]) ? $genderAgeData[$pageIndex]['age'] : '';

                        $data[] = [
                            "year" => $year,
                            "point" => $point,
                            "gender" => $gender, // Use gender from the corresponding page
                            "age" => $age,       // Use age from the corresponding page
                            "event" => $eventNames[$key],
                            "result" => $result,
                        ];
                        echo "Parsed entry: " . json_encode(end($data)) . "\n";
                    }
                }
            }
        }
    }

    return json_encode($data, JSON_PRETTY_PRINT);
}

// Execute gender and age extraction and JSON conversion
$genderAndAgeData = parseGenderAndAge($pdfPath);

// Parse all pages to JSON with extracted gender and age
$jsonData = parsePdfToJson($pdfPath, $genderAndAgeData, $year);

// Save JSON to file
$outputJsonPath = "output_{$year}.json";
file_put_contents($outputJsonPath, $jsonData);
echo "JSON data saved to {$outputJsonPath}\n";

?>
