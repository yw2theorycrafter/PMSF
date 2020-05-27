<?php
require_once 'config/config.php';

destroyCookiesAndSessions($_COOKIE['LoginCookie']);
header('Location: .');
die;
