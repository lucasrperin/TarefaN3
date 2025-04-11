<?php
include '../Config/Database.php';

if (isset($_GET['data'])) {
    $data = $_GET['data']; // Formato: "YYYY-MM-DD"
    $duracao = isset($_GET['duracao']) ? intval($_GET['duracao']) : 30; // duração em minutos
    $safeData = mysqli_real_escape_string($conn, $data);
    
    // Consulta todos os agendamentos do dia que possam bloquear o horário,
    // filtrando pelos status que consideramos ativos (ex.: PENDENTE e EM ANDAMENTO)
    $query = "SELECT 
                STR_TO_DATE(CONCAT(data, ' ', hora), '%Y-%m-%d %H:%i:%s') AS inicio,
                DATE_ADD(STR_TO_DATE(CONCAT(data, ' ', hora), '%Y-%m-%d %H:%i:%s'), INTERVAL duracao MINUTE) AS fim
              FROM TB_TREINAMENTOS
              WHERE data = '$safeData'
                AND status IN ('PENDENTE', 'EM ANDAMENTO')";
    $result = mysqli_query($conn, $query);
    $appointments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = [
            'inicio' => $row['inicio'], // ex: "2023-04-11 09:00:00"
            'fim'    => $row['fim']     // ex: "2023-04-11 09:30:00"
        ];
    }
    
    // Define o período de funcionamento (ajuste conforme necessário)
    $inicioDisponibilidade = new DateTime($data . ' 08:00:00');
    $fimDisponibilidade    = new DateTime($data . ' 18:00:00');
    
    $availableSlots = [];
    // Escolha o incremento: aqui usamos 15 minutos para gerar mais opções
    $interval = new DateInterval("PT30M");
    
    $current = clone $inicioDisponibilidade;
    
    while ($current <= $fimDisponibilidade) {
        // Define o intervalo candidato para um agendamento
        $candidateStart = clone $current;
        $candidateEnd   = clone $candidateStart;
        $candidateEnd->modify("+{$duracao} minutes");
        
        // Se o agendamento candidato extrapola o horário de funcionamento, encerre a iteração
        if ($candidateEnd > $fimDisponibilidade) {
            break;
        }
        
        // Verifica se o intervalo do candidato se sobrepõe a algum agendamento já existente
        $overlap = false;
        foreach ($appointments as $appt) {
            $apptStart = new DateTime($appt['inicio']);
            $apptEnd   = new DateTime($appt['fim']);
            
            // Se o início do candidato for menor que o fim do agendamento existente E
            // o início do agendamento existente for menor que o fim do candidato
            // então há sobreposição.
            if ($candidateStart < $apptEnd && $apptStart < $candidateEnd) {
                $overlap = true;
                break;
            }
        }
        
        // Se não houver sobreposição, adiciona o horário à lista (formata como "H:i")
        if (!$overlap) {
            $availableSlots[] = $candidateStart->format('H:i');
        }
        
        // Incrementa o horário do candidato
        $current->add($interval);
    }
    
    // Retorna os horários disponíveis no formato JSON
    echo json_encode($availableSlots);
    exit();
}
?>
