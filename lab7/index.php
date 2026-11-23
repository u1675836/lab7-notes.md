<?php
// Entry point: protected pages redirect anonymous visitors to the login page.
header('Location: php/dashboard.php', true, 302);
exit;
