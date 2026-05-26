<?php

require_once __DIR__ . '/functions.php';

function require_admin()
{
    if (empty($_SESSION['admin'])) {
        redirect_to('login.php');
    }
}

function require_customer()
{
    if (empty($_SESSION['customer'])) {
        redirect_to('login.php');
    }
}
