<?php
// THIS MUST BE THE VERY FIRST LINE IN THE FILE
session_start(); 

header('Content-Type: application/json');

// Return the entire session array as JSON
echo json_encode($_SESSION);