<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Block actions_recommender is defined here.
 *
 * @package     block_actions_recommender
 * @copyright   2023 Indira Lanza <lanza@uji.es>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



//require_once($CFG->dirroot . '/config.php');
require_once(__DIR__ . '/../../config.php');

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');

global $CFG, $OUTPUT; 




// Asegurarse de que el archivo no se pueda acceder directamente
//defined('MOODLE_INTERNAL') || die();


// Importa la clase csv_writer de la API de Moodle
require_once($CFG->libdir . '/csvlib.class.php');




class block_actions_recommender extends block_base {

    

    /**
     * Initializes class member variables.
     */
    public function init() {
        // Define a default title for the block.
        $this->title = get_string('pluginname', 'block_actions_recommender');
    }


    

/**
 * Obtiene el siguiente módulo en la jerarquía de un curso y lo formatea con un icono y un enlace.
 *
 * @param int $coursemoduleid El ID del último módulo visitado.
 * @param int $userid El ID del usuario autenticado.
 * @return string $mytext El contenido formateado con el enlace al siguiente módulo o un mensaje de error.
 */
    function getNextModuleFormatted($coursemoduleid, $userid) {
        global $DB, $OUTPUT, $COURSE;

        // Verifica si se ha pasado un ID de módulo válido
        if (!$coursemoduleid) {
            return 'No se ha proporcionado un ID válido de módulo.';
        }

        // Obtén el ID del curso actual
        $courseid = $COURSE->id;

        // Definir las columnas que queremos obtener
        $columns = array('id', 'module', 'instance', 'visible', 'deletioninprogress');

        // Prefijar cada columna con cmnext.
        $columns_prefixed = array_map(function($col) {
            return "cmnext." . $col;
        }, $columns);

        // Consulta SQL para obtener los módulos siguientes en la jerarquía
        $sql = "
            SELECT " . implode(', ', $columns_prefixed) . "
            FROM {course_modules} cm
            JOIN {course_sections} cs ON cm.section = cs.id
            JOIN {course_modules} cmnext ON FIND_IN_SET(cmnext.id, cs.sequence)
            WHERE cm.id = :coursemoduleid
            AND cmnext.course = :courseid
            AND cmnext.id > cm.id
            AND cmnext.deletioninprogress = 0 
            
        ";

        // Parámetros de la consulta
        $params = [
            'coursemoduleid' => $coursemoduleid,
            'userid' => $userid,
            'courseid' => $courseid,
        ];

        // Ejecuta la consulta para obtener todos los módulos
        $next_modules = $DB->get_records_sql($sql, $params);

        // Si no se encuentran módulos, devolver un mensaje de error
        if (!$next_modules) {
            return 'No se encontró un módulo siguiente o no tienes permisos para verlo.';
        }

        // Iterar sobre los módulos hasta encontrar uno visible y no en proceso de eliminación
        foreach ($next_modules as $next_module) {
            // Filtrar módulos que están ocultos o en proceso de eliminación
            if ($next_module->deletioninprogress == 0 && $next_module->visible == 1) {
                // Obtener el nombre del tipo de módulo desde la tabla 'modules'
                $module = $DB->get_record('modules', array('id' => $next_module->module), '*', MUST_EXIST);

                // Obtiene el nombre del curso-módulo usando el tipo de módulo
                $course_module = get_coursemodule_from_id($module->name, $next_module->id, 0, false, MUST_EXIST);

                // Formatear el nombre del recurso
                $resource_name = format_string($course_module->name);

                // Crear el enlace basado en el nombre del tipo de módulo y el ID del módulo
                $resource_link = new moodle_url('/mod/' . $module->name . '/view.php', array('id' => $course_module->id));

                // Obtener el icono del módulo
                $module_icon = $OUTPUT->pix_icon('icon', '', $module->name, ['class' => 'activityicon']);

                // Construir el enlace con el icono y el nombre formateado
                $mytext = $module_icon . ' <a href="' . $resource_link . '">' . $resource_name . '</a> <br>';

                // Devolver el contenido formateado
                return $mytext;
            }
        }

        // Si no se encontró ningún módulo visible y no eliminado, devolver un mensaje de error.
        return 'No se encontró un módulo visible y no en proceso de eliminación.';
    }




