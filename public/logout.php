<?php
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['customer']);
redirect_to('index.php');
