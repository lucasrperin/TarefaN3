<?php
require 'config/database.php'; 

$sql = "SELECT * FROM TB_ANALISES";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
        <h1>TAREFAS N3</h1>
        <link rel="stylesheet" href = ./css/home.css>
    </header>
    <body>
        <table id="example"  style="width:100%">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Situação</th>
                    <th>Analista</th>
                    <th>Sistema</th>
                    <th>Status</th>
                    <th>Hora Início</th>
                    <th>Hora Fim</th>
                    <th>Total Horas</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result-> num_rows > 0) {

                    while($row = $result-> fetch_assoc())
                    {
                        echo "<tr>";
                        echo "<td>". $row["Descricao"]. "</td>";
                        echo "<td>". $row["idSituacao"]. "</td>";
                        echo "<td>". $row["idAnalista"]. "</td>";
                        echo "<td>". $row["idSistema"]. "</td>";
                        echo "<td>". $row["idStatus"]. "</td>";
                        echo "<td>". $row["idUsuario"]. "</td>";
                        echo "<td>". $row["Hora_ini"]. "</td>";
                        echo "<td>". $row["Hora_fim"]. "</td>";
                        echo "<td>". $row["Total_hora"]. "</td>";
                        echo "</tr>";
                    } 
                } else {
                    echo "<tr><td colspan='2'>Nenhum dado encontrado</td></tr>";
                }
                $conn->close();
                ?>
            </tbody>
        </table>         
     <body>

    <footer> 

    </footer>
    
</html>