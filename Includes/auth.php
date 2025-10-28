<?php
// Start session before requiring files that may accidentally emit output
// so headers are still modifiable (session_start() must run before output).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Config/Database.php';

// Do not emit any output from this include file — doing so will
// prevent header()/session_start() from working. Use error_log()
// for server-side debug and expose $USER_ID_FOR_JS for templates.

// Variable templates may echo to expose to JS where appropriate:
//   <script>window.USER_ID = <?php echo $USER_ID_FOR_JS ?? 'null'; 
?>
