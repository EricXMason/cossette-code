<?php
/******************************
 *  Search script 
 * 
 *  Created by Eric Mason
 * 
 *  For Cossette Application
 * 
 ******************************/

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

$city = "";

//Ensure city is properly entered
if(count($argv) !== 2) {
    echo "Please include a search term or enclose multiple word search terms in quotations";
    exit();
}

//Retrieve argument, remove potential excess whitespace, replace spaces with %20 and wrap in %22s(double quotes)
$city = $argv[1];
$city = trim($city);
$city = str_ireplace(" ", "%20", $city);
$city = "%22" . $city . "%22";

//Set up GuzzleHttp Client
$url = "https://musicbrainz.org/ws/2/";
$client = new Client(['base_uri' => $url]);

try
{ 
    //search MusicBrainz database for artists using the area field, I prefer to work in json due to familiarity
    $response = $client->get('artist/?query=area:' . $city . '&limit=10&offset=0&fmt=json',[
        'headers' => [
            'User-Agent' => 'cossetteSearch/0.0.1 (eric.e.mason@gmail.com)'
        ] 
    ]);
    //Retrieve our list of artists from the response
    $artists = json_decode($response->getBody()->getContents())->artists;

    $artistData = [];

    foreach ($artists as $artist) {

        //Performs the requested string manipulation regardless of case
        $nameString = str_ireplace("o","^", $artist->name);

        //If the artist has tags, create a string for the CSV file and an array for the XML file
        if(array_key_exists("tags", $artist)) {
            $tagString = "";
            foreach ($artist->tags as $tag) {
                $tagString .= $tag->name . ", ";
                $tagArray[] = $tag->name;
            }
            
            //Remove the trailing comma and space from the string and encase in double quotes
            $tagString = substr($tagString, 0, -2);
            $tagString = '"' . $tagString . '"';

            //Populate artistData array with the desired artist attributes if the artist has tags
            $artistData[] = [$nameString, $artist->id, $tagString, $tagArray];
            unset($tagString);
            unset($tagArray);
        }
        else {
            //Populate artistData array with the desired artist attributes if the artist does not have tags
            $artistData[] = [$nameString, $artist->id, "", []];
        }  
    }

    //Creates or replaces artists.csv, enters each artist's data as a comma delineated row 
    $csvFile = fopen("artists.csv", "w") or die("Unable to create/open CSV file");
    foreach($artistData as $data) {
        $record = $data[0] . ", " . $data[1] . ", " . $data[2] . "\n";
        fwrite($csvFile, $record);
    }
    fclose($csvFile);

    //Confirms the file's creation and sends notification to the console
    if(file_exists("./artists.csv")) {
        echo "CSV file created\n";
    }
    else {
        echo "Unable to create/open CSV file";
    }
    
    unset($data);
    
    //Creates or replaces artists.xml
    $xmlHeader = '<?xml version="1.0" encoding="UTF-8"?><Artists></Artists>';
    $xml = new SimpleXMLElement($xmlHeader);

    //Each artist gets name, id and tags child nodes. Tags then gets a list of tag child nodes
    foreach($artistData as $data) {
        $record = $xml->addChild('Artist');
        $record->addChild('Name', $data[0]);
        $record->addChild('Id', $data[1]);
        
        $tags = $record->addChild('Tags');
        foreach($data[3] as $tagData){
            $tags->Tag[] = $tagData;
        }
        unset($tags);
        unset($record);
    }

    //The XML file creation/replacement is attempted, notification of the result is sent to the console
    if($xml->asXML("artists.xml")) {
        echo "XML file created\n";
    }
    else{
        echo "Unable to create/open XML file\n";
    }
}
catch (RequestException $e)
{
    //If the search fails for any reason, output status code and reason phrase
    if($e->hasResponse()) {
        $response = $e->getResponse();
        echo $response->getStatusCode() . " ";
        echo $response->getReasonPhrase();
    }
    
}
?>