<?php

$APP_NAME = getenv('APP_NAME') ?: 'i-Reserva';
$APP_URL = rtrim(getenv('APP_URL') ?: '', '/');
$MAIL_FROM = getenv('MAIL_FROM') ?: 'reservas@localhost';
$MAIL_FROM_NAME = getenv('MAIL_FROM_NAME') ?: $APP_NAME;
$MAIL_ADMIN_TO = getenv('MAIL_ADMIN_TO') ?: '';
