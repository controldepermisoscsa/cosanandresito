<?php
function is_logged_in(){
    session_start();
    return isset($_SESSION['user_id']);
}
?>