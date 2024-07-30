<?php
// Incluye la configuración de Moodle.
require_once('../../config.php');

// Verifica si $CFG->dataroot está definido.
if (!isset($CFG->dataroot)) {
    error_log("dataroot no está configurado correctamente.");
    die("Error: dataroot no está configurado correctamente.");
}

// Define la ruta completa del archivo en moodledata.
$file = $CFG->dataroot . '/prueba_plugin.csv';

// Intenta abrir el archivo para escritura.
if (!$fp = fopen($file, 'w')) {
    error_log("Error al abrir el archivo CSV.");
    die("Error al abrir el archivo CSV.");
}

// Escribe datos en el archivo.
fwrite($fp, "Test, prueba de escritura\n");

// Cierra el archivo.
fclose($fp);

// Muestra un mensaje de éxito.
echo "Archivo escrito con éxito.";

