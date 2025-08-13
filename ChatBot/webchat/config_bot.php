<?php
require_once __DIR__ . '/../../Includes/auth.php';
require_once __DIR__ . '/../../Config/Database.php';

$usuario_nome = $_SESSION['usuario_nome'] ?? '';

// busca última data de geração dos artigos
$res = $conn->query("SELECT MAX(data_geracao) AS ultima FROM TB_EMBEDDINGS where tipo = 'artigos'");
$row = $res->fetch_assoc();
$ultima = $row['ultima']
    ? date('d/m/Y H:i:s', strtotime($row['ultima']))
    : 'Nunca';

// busca última data de geração
$resvideo = $conn->query("SELECT MAX(data_geracao) AS ultima FROM TB_EMBEDDINGS where tipo = 'video'");
$rowvideo = $resvideo->fetch_assoc();
$ultimavideo = $rowvideo['ultima']
    ? date('d/m/Y H:i:s', strtotime($rowvideo['ultima']))
    : 'Nunca';    

// busca histórico (últimos 50)
$hist = $conn->query("
  SELECT id, titulo, origem, link, arquivo_json, status, data_inicio, data_fim
    FROM TB_TREINAMENTOS_BOT
ORDER BY id DESC
   LIMIT 50
");
$historico = [];
if ($hist) while ($r = $hist->fetch_assoc()) $historico[] = $r;

// ====== Resposta parcial para atualização do histórico ======
if (isset($_SERVER['HTTP_X_PARTIAL']) && $_SERVER['HTTP_X_PARTIAL'] === 'historico') {
  $hist2 = $conn->query("
    SELECT id, titulo, origem, link, arquivo_json, status, data_inicio, data_fim
      FROM TB_TREINAMENTOS_BOT
  ORDER BY id DESC
     LIMIT 50
  ");
  echo '<tbody id="histBody">';
  if ($hist2 && $hist2->num_rows) {
    while ($h = $hist2->fetch_assoc()) {
      $badgeClass = $h['status'] === 'CONCLUIDO' ? 'badge-success' : ($h['status'] === 'ERRO' ? 'badge-danger' : 'badge-warning');
      echo '<tr class="row-hover">';
      echo '<td class="text-muted">'.(int)$h['id'].'</td>';
      echo '<td>'.htmlspecialchars($h['titulo'] ?? '').'</td>';
      echo '<td>'.($h['origem'] === 'url' ? '<span class="badge badge-soft-info">URL</span>' : '<span class="badge badge-soft-secondary">Upload</span>').'</td>';
      echo '<td>'.(!empty($h['link']) ? '<a href="'.htmlspecialchars($h['link']).'" target="_blank" rel="noopener" class="link-muted">abrir</a>' : '<span class="text-muted">—</span>').'</td>';
      echo '<td>'.(!empty($h['arquivo_json']) ? '<a href="'.htmlspecialchars($h['arquivo_json']).'" target="_blank" rel="noopener" class="link-muted">baixar</a>' : '<span class="text-muted">—</span>').'</td>';
      echo '<td><span class="badge '.$badgeClass.'">'.htmlspecialchars($h['status']).'</span></td>';
      echo '<td>'.($h['data_inicio'] ? date('d/m/Y H:i', strtotime($h['data_inicio'])) : '—').'</td>';
      echo '<td>'.($h['data_fim'] ? date('d/m/Y H:i', strtotime($h['data_fim'])) : '—').'</td>';
      echo '<td>';
      if ($h['status'] !== 'PROCESSANDO') {
        echo '<button type="button" class="btn btn-sm btn-outline-secondary btn-log" data-id="'.(int)$h['id'].'"><i class="bi bi-journal-text me-1"></i>Ver log</button>';
      } else {
        echo '<span class="text-muted d-inline-flex align-items-center"><span class="spinner-border spinner-border-sm me-2"></span>Aguardando</span>';
      }
      echo '</td>';
      echo '</tr>';
    }
  } else {
    echo '<tr><td colspan="9" class="text-muted">Sem registros.</td></tr>';
  }
  echo '</tbody>';
  exit;
}

// ====== Resposta parcial para obter o log de um ID ======
if (isset($_SERVER['HTTP_X_PARTIAL']) && $_SERVER['HTTP_X_PARTIAL'] === 'log') {
  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) { http_response_code(400); echo "ID inválido"; exit; }
  $q = $conn->prepare("SELECT log FROM TB_TREINAMENTOS_BOT WHERE id=?");
  $q->bind_param('i', $id);
  $q->execute();
  $res = $q->get_result();
  $log = '';
  if ($res && $row = $res->fetch_assoc()) $log = $row['log'] ?? '';
  header('Content-Type: text/plain; charset=utf-8');
  echo $log;
  exit;
}

// === Helpers para converter caminho de arquivo em URL pública
function fs_to_url(string $abs): string {
  $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
  $abs = realpath($abs);
  if (!$doc || !$abs) return '';
  $doc = rtrim(str_replace('\\','/',$doc),'/');
  $abs = str_replace('\\','/',$abs);
  if (strpos($abs,$doc) !== 0) return '';
  $rel = substr($abs, strlen($doc));
  return $rel === '' ? '/' : ($rel[0] === '/' ? $rel : '/'.$rel);
}

// Caminhos absolutos no filesystem
$execEtapasFs = realpath(__DIR__ . '/../../ChatBot/webchat/executar_etapas.php');
$chamarProcFs = realpath(__DIR__ . '/../../ChatBot/scripts/video/chamar_processa_video.php');

// Converte FS -> URL pública (a partir do DOCUMENT_ROOT)
$execEtapasUrl  = fs_to_url($execEtapasFs);
$chamarProcUrl  = fs_to_url($chamarProcFs);


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuração do Chatbot</title>
    <!-- Font Awesome e Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <!-- CSS local -->
    <link rel="stylesheet" href="../../Public/config_bot.css">
    <link rel="icon" href="../../Public/Image/LogoTituto.png" type="image/png">
</head>
<body
  data-upload-video-emb="<?= htmlspecialchars($execEtapasUrl, ENT_QUOTES, 'UTF-8') ?>"
  data-chamar-processa-video="<?= htmlspecialchars($chamarProcUrl, ENT_QUOTES, 'UTF-8') ?>"
  data-theme=""
>

<div class="d-flex-wrapper">
  <?php include __DIR__ . '/../../components/sidebar_bot.php'; ?>

  <div class="w-100 flex-grow-1 d-flex flex-column">
    <!-- Header (não alterado) -->
    <div class="header d-flex justify-content-between align-items-center p-3 border-bottom">
      <h3 class="mb-0"><i class="bi bi-gear me-2"></i>Configuração do Chatbot</h3>
      <div class="user-info d-flex align-items-center gap-2">
        <span>Bem-vindo, <strong><?= htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8') ?></strong>!</span>
        <a href="/TarefaN3/Views/logout.php" class="btn btn-danger btn-sm">
          <i class="fa-solid fa-right-from-bracket me-1"></i> Sair
        </a>
        <button class="theme-toggle-btn btn btn-outline-secondary btn-sm" id="themeBtn" title="Alternar tema">
          <i class="fa fa-moon"></i>
        </button>
      </div>
    </div>

    <!-- Conteúdo principal (novo layout) -->
    <div class="page-content p-4">
        <div class="section mb-4">
            <div class="section-title">
                <div class="ico"><i class="fa fa-database"></i></div>
                <div>Embeddings Artigos</div>
            </div>
            <div class="stat mb-3">
                <div>
                    <div class="subtle">Última geração de embeddings</div>
                    <div class="fs-5 fw-bold"><?= htmlspecialchars($ultima) ?></div>
                </div>
                <button class="btn btn-primary btn-pill" id="btnExecutar">
                    <i class="fa fa-bolt me-1"></i> Gerar Novos Embeddings
                </button>
            </div>
            <div id="log" class="log-box" style="display:none;"></div>
            <div class="section-title">
                <div class="ico"><i class="fa fa-database"></i></div>
                <div>Embeddings Vídeos</div>
            </div>
            <div class="stat mb-3">
                <div>
                    <div class="subtle">Última geração de embeddings</div>
                    <div class="fs-5 fw-bold" id="ultimaVideoText"><?= htmlspecialchars($ultimavideo) ?></div>
                </div>
                <button type="button" class="btn btn-primary btn-pill" id="btnUploadVideos" title="Publica os embeddings das transcrições no Supabase">
                    <i class="fa fa-cloud-arrow-up me-1"></i> Publicar Embeddings
                </button>
                <div id="uploadVideosStatus" class="alert alert-success py-2 px-3 mt-2 d-none"></div>
            </div>
            <div id="logVideos" class="log-box" style="display:none;"></div>
        </div>

      <div class="split mb-4">
        <div class="section">
          <div class="section-title">
            <div class="ico"><i class="bi bi-easel2"></i></div>
            <div>Treinamento por Vídeo</div>
          </div>

          <form id="formTreinamento" enctype="multipart/form-data">
            <div class="mb-3">
              <label for="videoTitle" class="form-label">Título do treinamento</label>
              <input class="form-control" type="text" name="titulo" id="videoTitle" maxlength="180" placeholder="Escreva aqui..." required>
              <div class="form-text">Será salvo dentro do JSON e também como nome do arquivo.</div>
            </div>

            <div class="mb-3">
              <div id="dropzone" class="dropzone">
                <i class="fa fa-cloud-arrow-up"></i>
                <span><strong>Arraste e solte</strong> um arquivo aqui ou <u>clique para selecionar</u> (.mp4/.mp3)</span>
              </div>
              <input class="form-control d-none" type="file" name="video" id="videoFile" accept="video/*,audio/*">
            </div>

            <div class="mb-3">
                <label for="videoLink" class="form-label">Informe o link de vídeo</label>
                <input class="form-control" type="url" name="link" id="videoLink" placeholder="https://...">
            </div>

            <div class="d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-success btn-pill">
                    <span class="me-1"><i class="fa fa-brain"></i></span> Transcrever e Treinar
                </button>
                <span id="liveHint" class="chip d-none">
                    <span class="spinner-border spinner-border-sm"></span> Processando…
                </span>
                
            </div>

            <!-- Barra de progresso -->
            <div class="progress mt-3" id="progressBarContainer" style="height: 10px; display: none;">
              <div class="progress-bar bg-primary" role="progressbar" id="uploadProgressBar" style="width: 0%"></div>
            </div>

            <div id="logTreinamento" class="log-box mt-3" style="display:none;"></div>
          </form>
        </div>

        <div class="section">
          <div class="section-title">
            <div class="ico"><i class="bi bi-info-circle"></i></div>
            <div>Dicas rápidas</div>
          </div>
          <ul class="mb-0 subtle">
            <li>Use títulos claros — eles viram o <em>nome do arquivo JSON</em>.</li>
            <li>Envie <strong>arquivo OU link</strong>, não os dois ao mesmo tempo.</li>
            <li>Durante o processamento, acompanhe o status no histórico abaixo.</li>
          </ul>
        </div>
      </div>

      <div class="section">
        <div class="section-title">
          <div class="ico"><i class="bi bi-clock-history"></i></div>
          <div>Histórico de Treinamentos</div>
        </div>

        <div class="d-flex justify-content-end">
          <button class="btn btn-outline-secondary btn-sm btn-pill" id="btnReloadHist">
            <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
          </button>
        </div>

        <div class="table-responsive mt-3" id="histContainer">
          <table class="table table-modern table-sm align-middle">
            <thead>
              <tr>
                <th style="min-width:60px">#</th>
                <th style="min-width:240px">Título</th>
                <th>Origem</th>
                <th>Link</th>
                <th>JSON</th>
                <th>Status</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody id="histBody">
            <?php if (!$historico): ?>
              <tr><td colspan="9" class="text-muted">Sem registros.</td></tr>
            <?php else: foreach ($historico as $h): ?>
              <tr class="row-hover">
                <td class="text-muted"><?= (int)$h['id'] ?></td>
                <td><?= htmlspecialchars($h['titulo'] ?? '') ?></td>
                <td><?= $h['origem'] === 'url' ? '<span class="badge badge-soft-info">URL</span>' : '<span class="badge badge-soft-secondary">Upload</span>' ?></td>
                <td><?= !empty($h['link']) ? '<a href="'.htmlspecialchars($h['link']).'" target="_blank" rel="noopener" class="link-muted">abrir</a>' : '<span class="text-muted">—</span>' ?></td>
                <td><?= !empty($h['arquivo_json']) ? '<a href="'.htmlspecialchars($h['arquivo_json']).'" target="_blank" rel="noopener" class="link-muted">baixar</a>' : '<span class="text-muted">—</span>' ?></td>
                <td>
                  <?php
                    $status = $h['status'];
                    $badgeClass = $status === 'CONCLUIDO' ? 'badge-success'
                               : ($status === 'ERRO' ? 'badge-danger' : 'badge-warning');
                  ?>
                  <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                </td>
                <td><?= $h['data_inicio'] ? date('d/m/Y H:i', strtotime($h['data_inicio'])) : '—' ?></td>
                <td><?= $h['data_fim'] ? date('d/m/Y H:i', strtotime($h['data_fim'])) : '—' ?></td>
                <td>
                  <?php if ($h['status'] !== 'PROCESSANDO'): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-log" data-id="<?= (int)$h['id'] ?>">
                      <i class="bi bi-journal-text me-1"></i>Ver log
                    </button>
                  <?php else: ?>
                    <span class="text-muted d-inline-flex align-items-center">
                      <span class="spinner-border spinner-border-sm me-2"></span>Aguardando
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Toast container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080">
  <div id="appToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="toastMsg">OK</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

<!-- Modal Log -->
<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-journal-text me-2"></i>Log do treinamento</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <pre id="logContent" class="mb-0" style="white-space:pre-wrap; font-size: .875rem;"></pre>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Overlay "ocupado" -->
<div id="busyOverlay">
  <div style="text-align:center;color:#fff">
    <div class="spinner-border" role="status" aria-hidden="true"></div>
    <div style="margin-top:.75rem;font-weight:600">Processando… isso pode levar alguns minutos</div>
  </div>
</div>
<!-- Bootstrap bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../ChatBot/webchat/js/config_bot.js?v=<?= @filemtime(__DIR__ . '/../../ChatBot/webchat/js/config_bot.js') ?: time() ?>" defer></script>

</body>
</html>
