<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head><body>
<script>
    sessionStorage.removeItem('ts_username');
    window.location.href = 'login.php';
</script>
</body></html>