    /**
     * Ejecuta una consulta para obtener registros del sistema y guarda los resultados en un archivo CSV.
     * Se ejecuta solo una vez al día o si el archivo CSV no existe.
     * @return int Retorna 1 si la operación fue exitosa y se creó el archivo CSV, 0 si el archivo CSV ya existía y no se actualizó, -1 si hubo un error al intentar crear o actualizar el archivo CSV.
     */
    public function execute_log_query_once_per_day_or_first_time() {
        // Ruta completa para el archivo CSV en moodledata
        global $CFG;
        $courseid = 2;
        $csv_file = $CFG->dataroot . '/logs_courseid_'.$courseid.'.csv';
        // ID del rol de estudiante
        $roleid = 5;


        if (!isset($CFG->dataroot)) {
            error_log("dataroot no está configurado correctamente aaa.");
            
            die("Error: dataroot no está configurado correctamente aaaa.".$CFG->dataroot." en execute_log_query_once_per_day_or_first_time ");
        }
     
    
        // Verifica si el archivo CSV existe
        if (!file_exists($csv_file)) {
            // Si el archivo CSV no existe, ejecuta la consulta y guarda los resultados en el archivo
            if ($this->execute_log_query_and_save_to_csv($csv_file, $roleid)) {
                return 1; // Operación exitosa, archivo CSV creado
            } else {
                return -1; // Error al intentar crear el archivo CSV
            }
        } else {
            // Obtiene la fecha de la última modificación del archivo CSV
            $last_modified_date = date('Y-m-d', filemtime($csv_file));
    
            // Solo para pruebas de escritura del archivo, simulo que tiene fecha de n días atrás
            $n = 3; // número de días a restar //modificar a 0 si no quiero actualizar hoy el fichero
            $date = new DateTime($last_modified_date);
            $date->modify("-$n days");
            $modified_date = $date->format('Y-m-d');
    
            // Obtiene la fecha actual
            $current_date = date('Y-m-d');
    
            // Si la última modificación no fue hoy, ejecuta la consulta y actualiza el archivo
            if ($modified_date != $current_date) { // Compara la fecha modificada con la fecha actual
                if ($this->execute_log_query_and_save_to_csv($csv_file, $roleid)) {
                    return 1; // Operación exitosa, archivo CSV actualizado
                } else {
                    return -1; // Error al intentar actualizar el archivo CSV
                }
            } else {
                return 0; // Archivo CSV ya existía de fecha hoy y no se actualizó
            }
        }
    }
    



