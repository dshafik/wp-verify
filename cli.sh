<?php

/**
 * PHP CLI Framework
 *
 * An open source command line interface framework for PHP 5.0 or newer.
 *
 * @package PHP CLI    
 * @author Tj Holowaychuk <tj@vision-media.ca>  
 * @copyright Copyright (c) 2008 Tj Holowaychuk  
 * @link http://cliframework.com  
 */
 
/**
 * Bullet Lists.
 */
define('CLI_LIST_BULLET',  1);
define('CLI_LIST_NUMERIC', 2);
define('CLI_LIST_ALPHA',   3);

class CLI {
  
  /**
   * Version.
   * 
   * @var string  
   */
  const VERSION = '0.1';
  
  /**
   * Options.
   * 
   * @var array  
   * 
   * @access public 
   * 
   * @since Version 0.1
   */
  static public $o;
  
  /**
   * Option descriptions.
   * 
   * @var array 
   * 
   * @access public  
   *  
   * @since Version 0.1
   */
  static public $od;
  
  /* -----------------------------------------------------------------
  
    Methods 
  
  ------------------------------------------------------------------ */
  
  /**
   * Set options.
   * 
   * @param array $options
   *   Array of assoc options; option => description
   *     - Optional: 'v'
   *     - Requires value: 'd:'
   *     - Optional value: 'h::' 
   *         - Note: only supported in PHP 5.3.x and greater
   * 
   * @access public
   * 
   * @since Version 0.1
   * 
   * @todo support long options 
   * @todo format help better 
   */
  static public function seto($options) {       
    self::$od = $options;
    self::$o =  getopt((string) implode((array) array_keys($options)));
  }
  
  /**
   * Get option.
   * 
   * @param string $option
   * 
   * @return mixed
   *   - Option set without value required: TRUE
   *   - Option set value: Value returned
   *   - Required option set with value: Value returned
   *   - Option not set or value is not present: FALSE 
   * 
   * @access public 
   * 
   * @since Version 0.1
   */
  static public function geto($option) {  
    if (isset(self::$o[$option]) && $value = self::$o[$option]){
      return $value;  
    }
        
    return array_key_exists($option, self::$o);
  }
 
  /**
   * Get an argument value by index.
   * 
   * @param int $i
   * 
   * @return mixed
   *   - success: String 
   *   - failure: FALSE
   * 
   * @access public
   * 
   * @since Version 0.1
   * 
   * @todo check if the argument is an option, or how to handle this better
   */
  static public function getv($i) {  
    global $argv; 
    return (!empty($argv[$i])) ? $argv[$i] : FALSE;
  }
  
  /**
   * Display help information.
   * 
   * @access public 
   * 
   * @since Version 0.1
   * 
   * @todo: hook for additional help, or global
   * @todo: support php 5.30's :: optional values
   * @todo: make a spacer theme, theme_list, theme_table, theme_spacer
   */
  static public function gethelp() {
    $additional = array();
    
    // Display options
    echo self::theme_spacer('Options'); 
    
    foreach((array) self::$od AS $option => $description){    
      if (strpos($option, '+') === 0){
        $additional[str_replace('+', '', $option)] = $description;
        continue;
      }
      if (strstr($option, ':')){
        echo "\n  -" . str_replace(':', '', $option) . " : {$description}";
      }
      else {
        echo "\n  -" . $option . " : {$description}";      
      }
    }
              
     echo "\n" . self::theme_spacer() . "\n";
                       
    // Display additional 
    foreach((array) $additional AS $caption => $description){ 
     echo self::theme_spacer($caption);
     echo $description;
     echo self::theme_spacer() . "\n"; 
    }
  }
  
  /**
   * Table output.
   * 
   * @param array $rows
   * 
   * @param array $headers 
   *   (optional) Column headers.
   * 
   * @param string $caption
   *   (optional) Table caption.
   * 
   * @param $pad_type_headers
   *   (optional) Padding method.
   *   - STR_PAD_LEFT
   *   - STR_PAD_RIGHT
   *   - STR_PAD_BOTH
   * 
   * @param $pad_type_cells
   *   (optional) Padding method.  
   *   - STR_PAD_LEFT
   *   - STR_PAD_RIGHT
   *   - STR_PAD_BOTH
   * 
   * @return string
   * 
   * @access public 
   * 
   * @since Version 0.1
   * 
   * @todo support and detect colspans
   */
  static public function theme_table($rows, $headers = array(), $caption = NULL, $pad_type_headers = STR_PAD_BOTH, $pad_type_cells = STR_PAD_RIGHT) {
    $output = '';
    $cl = array();                                                          
    $clp = 0;    
    
    // Rows are manditory for the table to print
    if (!count($rows)){
      return FALSE;
    }                                                      
    
    // Determine each columns length
    foreach((array) $headers AS $i => $header){
      $cl[$i] = strlen($header) > $cl[$i] ? strlen($header) : $cl[$i];
    }
    foreach((array) $rows AS $row){
      foreach((array) $row AS $i => $cell){
        $cl[$i] = strlen($cell) > $cl[$i] ? strlen($cell) : $cl[$i];
      }
    }
          
    // Column length product
    foreach((array) $cl AS $l){
      $clp += $l;
    }     
          
    // Caption
    if ($caption){
      $output .= "\n  ";                                     
      $output .= str_pad(" {$caption} ", $clp + ((count($cl) * 2) + 2), '-', STR_PAD_BOTH);
    }
    // Headers
    if (count($headers)){
      $output .= "\n | ";
      foreach((array) $headers AS $i => $header){
        $output .= str_pad($header, $cl[$i], ' ', $pad_type_headers) . " | ";
      }   
    }  
    // Rows
    foreach((array) $rows AS $row){
      $output .= "\n | ";
      foreach((array) $row AS $i => $cell){      
        $output .= str_pad($cell, $cl[$i], ' ', $pad_type_cells) . " | ";   
      }
    }
    $output .= "\n";
    
    return $output;
  }
  
