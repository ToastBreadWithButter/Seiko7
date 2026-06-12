<?php
require_once __DIR__ . '/../app/bootstrap.php';
session_destroy();
header('Location: ' . url('index.php'));
exit;