    public function queryDBAndSendLogs($current_userid, $roleid = null) {

        global $DB;
        global $CFG;
        global $COURSE;

        // Define las columnas que quieres en el archivo CSV
        $columns = array('timecreated', 'userid', 'relateduserid', 'contextid', 'contextinstanceid', 'contextlevel', 'component', 'eventname', 'other', 'origin');

        // ID del curso con el que se interactuó (en este caso, curso con ID 2)
        //$courseid = 2;

        // Obtener el "Course ID number" del curso actual. "Course ID number" es el identificador que se asigna de forma manual para que moodle pueda interactuar con sistemas externos.
        $course_id_number = $COURSE->idnumber; #El id=1 es el identificador que he asignado al curso de programación virtual. Lo usaré para poder consultar los logs de los usuarios dentro del grupo "Expderimental Group" para entrenar el recomendador

        //Obtener el id (dinamico que asigna moodle cuando se crea el curso) del curso actual
        $courseid=$COURSE->id;

        $csv_file = $CFG->dataroot . '/logs_courseid_'.$courseid.'.csv';


        if ($course_id_number==1){//Curso programación virtual
            
            // Obtener los grupos del usuario en el curso
            $groups = groups_get_all_groups($courseid, $current_userid);

            if ($groups) {
                foreach ($groups as $group) {
                    // Obtener el Group ID number (idnumber) del grupo
                    $groupidnumber = $group->idnumber;

                    if ($groupidnumber=='002'){
                        //Consulta para los logs unicamente de los usuarios del grupo de control experimental del curso 
                        // Construcción de la consulta SQL
                        $sql = "SELECT l." . implode(', l.', $columns) . " 
                        FROM {logstore_standard_log} l
                        JOIN {role_assignments} ra ON ra.userid = l.userid
                        JOIN {context} ctx ON ctx.id = ra.contextid
                        JOIN {course_modules} cm ON cm.id = l.contextinstanceid
                        JOIN {groups_members} gm ON gm.userid = l.userid
                        JOIN {groups} g ON g.id = gm.groupid
                        WHERE cm.course = :courseid 
                        AND l.contextlevel = 70
                        AND cm.deletioninprogress = 0
                        AND g.idnumber = :groupidnumber
                        AND cm.visible = 1"; // Solo incluir módulos visibles para el usuario

                        // Parámetros de la consulta
                        $params = array('courseid' => $courseid, 'groupidnumber' => '002');


                    }
                    else {
                        return false; //No pertenece al grupo experimental, por tanto no se entrena ni se ofrecen recomendaciones
                    }
                    
                    
                    
                }
            } 

        } else {
            
            // Construcción de la consulta SQL
            $sql = "SELECT l." . implode(', l.', $columns) . " 
            FROM {logstore_standard_log} l
            JOIN {role_assignments} ra ON ra.userid = l.userid
            JOIN {context} ctx ON ctx.id = ra.contextid
            JOIN {course_modules} cm ON cm.id = l.contextinstanceid
            WHERE cm.course = :courseid 
            AND l.contextlevel=70
            AND cm.deletioninprogress = 0
            AND cm.visible = 1"; // Solo incluir módulos visibles para el usuario

            // Parámetros de la consulta
            $params = array('courseid' => $courseid);

        }

         
        

        // Agregar la condición para filtrar por rol si se especifica
        if ($roleid !== null) {
            $sql .= " AND ra.roleid = :roleid";
            $params['roleid'] = $roleid;
        }

        // Ordenar por tiempo creado
        $sql .= " ORDER BY l.timecreated DESC";

        try {
            // Obtiene los registros del sistema
            $logs = $DB->get_records_sql($sql, $params);
        } catch (dml_exception $e) {
            // Manejo del error de la base de datos
            debugging("Error al ejecutar la consulta SQL: " . $e->getMessage());
            return false;
        }



        // Ordenar por tiempo creado
        $sql2 = $sql . " LIMIT 10";

        
                

        try {
            // Obtiene los registros del sistema
            $logs_2_write = $DB->get_records_sql($sql2, $params);
            // Depuración: Imprimir los registros recuperados
            /*echo '<div style="position:relative; z-index:1000; background-color:#fff; border:1px solid #000;">';
            echo '<pre>';
            print_r($logs_2_write);
            echo '</pre>';
            echo '</div>';*/
        } catch (dml_exception $e) {
            // Manejo del error de la base de datos
            debugging("Error al ejecutar la consulta SQL: " . $e->getMessage());

             

            return false;
        }

        // Verifica si se obtuvieron logs
        if (!$logs) {
            
            return false; // No se encontraron logs
        }
        

        // Verifica si se obtuvieron logs
        if (!$logs_2_write) { 

           // Depuración
            //echo '<div style="position:relative; z-index:1000; background-color:#fff; border:1px solid #000;">';
            //echo '<pre>';
            //print_r($sql2);
            //echo '</pre>';
            //echo '</div>';
            
            return false; // No se encontraron logs
        }
        
       

       // Leer el archivo CSV existente
        $existing_logs = [];
        if (file_exists($csv_file)) {
            if (($fp = fopen($csv_file, 'r')) !== false) {
                // Saltar la primera línea si contiene encabezados
                $first_line = fgetcsv($fp);
                while (($data = fgetcsv($fp)) !== false) {
                    $existing_logs[] = $data;
                }
                fclose($fp);
            }
        }

        // Preparar los registros para escribir, evitando duplicados
        $logs_to_write = [];
        foreach ($logs_2_write as $log) {
            $log_data = array(
                $log->timecreated,
                $log->userid,
                $log->relateduserid,
                $log->contextid,
                $log->contextinstanceid,
                $log->contextlevel,
                $log->component,
                $log->eventname,
                $log->other,
                $log->origin
            );

            // Comprobación detallada para evitar duplicados
            $is_duplicate = false;
            foreach ($existing_logs as $existing_log) {
                if (count($existing_log) != count($log_data)) {
                    continue; // Saltar si el número de columnas no coincide
                }

                $match = true;
                for ($i = 0; $i < count($log_data); $i++) {
                    if (trim($log_data[$i]) !== trim($existing_log[$i])) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    $is_duplicate = true;
                    break;
                }
            }

            // Añadir solo registros nuevos
            if (!$is_duplicate) {
                $logs_to_write[] = $log_data;
            }
        }

        // Si no hay nuevos registros, no hacer nada
        if (empty($logs_to_write)) {
            return false;
        } else {
            // Añade al arreglo $logs_to_write los valores obtenidos en logs_2_write
            $logs_to_write = array_merge($logs_to_write, $existing_logs);

            // Deja en $logs_to_write solo los primeros 10, que corresponden a los logs más recientes
            $logs_to_write = array_slice($logs_to_write, 0, 10);
        }

        // Escribir en el archivo CSV (sobrescribiendo)
        $fp = fopen($csv_file, 'w');
        if (!$fp) {
            return false;
        }

        // Escribir cabeceras solo si el archivo estaba vacío
        if (filesize($csv_file) == 0) {
            fputcsv($fp, $columns);
        }

        // Escribir los registros
        foreach ($logs_to_write as $log_data) {
            if (!fputcsv($fp, $log_data)) {
                fclose($fp);
                return false;
            }
        }

        // Cerrar el archivo CSV
        fclose($fp);


        // Enviar los logs al servicio web FastAPI
        //$logs_json = json_encode($logs_to_send);

        // Ahora, después de guardar los ultimos 10 registros en el archivo CSV, envía todos los logs historicos al servicio web FastAPI
        $logs_data = array();
        foreach ($logs as $log) {
            $logs_data[] = array(
                'timecreated' => $log->timecreated,
                'userid' => $log->userid,                
                'contextid' => $log->contextid,
                'contextinstanceid' => $log->contextinstanceid,
                'contextlevel' => $log->contextlevel,
                'component' => $log->component,
                'eventname' => $log->eventname,
                'other' => $log->other
                
            );
        }


        $logs_json = json_encode($logs_data);


        $url = get_config('block_actions_recommender', 'serverurl') . '/logs/';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $logs_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($logs_json) // Asegura que el Content-Length esté definido
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        // Check for cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("cURL Error: " . $error);
            return false;
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            error_log("Logs sent to FastAPI successfully.");
            return true;
        } else {
            error_log("Error sending logs to FastAPI. HTTP Code: " . $http_code . " Response: " . $response);
            return false;
        }

