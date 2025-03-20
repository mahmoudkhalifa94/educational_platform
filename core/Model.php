<?php
// File: ./core/model.php

/**
* Base Model Class
* All specific models will extend this class
*/
class Model {
   protected $db;
   protected $table;
   
   /**
    * Constructor
    */
   public function __construct() {
       global $database;
       $this->db = $database;
   }
   
   /**
    * Get all records
    * 
    * @param string $orderBy Order by column
    * @param string $order Order direction (ASC/DESC)
    * @return array Records
    */
   public function all($orderBy = 'id', $order = 'ASC') {
       $query = "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$order}";
       $result = $this->db->query($query);
       
       $records = [];
       while ($row = $result->fetch_assoc()) {
           $records[] = $row;
       }
       
       return $records;
   }
   
   /**
    * Find record by ID
    * 
    * @param int $id Record ID
    * @return array|bool Record or false if not found
    */
   public function find($id) {
       $id = $this->db->escape($id);
       $query = "SELECT * FROM {$this->table} WHERE id = '{$id}' LIMIT 1";
       $result = $this->db->query($query);
       
       if ($result->num_rows > 0) {
           return $result->fetch_assoc();
       }
       
       return false;
   }
   
   /**
    * Find records by condition
    * 
    * @param string $column Column name
    * @param string $value Value to search for
    * @return array Records
    */
   public function where($column, $value) {
       $column = $this->db->escape($column);
       $value = $this->db->escape($value);
       
       $query = "SELECT * FROM {$this->table} WHERE {$column} = '{$value}'";
       $result = $this->db->query($query);
       
       $records = [];
       while ($row = $result->fetch_assoc()) {
           $records[] = $row;
       }
       
       return $records;
   }
   
   /**
    * Create new record
    * 
    * @param array $data Record data
    * @return int|bool New record ID or false on failure
    */
   public function create($data) {
       $columns = [];
       $values = [];
       
       foreach ($data as $column => $value) {
           $columns[] = $column;
           $values[] = "'" . $this->db->escape($value) . "'";
       }
       
       $columns = implode(', ', $columns);
       $values = implode(', ', $values);
       
       $query = "INSERT INTO {$this->table} ({$columns}) VALUES ({$values})";
       
       if ($this->db->query($query)) {
           return $this->db->insert_id();
       }
       
       return false;
   }
   
   /**
    * Update record
    * 
    * @param int $id Record ID
    * @param array $data Record data
    * @return bool Success status
    */
   public function update($id, $data) {
       $id = $this->db->escape($id);
       $updates = [];
       
       foreach ($data as $column => $value) {
           $updates[] = "{$column} = '" . $this->db->escape($value) . "'";
       }
       
       $updates = implode(', ', $updates);
       
       $query = "UPDATE {$this->table} SET {$updates} WHERE id = '{$id}'";
       
       return $this->db->query($query);
   }
   
   /**
    * Delete record
    * 
    * @param int $id Record ID
    * @return bool Success status
    */
   public function delete($id) {
       $id = $this->db->escape($id);
       $query = "DELETE FROM {$this->table} WHERE id = '{$id}'";
       
       return $this->db->query($query);
   }
}
?>