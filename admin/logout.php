<?php
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['admin']);
redirect_to('login.php');
