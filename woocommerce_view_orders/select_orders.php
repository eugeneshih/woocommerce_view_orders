<?php

/* 
 * Copyright (c) 2013 Eugene Shih
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this program.  If not, see
 * <http://www.gnu.org/licenses/>.
 */

class SelectOrders
{
  // properties
  private $mysqli;

  // constructor
  function __construct($database_host,
                       $database_user,
                       $database_pwd,
                       $database_default_db) {
    $this->mysqli = new mysqli($database_host,
                               $database_user,
                               $database_pwd,
                               $database_default_db);
    if ($this->mysqli->connect_errno) {
      echo "Failed to connect to MySQL: (" . 
        $this->mysqli->connect_errno . 
        ") " . $this->mysqli->connect_error;
    }
  }

  function getUniqueProducts($beginDate, $endDate, $slug) {
    $res = $this->mysqli->query("SELECT DISTINCT post_title FROM " .
                                "wp_ue7xmr_posts " .
                                "JOIN wp_ue7xmr_term_relationships " .
                                "ON wp_ue7xmr_posts.id = wp_ue7xmr_term_relationships.object_id " .
                                "JOIN wp_ue7xmr_terms " .
                                "ON term_id = term_taxonomy_id " .
                                "WHERE " .
                                "post_type='product' and " .
                                "post_date > date(\"" . $beginDate . "\") and " .
                                "post_date < date(\"" . $endDate   . "\") and " .
                                "post_title not like \"Test%\" and " .
                                "slug=\"" . $slug . "\"");
    $products_array = array();
    $res->data_seek(0);
    while($row = $res->fetch_assoc()) {
      $products_array[] = $row['post_title'];
    }  
    return $products_array;
  }

  function getOrderIds($classname, $beginDate, $endDate) {
    // list all students taking a specific class, if $classname is empty, it will return all
    // classes that paid
    // look at the terms and term_relationships tables to determine paid status
    $res = $this->mysqli->query("SELECT DISTINCT id " .
                                "FROM wp_ue7xmr_posts JOIN wp_ue7xmr_postmeta " .
                                "ON id = post_id " .
                                "JOIN wp_ue7xmr_woocommerce_order_items " .
                                "ON id = order_id " .
                                "JOIN wp_ue7xmr_woocommerce_order_itemmeta " .
                                "ON wp_ue7xmr_woocommerce_order_items.order_item_id = " .
                                "  wp_ue7xmr_woocommerce_order_itemmeta.order_item_id " .
                                "JOIN wp_ue7xmr_term_relationships " .
                                "ON id = object_id " .
                                "JOIN wp_ue7xmr_terms " .
                                "ON term_id = term_taxonomy_id " .
                                "WHERE post_type='shop_order' and " .
                                " order_item_name = '" . $classname . "' and " .
                                " (slug='processing' or slug='completed') and " .
                                " wp_ue7xmr_woocommerce_order_itemmeta.meta_key='_qty' and " .
                                " wp_ue7xmr_woocommerce_order_itemmeta.meta_value > 0");
    $shop_order_ids = array();
    $res->data_seek(0);
    while($row = $res->fetch_assoc()) {
      $shop_order_ids[] = $row['id'];
    }  
    return $shop_order_ids;
  }

  function getOrderMetaData($shop_order_ids) {
    $shop_order_id_string = implode(",", $shop_order_ids);

    // list all orders
    $res = $this->mysqli->query("SELECT wp_ue7xmr_postmeta.meta_key AS meta_key, " .
                          " wp_ue7xmr_postmeta.meta_value AS meta_value, " .
                          "wp_ue7xmr_posts.id AS id " .
                          "FROM wp_ue7xmr_posts " .
                          "JOIN wp_ue7xmr_postmeta " .
                          "ON wp_ue7xmr_posts.id = wp_ue7xmr_postmeta.post_id " .
                          "WHERE wp_ue7xmr_posts.id in (" . $shop_order_id_string . ")");
    $res->data_seek(0);
    while($row = $res->fetch_assoc()) {
      $index = $row['id'];
      $order_dict[$index][$row['meta_key']] = $row['meta_value'];
    }
    
    return $order_dict;
  }
}

function makerow($array_of_strings, $bold=false) {
  $row = "<tr>\n";
  foreach($array_of_strings as &$string) {
    if($bold) {
      $output = "<b>" . $string . "</b>";
    } else {
      $output = $string;
    }
    $col = "<td>" . $output . "</td>\n";
    $row = $row . $col;
  }
  $row = $row . "</tr>\n";
  return $row;
}

function generate_md($order_dict, $class) {
  $fields = array("student_first_name", "student_last_name", "grade", "teacher",
                  "_billing_first_name", "_billing_last_name", "_billing_contactphone", "_billing_email", 
                  "child_from_red");

  $fname = implode("_", explode(" ", $class)) . ".md";
  $fh = fopen($fname, "w");

  // heading
  fwrite($fh, "## " . $class . " ##\n");
  fwrite($fh, "<table>");

  // write headers
  $headings = array();
  foreach($fields as &$heading) {
    $headings[] = ucwords(implode(" ", explode("_", $heading)));
  }
  fwrite($fh, makerow($headings, true));

  // write students
  $orderIds = array_keys($order_dict);
  foreach($orderIds as &$key) {
    $student_data = array();
    foreach($fields as &$meta_key) {
      if ($meta_key == '_billing_email') {
        $data = strtolower($order_dict[$key][$meta_key]);
      } else {
        $data = ucwords(strtolower($order_dict[$key][$meta_key]));
      }
      $student_data[] = $data;
    }
    fwrite($fh, makerow($student_data));
  }
  fwrite($fh, "</table>");
  fclose($fh);
}

function generate_csv($order_dict, $class) {
  $fields = array("student_first_name", "student_last_name", "grade", "teacher",
                  "_billing_first_name", "_billing_last_name", "_billing_contactphone", "_billing_email", 
                  "child_from_red");

  $fname = implode("_", explode(" ", $class)) . ".csv";
  $fh = fopen($fname, "w");
  
  // write headers
  fwrite($fh, "guardian first name, guardian last name, guardian phone, guardian e-mail, " . 
         "student first name, student last name, teacher, grade, RED\n");

  // select the order from the selected orders
  $orderIds = array_keys($order_dict);
  foreach($orderIds as &$key) {
    $meta_keys = array_keys($order_dict[$key]);
    // then go through the keys, printing out only the ones that we care about
    foreach($meta_keys as &$meta_key) {
      if(in_array($meta_key, $fields)) {
        //fwrite($fh, $meta_key . " = " . $order_dict[$key][$meta_key] . "\n");
        if($meta_key == 'child_from_red') {
          $data = $order_dict[$key][$meta_key];
          $data = implode("|", explode(", ", $data));
        } else {
          $data = $order_dict[$key][$meta_key];
        }
        fwrite($fh, $data . ",");
      }
    }
    fwrite($fh, "\n");
  }
  fclose($fh);
}

?>