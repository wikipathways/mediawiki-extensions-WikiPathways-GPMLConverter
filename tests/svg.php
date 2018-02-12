<?php
ini_set('display_startup_errors',1);
ini_set('display_errors',1);
error_reporting(-1);
require_once "../src/GPMLConverter.php";
use WikiPathways\GPMLConverter;

function alphanumeric($s) {
	return preg_replace("/[^a-zA-Z0-9]+/", "", $s);
}

$identifier = isset($_GET["identifier"]) ? alphanumeric($_GET["identifier"]) : "WP4";
$version = isset($_GET["version"]) ? alphanumeric($_GET["version"]) : "0";
$theme = isset($_GET["theme"]) ? alphanumeric($_GET["theme"]) : "plain";

$gpml = base64_decode(json_decode(file_get_contents("https://webservice.wikipathways.org/getPathwayAs?fileType=gpml&pwId=$identifier&format=json"))->data);
$gpml_parsed = new SimpleXMLElement($gpml);
$organism = $gpml_parsed['Organism'];

$pvjson = GPMLConverter::gpml2pvjson($gpml, array("identifier"=>$identifier, "version"=>$version, "organism"=>$organism));

#echo 'why';
#echo $pvjson;
echo GPMLConverter::pvjson2svg($pvjson, array("react"=>false, "theme"=>$theme));