  /**
   * Spacer.
   * 
   * @param string $caption 
   *   (optional) Spacer caption.
   * 
   * @return string
   * 
   * @access public 
   * 
   * @since Version 0.1
   */
   static public function theme_spacer($caption = ''){
     $caption = !empty($caption) ? $caption . ' ' : '';
     return "\n " . str_pad($caption, 45, '-', STR_PAD_RIGHT) . "\n";      
   }
   
  /**
   * List.
   * 
   * @param array $items
   * 
   * @param string $caption 
   *   (optional) List caption.
   * 
   * @param int $list_type
   *   (optional) List style type.
   *      - CLI_LIST_ALPHA   
   *      - CLI_LIST_BULLET  
   *      - CLI_LIST_NUMERIC
   * 
   * @return string
   * 
   * @access public 
   * 
   * @since Version 0.1
   */
   static public function theme_list($items, $caption = NULL, $list_type = CLI_LIST_BULLET){
     $output = "\n";
     
     // Caption
     if ($caption){
       $output .= " {$caption} \n";
     }
     
     // List items
     $output .= self::_list($items, $list_type);
     
     return $output;  
   }
   
   /**
    * Item list helper.
    * 
    * Loop menu items recursively to generate 
    * a item list formated via $list_type 
    * 
    * @see self::item_list
    * 
    * @access protected 
    * 
    * @since Version 0.1
    */
   static protected function _list($items, $list_type = CLI_LIST_BULLET, $depth = 0) {
     static $list_id = 0;
     $output = '';
     $list_id++;
     
     foreach((array) $items AS $i => $item){
       if (is_array($item)){
         $output .= self::_list($item, $list_type, $depth + 1);
       }
       else {        
         $output .= str_repeat('   ', $depth + 1) . self::_list_type($i, $list_id, $list_type, $depth) . ' ' . $item . " \n"; 
       }
     }
     
     return $output;
   }
   
   /**
    * Item list type helper.
    * 
    * Based on the current depth of an item list
    * return the proper bullet format based on
    * the $list_type pram
    * 
    * @see self::_list
    * 
    * @access protected 
    * 
    * @since Version 0.1
    */
   static protected function _list_type($i, $list_id, $list_type = CLI_LIST_BULLET, $depth = 0) {
     static $lists;
     
     $lists[$list_id][$depth]++;
     
     switch($list_type){
       case CLI_LIST_ALPHA:
         $output = !$depth ? chr(96 + (int) $lists[$list_id][$depth]) : '-'; 
         break;
        
       case CLI_LIST_BULLET:
         $output = '-';
         break;
       
       case CLI_LIST_NUMERIC:
         $output = !$depth ? (int) $lists[$list_id][$depth] : chr(96 + (int) $lists[$list_id][$depth]);  
         break;
     }
     
     if ($list_type != CLI_LIST_BULLET){
       $output .= '.';
     }
     
     return $output;
   }
    
  /**
   * Prompt user input.
   * 
   * @param string $message   
   * 
   * @return string
   *   Response.
   * 
   * @access public 
   * 
   * @since Version 0.1
   */
  static public function prompt($message) {
    echo $message . " \n";
    return fgets(STDIN, 128);
  }
                                                  
  /**
   * Output an error message, halting the program.
   * 
   * @param string $message
   * 
   * @access public 
   * 
   * @since Version 0.1
   * 
   * @todo: support errorno's
   * @todo: errorno map
   */
  static public function error($message) {
    fwrite(STDERR, "\nError: {$message}\n");
    exit;
  }

  /**
   * Debug.
   * 
   * @param mixed $var
   * 
   * @access public 
   * 
   * @since Version 0.1
   */
  static public function dbg($var) {
    echo "\n" . print_r($var) . "\n";  
  }  
}                     
  

