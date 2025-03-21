<?php
// File: ./core/controller.php

/**
* Base Controller Class
* All other controllers will extend this class
*/
class Controller {
   protected $db;
   protected $view;
   protected $model;
   
   /**
    * Constructor
    */
   public function __construct() {
       global $database;
       $this->db = $database;
       
       // Load view handler
       require_once 'view.php';
       $this->view = new View();
   }
   
   /**
    * Load model
    * 
    * @param string $model Model name
    * @return object Model instance
    */
   protected function loadModel($model) {
       // Convert model name to proper case
       $model = ucfirst($model) . 'Model';
       $file = '../models/' . $model . '.php';
       
       if (file_exists($file)) {
           require_once $file;
           $this->model = new $model();
           return $this->model;
       } else {
           die("Model file not found: $file");
       }
   }
   
   /**
    * Redirect to another page
    * 
    * @param string $url URL to redirect to
    * @return void
    */
   protected function redirect($url) {
       header("Location: $url");
       exit;
   }
   
   /**
    * Get user input from POST
    * 
    * @param string $key Input name
    * @param mixed $default Default value if input doesn't exist
    * @return mixed Input value
    */
   protected function post($key, $default = null) {
       return isset($_POST[$key]) ? $_POST[$key] : $default;
   }
   
   /**
    * Get user input from GET
    * 
    * @param string $key Input name
    * @param mixed $default Default value if input doesn't exist
    * @return mixed Input value
    */
   protected function get($key, $default = null) {
       return isset($_GET[$key]) ? $_GET[$key] : $default;
   }
}
?>