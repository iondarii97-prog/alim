<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'alim';

$conn = new mysqli($host,$user,$pass,$db);
$conn->set_charset('utf8mb4');