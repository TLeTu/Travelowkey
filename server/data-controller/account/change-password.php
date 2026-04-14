<?php
require_once('../connect.php');

$action = $_POST['action'];

if($action == 'change-password'){
    $userId = $_POST['userId'];
    $newPassword = $_POST['newPassword'];
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $sql = "UPDATE user SET";

    $sql .= " `Password` = '$hashedPassword'";

    $sql .= " WHERE `Id` = '$userId';";

    if ($conn->query($sql) === TRUE) {
        echo "success";
    } else {
        echo "fail";
    }

    $conn->close();
}


if($action == 'check-old-password'){
    $userId = $_POST['userId'];
    $oldPassword = $_POST['oldPassword'];
    $hashedOldPassword = password_hash($oldPassword, PASSWORD_DEFAULT);
    $sql = "SELECT * FROM user WHERE `Id` = '$userId' AND `Password` = '$hashedOldPassword';";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        echo 'success';
    } else{
        echo 'fail';
    }

    $conn->close();
}