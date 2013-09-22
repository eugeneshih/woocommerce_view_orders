<?php

include 'select_orders.php';
include 'constants.php';

$selectOrders = new SelectOrders(DATABASE_HOST, DATABASE_USER, DATABASE_PWD, DATABASE_DEFAULT_DB);

$fields = array("student_first_name", "student_last_name", "grade", "teacher",
                "_billing_first_name", "_billing_last_name", "_billing_contactphone", "_billing_email", 
                "child_from_red");
$classnames = $selectOrders->getUniqueProducts("2013-09-01", "2013-09-30", "fall-2013");
foreach ($classnames as &$class) {
  $input = str_replace('&', '&#038;', $class);
  $orderIds = $selectOrders->getOrderIds($input, "2013-09-10", "2013-09-30");  
  echo $class . ": number of students = " . count($orderIds) . "\n";

  // if there are more than 0 total orders for this class, go through and print out the class list 
  if(count($orderIds) > 0) {
    $order_dict = $selectOrders->getOrderMetaData($orderIds);
    generate_csv($order_dict, $class);
    generate_md($order_dict, $class);
  }
}


?>