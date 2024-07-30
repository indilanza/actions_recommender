<?php
$log_file = '/var/log/apache2/error.log';
$lines = 10;

if (file_exists($log_file)) {
    $log_content = shell_exec("tail -n $lines $log_file");
    echo nl2br($log_content);
} else {
    echo "No se pudo encontrar el archivo de logs.";
}

