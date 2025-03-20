<?php
// File: ./core/view.php

/**
* View Class
* Handles rendering of templates and views
*/
class View {
   private $data = [];
   
   /**
    * Set data for view
    * 
    * @param string|array $key Variable name or associative array of variables
    * @param mixed $value Variable value
    * @return void
    */
   public function set($key, $value = null) {
       if (is_array($key)) {
           $this->data = array_merge($this->data, $key);
       } else {
           $this->data[$key] = $value;
       }
   }
   
   /**
    * Render view template
    * 
    * @param string $view View file name
    * @param array $data Additional data to make available in view
    * @param bool $return Whether to return the view as string
    * @return mixed Rendered view or void
    */
   public function render($view, $data = [], $return = false) {
       // Combine the data already set with the new data
       $data = array_merge($this->data, $data);
       
       // Extract variables for use in view
       extract($data);
       
       // Start output buffering
       ob_start();
       
       // Include view file
       $viewFile = '../views/' . $view . '.php';
       if (file_exists($viewFile)) {
           include $viewFile;
       } else {
           die("View file not found: $viewFile");
       }
       
       // Get the buffered content
       $content = ob_get_clean();
       
       if ($return) {
           return $content;
       } else {
           echo $content;
       }
   }
   
   /**
    * Render a partial view
    * 
    * @param string $partial Partial view name
    * @param array $data Data to make available in partial
    * @return string Rendered partial
    */
   public function partial($partial, $data = []) {
       return $this->render('partials/' . $partial, $data, true);
   }
}
?>