        return true; // Éxito al guardar los logs
    }

    




        /**
     * Ejecuta una consulta para obtener registros del sistema y guarda los resultados en un archivo CSV.
     *
     * @param string $csv_file Ruta completa para guardar el archivo CSV
     * @param int|null $roleid ID del rol para filtrar los registros, null para no filtrar por rol.
     * @return bool Retorna true si la operación fue exitosa, false en caso contrario.
     */
    public function execute_log_query_and_save_to_csv($csv_file, $roleid = null) {
        global $DB;
        global $CFG;

        // Define las columnas que quieres en el archivo CSV
        $columns = array('timecreated', 'userid', 'relateduserid', 'contextid','contextinstanceid', 'contextlevel', 'component', 'eventname', 'other', 'origin');

        // ID del curso con el que se interactuó (en este caso, curso con ID 2)
        $courseid = 2;

        // Construcción de la consulta SQL
        $sql = "SELECT l." . implode(', l.', $columns) . " 
                FROM {logstore_standard_log} l
                JOIN {role_assignments} ra ON ra.userid = l.userid
                JOIN {context} ctx ON ctx.id = ra.contextid
                JOIN {course_modules} cm ON cm.id = l.contextinstanceid
                WHERE cm.course = :courseid 
                AND l.contextlevel=70
                AND cm.deletioninprogress = 0"; //l.contextlevel=70 indica que se trata de un módulo. // cm.deletioninprogress = 0 Asegurarse de que el módulo no esté en proceso de eliminación

        // Parámetros de la consulta
        $params = array('courseid' => $courseid);

        // Agregar la condición para filtrar por rol si se especifica
        if ($roleid !== null) {
            $sql .= " AND ra.roleid = :roleid";
            $params['roleid'] = $roleid;
        }

        // Ordenar por tiempo creado y limitar la cantidad de resultados
        #$sql .= " ORDER BY l.timecreated DESC LIMIT 100";
        $sql .= " ORDER BY l.timecreated DESC ";

        try {
            // Obtiene los registros del sistema
            $logs = $DB->get_records_sql($sql, $params);
        } catch (dml_exception $e) {
            // Manejo del error de la base de datos
            debugging("Error al ejecutar la consulta SQL: " . $e->getMessage());
            return false;
        }

        // Verifica si se obtuvieron logs
        if (!$logs) {
            return false; // No se encontraron logs
        }

        // Abre un archivo CSV para escribir los registros
        $fp = fopen($csv_file, 'w');
        if (!$fp) {
            return false; // Error al abrir el archivo CSV
        }

        // Escribe las cabeceras del CSV
        if (!fputcsv($fp, $columns)) {
            fclose($fp);
            return false; // Error al escribir las cabeceras del CSV
        }

        // Itera sobre los registros y escribe cada uno en el archivo CSV
        foreach ($logs as $log) {
            $data = array(
                $log->timecreated,
                $log->userid,
                $log->relateduserid,
                $log->contextid,
                $log->contextinstanceid,
                $log->contextlevel,
                $log->component,
                $log->eventname,
                $log->other,
                $log->origin
            );
            if (!fputcsv($fp, $data)) {
                fclose($fp);
                return false; // Error al escribir un registro en el CSV
            }
        }

        // Cierra el archivo CSV
        fclose($fp);



         // Ahora, después de guardar los registros en el archivo CSV, envía los logs al servicio web FastAPI
        $logs_data = array();
        foreach ($logs as $log) {
            $logs_data[] = array(
                'timecreated' => $log->timecreated,
                'userid' => $log->userid,                
                'contextid' => $log->contextid,
                'contextinstanceid' => $log->contextinstanceid,
                'contextlevel' => $log->contextlevel,
                'component' => $log->component,
                'eventname' => $log->eventname,
                'other' => $log->other
                
            );
        }
        $logs_json = json_encode($logs_data);

        #$url = 'http://150.128.81.34:8000/logs/';
        $url = get_config('block_actions_recommender', 'serverurl') . '/logs/';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $logs_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($logs_json) // Asegura que el Content-Length esté definido
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        
        // Check for cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("cURL Error: " . $error);
            return false;
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            error_log("Logs sent to FastAPI successfully.");
            return true;
        } else {
            error_log("Error sending logs to FastAPI. HTTP Code: " . $http_code . " Response: " . $response);
            return false;
        }
        



        return true; // Éxito al guardar los logs
    }




    //$learning_object_id es El ID del objeto de aprendizaje que deseas pasar al servicio web
    public function recommended_list($learning_object_id ) {        

        #$url = "http://150.128.81.34:8000/recommend/" . $learning_object_id;
        $url = get_config('block_actions_recommender', 'serverurl') . '/recommend/'. $learning_object_id;
        
        //$url = "http://150.128.81.34:8000/recommend/2";

        // Inicializar cURL
        $curl = curl_init();

        // Configurar la solicitud cURL
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Realizar la solicitud GET al servicio web
        $response = curl_exec($curl);

        // Verificar si hay errores
        if(curl_errno($curl)){
            echo 'Error:' . curl_error($curl);
        }

        // Cerrar la conexión cURL
        curl_close($curl);

        // Decodificar la respuesta JSON en un array asociativo
        $resultado = json_decode($response, true);

        // Obtener los valores de la clave "IDs" y guardarlos en una lista
        $ids = $resultado['IDs:'];

        // Hacer lo que necesites con la lista de IDs
        //print_r($ids);

        return $ids;

    }


    function generate_unique_random_numbers($min, $max, $count) {
        // Verifica que el rango de números posibles es suficiente para generar la cantidad deseada de números únicos
        if (($max - $min + 1) < $count) {
            throw new Exception("Rango insuficiente para generar la cantidad deseada de números únicos.");
        }
    
        $numbers = [];
    
        // Genera números aleatorios únicos
        while (count($numbers) < $count) {
            $num = rand($min, $max);
            if (!in_array($num, $numbers)) {
                $numbers[] = $num;
            }
        }
    
        return $numbers;
    }


    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        require_login(); // Asegura que el usuario esté autenticado

        global $DB;
        global $USER;
        global $PAGE;//lo agregue nuevo
        global $CFG, $OUTPUT;
        global $COURSE;

        $this->content = new stdClass();
        $this->content->items = array(); // No items by default.
        $this->content->icons = array(); // No icons by default.
        //$this->content->footer = 'Este es el contenido del pie de pagina'; // Empty footer by default.

        $user_id = $USER->id; // Obtener el ID del usuario autenticado        

        $text = '';


        $course_id_number = $COURSE->idnumber;
        


        if ($course_id_number=='1'){ //Si el curso es el de programación virtual hago las sigueintes comprobaciones 
             // Obtén el ID del grupo objetivo

            
        
            $target_groupid = '001'; //'001' es el id del control group en el curso de programacion virtual.  ID (definido de forma manual para interaccion con sistemas externos) del grupo que quieres restringir

                // Consulta para verificar si el usuario pertenece a ese grupo en el curso actual
            $sql = "SELECT gm.*
                        FROM {groups_members} gm
                        JOIN {groups} g ON g.id = gm.groupid
                        WHERE gm.userid = :userid
                        AND g.courseid = :courseid
                        AND g.idnumber = :groupid";

            $params = array(
                    'userid' => $USER->id,
                    'courseid' => $COURSE->id,
                    'groupid' => $target_groupid
                );

                // Ejecuta la consulta
            $group_member = $DB->get_record_sql($sql, $params);

            // Agregar depuración
           /* if ($group_member) {
                $text.=  "User is in group 001";
            } else {
                $text .= "User is not in group 001.<br>";
                
            }*/

                // Si el usuario pertenece al grupo, no mostrar el bloque
            if ($group_member) {
                $text = 'es miembro del grupo con id 001';
                return null;
                }


        }else {
            $text = 'NO entro al inf..';
        }



        

        // Obtener la marca de tiempo de la última modificación del archivo script1.js
        //$script1_version = filemtime(__DIR__ . '/scripts1.js');
        //$PAGE->requires->js('/blocks/actions_recommender/scripts1.js?v=' . $script1_version);

        // Ejecuta la función para obtener y guardar los registros del sistema en un archivo CSV una vez al día o la primera vez
        ######$aresultado=$this->execute_log_query_once_per_day_or_first_time();

                // Ejecuta la función para obtener y guardar los registros del sistema en un archivo CSV una vez al día o la primera vez
       /* if ($aresultado==1) {
            // Si la función se ejecuta correctamente, imprime un mensaje de éxito
            $text .= "Logs guardados en el archivo: " . $CFG->dataroot . '/logs_courseid_2.csv' . "<br>";
        } else  if ($aresultado==-1){
            // Si la función no se ejecuta correctamente, imprime un mensaje de error
            $text .= "Error al guardar los logs en el archivo CSV.<br>";
        } else  if ($aresultado==0){
            // Si ya se actualizo el fichero en el día, no se vuelve a ejecutar la consulta a los logs de moodle
            $text .= "No se actualizaron los logs en el archivo CSV.<br>";
        } */

        // ID del rol de estudiante. Hay que modificar el codigo para que tambien tenga en cuenta a que grupo de control pertenece el estudiante, ya que cada grupo de control tiene acceso a recursos o modulos distintos...
        $roleid = 5;
        
        $aResultado2=$this->queryDBAndSendLogs($user_id, $roleid);

        //Depuración
        /*if ($aResultado2==True) {
            // Si la función se ejecuta correctamente, imprime un mensaje de éxito
            $text .= "Logs guardados correctamente en el archivo: " . $CFG->dataroot . '/logs_courseid_2.csv' . "<br>";
        } else  if ($aResultado2==False){
            // Si la función no se ejecuta correctamente o no existen nuevos registros de otros usuarios en la BD
            $text .= "No se han guardado nuevos logs en el archivo CSV del servidor de Moode.<br>";
        } */



        // Obtener el último módulo visto por el usuario autenticado
        //contextlevel = 70  indica que se trata de un módulo o recurso del curso
        $last_viewed_module = $DB->get_records_sql("
        SELECT contextinstanceid as coursemoduleid 
        FROM {logstore_standard_log}
        WHERE userid = :userid
        AND contextlevel = 70
        AND action = :action
        ORDER BY timecreated DESC
        LIMIT 1
        ", ['userid' => $user_id, 'action' => 'viewed']);


        $mytext = '';


            // Verificar si se obtuvieron resultados en la consulta
        if (!empty($last_viewed_module)) {
            // Obtener el valor de coursemoduleid del primer resultado
            $coursemoduleid = reset($last_viewed_module)->coursemoduleid;

            // Agregar a la variable $mytext
           // $mytext .= " Obtener el ultimo modulo visto por el usuario autenticado\n";
          // $mytext .= "\$last_viewed_module = " . $coursemoduleid . ";  <br>";
        } else {
            // Manejar el caso en que no se encuentren resultados
            $mytext .= " No se encontraron módulos completados por el usuario.";
        }    


        if ($last_viewed_module) {
            // Hacer la recomendación de un camino con n elementos y mostrarlo en el bloque.
            //la recomendacion en base al id del modulo 
            
            /***LLAMAR AL SERVICIO WEB DE RECOMENDACIONES***/
            //$recommended_list=$this->recommended_list($coursemoduleid);


            // recommended_list es una lista de ids de course modules
            //$recommended_list = [3, 4]; // modificar esta línea y agregar el servicio del recomendador en base al último elemento visitado last_completed_module
            //$recommended_list = $this->generate_unique_random_numbers(3, 23, 3);

            //$coursemoduleid=4;
            $recommended_list = $this->recommended_list($coursemoduleid);
            //var_dump($recommended_list); // Depuración

            //die("Error: dataroot no está configurado correctamente aaaa.".recommended_list." en execute_log_query_once_per_day_or_first_time ");

            //Cargar las recomendaciones en el bloque
        
            //$PAGE->requires->js_init_call('fetchRecommendations');

            if ($recommended_list==[]){
                $mytext = 'There is not enough data for the Recommend algorithm. Please visit the next course item: ';

                //Esto puede pasar cuando no hay suficiente información para el pathrecomender, entonces simplemente se recomienda el item (módulo) siguiente de la jerarquía el curso en Moodle
                //Recomendar módulo siguiente en el curso

                $mytext.= $this->getNextModuleFormatted($coursemoduleid,$user_id);


                $this->content->text = $mytext;
                return $this->content;

            }


            
            // Recuperar un objeto de tipo lo que recupera la consulta a la BD
            $recommended_modules = $DB->get_records_sql("
            SELECT  cm.id, cm.module, m.name as modulename
            FROM {course_modules} AS cm
            JOIN {modules} AS m ON cm.module = m.id
            WHERE cm.id IN (" . implode(',', $recommended_list) . ")
            ");

 

            // Construct the content text with the last completed module of the actual user
            foreach ($recommended_modules as $resource) {
                $course_module = get_coursemodule_from_id($resource->modulename, $resource->id, 0, false, MUST_EXIST);
                $course = $DB->get_record('course', array('id' => $course_module->course));
                $resource_name = format_string($course_module->name);
                $resource_link = new moodle_url('/mod/' . $resource->modulename . '/view.php', array('id' => $course_module->id));

                // Obtener el icono del módulo
                $module_icon = $OUTPUT->pix_icon('icon', '', $resource->modulename, ['class' => 'activityicon']);

                // Construir el enlace con el icono y el nombre formateado
                $mytext .=  $module_icon . ' <a href="' . $resource_link . '">' . $resource_name . '</a> <br>';
            }


        
            //$mytext .= ' ENTRÓ AL IF:';
        } else {
            $mytext = 'No se encontraron módulos recomendados.';
        }
        
        

        $text.= $mytext;


        // Set the content text.
        if (!empty($text)) {
            $this->content->text = $text;
        } else {
            // Provide a default text if no resources viewed.
            $text = 'No se han visto recursos recientemente.';
            $this->content->text = $text;
        }



   

        return $this->content;
    }





    /**
     * Defines configuration data.
     *
     * The function is called immediately after init().
     */
    public function specialization() {
        // Load user-defined title, or use a default if not set.
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_actions_recommender');
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        // Define the formats where the block is applicable.
        return array(
            'all' => true, // Block is applicable to all pages.
        );
    }



    public function html_attributes() {
        // Get default values.
        $attributes = parent::html_attributes();
        // Append our class to class attribute.
        $attributes['class'] .= ' block_'. $this->name();
        return $attributes;
    }


    public function has_config() {
        return true;
    }
}
