<?php
// File: ./includes/database.php

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'educational_platform';

// Database connection class
class Database {
   private $conn;
   
   public function __construct($host, $user, $pass, $name) {
       $this->conn = new mysqli($host, $user, $pass, $name);
       
       if ($this->conn->connect_error) {
           die("Connection failed: " . $this->conn->connect_error);
       }
   }
   
   public function query($sql) {
       return $this->conn->query($sql);
   }
   
   public function prepare($sql) {
       return $this->conn->prepare($sql);
   }
   
   public function escape($string) {
       return $this->conn->real_escape_string($string);
   }
   
   public function insert_id() {
       return $this->conn->insert_id;
   }
   
   public function error() {
       return $this->conn->error;
   }
   
   public function close() {
       $this->conn->close();
   }
}

// Create database instance
$database = new Database($db_host, $db_user, $db_pass, $db_name);
?>