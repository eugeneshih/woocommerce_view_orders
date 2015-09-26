<?php

include 'select_orders.php';
include 'constants.php';

$start_date = START_DATE;
$end_date = END_DATE;

$selectOrders = new SelectOrders(DATABASE_HOST, DATABASE_USER, DATABASE_PWD, DATABASE_DEFAULT_DB);

$fields = array("student_first_name", "student_last_name", "grade", "teacher",
                "_billing_first_name", "_billing_last_name", "_billing_contactphone", 
                "_billing_email", "child_from_red");
$classnames = $selectOrders->getUniqueProducts($start_date, $end_date);
$allOrderIds = array();
foreach ($classnames as &$class) {
  $input = str_replace('&', '&#038;', $class);
  $orderIds = $selectOrders->getOrderIds($input, $start_date, $end_date);
  $allOrderIds = array_merge($allOrderIds, $orderIds);
  
  // if there are more than 0 total orders for this class, go through and print out the class list 
  if(count($orderIds) > 0) {
    echo $class . ": number of students = " . count($orderIds) . "\n";
    $order_dict = $selectOrders->getOrderMetaData($orderIds);
    generate_csv($order_dict, $class);
    generate_md($order_dict, $class);
    generate_attendance($order_dict, $class);
    generate_attendance_csv($order_dict, $class);
  }
}

// generate all emails
$fname = "emails.csv";
$fh = fopen($fname, "w");
$allEmails = array_unique($selectOrders->getOrderEmails($allOrderIds));
echo "number unique emails = " . count($allEmails);
foreach($allEmails as &$email) {
  fwrite($fh, $email . ",");
}
fclose($fh)

?>