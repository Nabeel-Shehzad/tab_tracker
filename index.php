<?php
// Admin section index - redirect to login/dashboard
require_once 'common/config_mysql.php';

if (isAdmin()) {
  header('Location: dashboard.php');
} else {
  header('Location: login.php');
}
exit;
