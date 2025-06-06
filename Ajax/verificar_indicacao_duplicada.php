<?php
header('Content-Type: application/json; charset=UTF-8');
require '../Config/Database.php';

// Recebe parâmetros GET (esperamos plugin_id, serial e data no formato YYYY-MM-DD)
$plugin_id = isset($_GET['plugin_id'])   ? mysqli_real_escape_string($conn, $_GET['plugin_id'])   : '';
$serial    = isset($_GET['serial'])      ? mysqli_real_escape_string($conn, $_GET['serial'])      : '';
$data      = isset($_GET['data'])        ? mysqli_real_escape_string($conn, $_GET['data'])        : '';

// Resposta padrão
$response = ['exists' => false];

// Se faltar algum parâmetro, devolvemos exists=false e status 400
if (empty($plugin_id) || empty($serial) || empty($data)) {
    http_response_code(400);
    echo json_encode($response);
    exit();
}

// 1) Definimos $newDate como DATE a partir de $data (YYYY-MM-DD)
$newDate = "'{$data}'"; // manter em aspas simples para usar no SQL

// 2) Montamos o SELECT que, para cada registro i.data existente, calcula
//    first_of_month = DATE_FORMAT(i.data, '%Y-%m-01')
//    cycle_end       = DATE_ADD(first_of_month, INTERVAL 44 DAY)
//    e verifica se NEW_DATE está entre first_of_month e cycle_end.
//
//    Se existir ao menos 1 linha que satisfaça plugin_id + serial E nova data
//    dentro do ciclo, retornamos exists = true.

$sqlCheck = "
  SELECT COUNT(*) AS cnt
    FROM TB_INDICACAO AS i
   WHERE i.plugin_id = '{$plugin_id}'
     AND i.serial    = '{$serial}'
     AND {$newDate} BETWEEN
         DATE_FORMAT(i.data, '%Y-%m-01')
       AND DATE_ADD(DATE_FORMAT(i.data, '%Y-%m-01'), INTERVAL 44 DAY)
";

$resCheck = mysqli_query($conn, $sqlCheck);
if ($resCheck) {
    $rowCheck = mysqli_fetch_assoc($resCheck);
    if ((int)$rowCheck['cnt'] > 0) {
        $response['exists'] = true;
    }
}

echo json_encode($response);
exit();
