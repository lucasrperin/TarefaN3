<?php
require_once __DIR__ . '/../../Includes/auth.php';
require_once __DIR__ . '/../../Config/Database.php';

$usuario_nome = $_SESSION['usuario_nome'] ?? '';

// busca última de ARTIGOS
$res = $conn->query("SELECT MAX(data_geracao) AS ultima FROM TB_EMBEDDINGS WHERE tipo = 'artigos'");
$row = $res->fetch_assoc();
$ultima = $row['ultima'] ? date('d/m/Y H:i:s', strtotime($row['ultima'])) : 'Nunca';

// busca última de VÍDEO
$resvideo = $conn->query("SELECT MAX(data_geracao) AS ultima FROM TB_EMBEDDINGS WHERE tipo = 'video'");
$rowvideo = $resvideo->fetch_assoc();
$ultimavideo = $rowvideo['ultima'] ? date('d/m/Y H:i:s', strtotime($rowvideo['ultima'])) : 'Nunca';

// busca última de WEBSITE
$resweb = $conn->query("SELECT MAX(data_geracao) AS ultima FROM TB_EMBEDDINGS WHERE tipo = 'website'");
$rowweb = $resweb->fetch_assoc();
$ultimaweb = $rowweb['ultima'] ? date('d/m/Y H:i:s', strtotime($rowweb['ultima'])) : 'Nunca';

// histórico (últimos 50)
$hist = $conn->query("
  SELECT id, titulo, origem, link, arquivo_json, status, data_inicio, data_fim
    FROM TB_TREINAMENTOS_BOT
ORDER BY id DESC
   LIMIT 50
");
$historico = [];
if ($hist) while ($r = $hist->fetch_assoc()) $historico[] = $r;

// ====== Resposta parcial: atualizar histórico ======
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

// ====== Resposta parcial: obter log por ID ======
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

// === Helper: FS->URL pública
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

// Caminhos para os endpoints PHP já existentes/novos
$execEtapasFs       = realpath(__DIR__ . '/../../ChatBot/webchat/executar_etapas.php');
$chamarProcVidFs    = realpath(__DIR__ . '/../../ChatBot/scripts/video/chamar_processa_video.php');
$chamarProcSiteFs   = realpath(__DIR__ . '/../../ChatBot/scripts/website/chamar_processa_site.php');
$listarJsonSiteFs   = realpath(__DIR__ . '/../../ChatBot/scripts/website/listar_json_site.php');   // lista arquivos JSON do treino de site
$excluirJsonSiteFs  = realpath(__DIR__ . '/../../ChatBot/scripts/website/excluir_json_site.php');  // exclui arquivo JSON do treino de site

// URLs públicas
$execEtapasUrl       = fs_to_url($execEtapasFs);
$chamarProcVidUrl    = fs_to_url($chamarProcVidFs);
$chamarProcSiteUrl   = fs_to_url($chamarProcSiteFs);
$listarJsonSiteUrl   = fs_to_url($listarJsonSiteFs);
$excluirJsonSiteUrl  = fs_to_url($excluirJsonSiteFs);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Configuração do Chatbot</title>

  <!-- Font Awesome / Bootstrap Icons / Bootstrap -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
  <!-- CSS local -->
  <link rel="stylesheet" href="../../Public/config_bot.css">
  <link rel="icon" href="../../Public/Image/LogoTituto.png" type="image/png">

  <!-- Z-index para empilhar modais corretamente -->
  <style>
    #siteFilesModal .modal { z-index: 1085; }
    #jsonPreviewModal .modal { z-index: 1095; }
    .table-modern tbody tr.row-hover:hover { background: rgba(0,0,0,.03); }
    .chip { display:inline-flex; align-items:center; gap:.5rem; background:#eef; border-radius:999px; padding:.25rem .6rem; }
    .btn-pill { border-radius: 999px; }
    .log-box { white-space: pre-wrap; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
  </style>
</head>
<body
  data-upload-video-emb="<?= htmlspecialchars($execEtapasUrl, ENT_QUOTES, 'UTF-8') ?>"
  data-chamar-processa-video="<?= htmlspecialchars($chamarProcVidUrl, ENT_QUOTES, 'UTF-8') ?>"
  data-chamar-processa-site="<?= htmlspecialchars($chamarProcSiteUrl, ENT_QUOTES, 'UTF-8') ?>"
  data-upload-site-emb="<?= htmlspecialchars($execEtapasUrl, ENT_QUOTES, 'UTF-8') ?>"
  data-site-list-json="<?= htmlspecialchars($listarJsonSiteUrl, ENT_QUOTES, 'UTF-8') ?>"
  data-site-delete-json="<?= htmlspecialchars($excluirJsonSiteUrl, ENT_QUOTES, 'UTF-8') ?>"
  data-theme=""
>
<div class="d-flex-wrapper">
  <?php include __DIR__ . '/../../components/sidebar_bot.php'; ?>

  <div class="w-100 flex-grow-1 d-flex flex-column">
    <!-- Header -->
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

    <!-- Conteúdo principal -->
    <div class="page-content p-4">

      <!-- ARTIGOS -->
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

        <!-- VÍDEOS -->
        <div class="section-title mt-4">
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

        <!-- WEBSITES -->
        <div class="section-title mt-4">
          <div class="ico"><i class="fa fa-database"></i></div>
          <div>Embeddings Websites</div>
        </div>
        <div class="stat mb-3">
          <div>
            <div class="subtle">Última geração de embeddings</div>
            <div class="fs-5 fw-bold" id="ultimaWebsiteText"><?= htmlspecialchars($ultimaweb) ?></div>
          </div>
          <button type="button" class="btn btn-primary btn-pill" id="btnUploadSites" title="Publica os embeddings coletados de websites no Supabase">
            <i class="fa fa-cloud-arrow-up me-1"></i> Publicar Embeddings
          </button>
          <div id="uploadSitesStatus" class="alert alert-success py-2 px-3 mt-2 d-none"></div>
        </div>
        <div id="logSites" class="log-box" style="display:none;"></div>
      </div>

      <div class="split mb-4">
        <!-- Treinamento por VÍDEO -->
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
            </div>

            <div class="progress mt-3" id="progressBarContainer" style="height: 10px; display: none;">
              <div class="progress-bar bg-primary" role="progressbar" id="uploadProgressBar" style="width: 0%"></div>
            </div>

            <div id="logTreinamento" class="log-box mt-3" style="display:none;"></div>
          </form>
        </div>

        <!-- Dicas -->
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

      <!-- Treinamento por WEBSITE -->
      <div class="split mb-4">
        <div class="section">
          <div class="section-title">
            <div class="ico"><i class="bi bi-globe2"></i></div>
            <div>Treinamento por Website</div>
          </div>

          <form id="formTreinamentoSite">
            <div class="mb-3">
              <label for="siteUrl" class="form-label">URL inicial</label>
              <input class="form-control" type="url" id="siteUrl" name="url" placeholder="https://suporte.clipp.com.br/artigos" required>
              <div class="form-text">Será feita a coleta de páginas a partir dessa URL.</div>
            </div>

            <div class="row g-3">
              <div class="col-sm-4">
                <label for="siteMaxPages" class="form-label">Máx. páginas</label>
                <input class="form-control" type="number" id="siteMaxPages" name="max_pages" min="1" max="100" value="10">
              </div>
              <div class="col-sm-4 d-flex align-items-end">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="siteSameDomain" name="same_domain" checked>
                  <label for="siteSameDomain" class="form-check-label">Somente mesmo domínio</label>
                </div>
              </div>
              <div class="col-sm-4 d-flex align-items-end">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="siteUseSitemap" name="use_sitemap">
                  <label for="siteUseSitemap" class="form-check-label">Usar sitemap.xml se disponível</label>
                </div>
              </div>
            </div>

            <div class="d-flex align-items-center gap-3 mt-3">
              <button type="submit" class="btn btn-success btn-pill">
                  <span class="me-1"><i class="fa fa-brain"></i></span> Coletar & Treinar
              </button>
            </div>

            <div id="logTreinamentoSite" class="log-box mt-3" style="display:none;"></div>
          </form>
        </div>

        <div class="section">
          <div class="section-title">
            <div class="ico"><i class="bi bi-lightbulb"></i></div>
            <div>Como funciona</div>
          </div>
          <ul class="mb-0 subtle">
            <li>Coleta páginas (respeitando limites) e extrai o texto principal.</li>
            <li>Gera um JSON por página com <em>titulo</em>, <em>conteudo</em>, <em>embedding</em> e <em>link</em>.</li>
            <li>Depois use “Publicar Embeddings” (Websites) para sincronizar com o Supabase.</li>
          </ul>
        </div>
      </div>

      <!-- Histórico -->
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

<!-- Toast -->
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

<!-- Modal Lista de JSONs do Site -->
<div class="modal fade" id="siteFilesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-files me-2"></i>Arquivos coletados (Website)</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="checkAllRows">
              <label class="form-check-label" for="checkAllRows">Selecionar todos</label>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteSelected" disabled>
              <i class="bi bi-trash me-1"></i>Excluir selecionados
            </button>
          </div>
        </div>

        <div class="table-responsive border rounded">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:36px"></th>
                <th>Arquivo</th>
                <th style="width:120px">Tamanho</th>
                <th style="width:160px">Modificado</th>
                <th style="width:220px">Ações</th>
              </tr>
            </thead>
            <tbody id="siteFilesBody">
              <tr><td colspan="5" class="text-muted p-3">Carregando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <small class="me-auto text-muted" id="siteFilesCounter">0 itens</small>
        <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Pré-visualização JSON -->
<div class="modal fade" id="jsonPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="bi bi-eye me-2"></i>Pré-visualização</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <pre id="jsonPreviewContent" class="mb-0" style="white-space:pre-wrap; font-size:.875rem; min-height: 240px;">Carregando…</pre>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Overlay -->
<div id="busyOverlay">
  <div style="text-align:center;color:#fff">
    <div class="spinner-border" role="status" aria-hidden="true"></div>
    <div style="margin-top:.75rem;font-weight:600">Processando… isso pode levar alguns minutos</div>
  </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../ChatBot/webchat/js/config_bot.js?v=<?= @filemtime(__DIR__ . '/../../ChatBot/webchat/js/config_bot.js') ?: time() ?>" defer></script>

<script>
(() => {
  // ====== util ======
  const $ = (sel, root=document) => root.querySelector(sel);
  const $$ = (sel, root=document) => Array.from(root.querySelectorAll(sel));

  // ====== empilhamento de modais (preview sempre na frente) ======
  document.addEventListener('show.bs.modal', (ev) => {
    const open = document.querySelectorAll('.modal.show').length;
    const base = 1085;
    const z = base + (open + 1) * 10;
    ev.target.style.zIndex = z;
    setTimeout(() => {
      const backs = document.querySelectorAll('.modal-backdrop');
      if (backs.length) backs[backs.length - 1].style.zIndex = z - 1;
    }, 0);
  }, true);

  // ====== endpoints ======
  const body = document.body;
  const siteListUrl   = body.dataset.siteListJson || '';
  const siteDeleteUrl = body.dataset.siteDeleteJson || '';

  // ====== modais e elementos ======
  const siteFilesModalEl   = $('#siteFilesModal');
  const siteFilesModal     = siteFilesModalEl ? new bootstrap.Modal(siteFilesModalEl, { backdrop: true, keyboard: true }) : null;
  const siteFilesBody      = $('#siteFilesBody');
  const siteFilesCounter   = $('#siteFilesCounter');
  const checkAllRows       = $('#checkAllRows');
  const btnDeleteSelected  = $('#btnDeleteSelected');

  const jsonPreviewModalEl = $('#jsonPreviewModal');
  const jsonPreviewModal   = jsonPreviewModalEl ? new bootstrap.Modal(jsonPreviewModalEl, { backdrop: true, keyboard: true }) : null;
  const jsonPreviewContent = $('#jsonPreviewContent');

  function showToast(msg, type='success') {
    const toastEl = document.getElementById('appToast');
    if (!toastEl) return;
    toastEl.classList.remove('text-bg-success','text-bg-danger','text-bg-warning','text-bg-info');
    toastEl.classList.add('text-bg-' + (type === 'error' ? 'danger' : type));
    const toastMsg = document.getElementById('toastMsg');
    if (toastMsg) toastMsg.textContent = msg;
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
  }

  function humanSize(bytes) {
    const b = Number(bytes||0);
    if (b < 1024) return b + ' B';
    const u = ['KB','MB','GB','TB'];
    const i = Math.floor(Math.log(b)/Math.log(1024));
    return (b/Math.pow(1024,i)).toFixed(1)+' '+u[i];
  }

  function refreshCounter() {
    if (!siteFilesBody || !siteFilesCounter) return;
    const rows = $$('#siteFilesBody tr');
    const count = rows.filter(r => !r.querySelector('td')?.textContent?.match(/Carregando|Nenhum arquivo/)).length;
    siteFilesCounter.textContent = `${count} item${count === 1 ? '' : 's'}`;
  }

  function updateSelectionUi() {
    if (!siteFilesBody || !btnDeleteSelected || !checkAllRows) return;
    const checks = $$('.row-check', siteFilesBody);
    const sel = checks.filter(c => c.checked);
    btnDeleteSelected.disabled = sel.length === 0;
    const allChecked = checks.length > 0 && sel.length === checks.length;
    checkAllRows.checked = allChecked;
    checkAllRows.indeterminate = sel.length > 0 && !allChecked;
  }

  function bindRowButtons(tr) {
    const btnPrev = tr.querySelector('.btn-preview');
    const btnDel  = tr.querySelector('.btn-delete');
    const chk     = tr.querySelector('.row-check');

    if (chk) chk.addEventListener('change', updateSelectionUi);

    if (btnPrev) btnPrev.addEventListener('click', async () => {
      const url = btnPrev.getAttribute('data-preview-url');
      const name= btnPrev.getAttribute('data-name') || 'arquivo.json';
      if (!url || !jsonPreviewModal) return;

      jsonPreviewContent.textContent = 'Carregando…';
      jsonPreviewModal.show();

      try {
        const r = await fetch(url, { cache: 'no-store' });
        const txt = await r.text();
        try {
          const obj = JSON.parse(txt);
          jsonPreviewContent.textContent = JSON.stringify(obj, null, 2);
        } catch(_) {
          jsonPreviewContent.textContent = txt;
        }
        jsonPreviewModalEl.addEventListener('shown.bs.modal', () => {
          jsonPreviewModalEl.querySelector('.btn-close')?.focus();
        }, { once: true });
      } catch(e) {
        jsonPreviewContent.textContent = 'Falha ao carregar o conteúdo para pré-visualização.';
      }
    });

    if (btnDel) btnDel.addEventListener('click', async () => {
      const url  = btnDel.getAttribute('data-delete-url');
      const name = btnDel.getAttribute('data-name') || 'arquivo';
      if (!url) return;
      if (!confirm(`Excluir “${name}”? Esta ação não pode ser desfeita.`)) return;

      btnDel.disabled = true;
      try {
        let r = await fetch(url, { method:'POST' });
        if (!r.ok || r.status === 405 || r.status === 400) {
          r = await fetch(url);
        }
        let ok = r.ok;
        try {
          const data = await r.clone().json();
          if (typeof data?.ok !== 'undefined') ok = !!data.ok;
        } catch (_) {}

        if (ok) {
          tr.remove();
          updateSelectionUi();
          refreshCounter();
          showToast('Arquivo excluído.');
        } else {
          showToast('Não foi possível excluir o arquivo.', 'error');
        }
      } catch(e){
        showToast('Erro durante exclusão.', 'error');
      } finally {
        btnDel.disabled = false;
      }
    });
  }

  function renderSiteFiles(list=[]) {
    if (!siteFilesBody) return;
    if (!Array.isArray(list) || list.length === 0) {
      siteFilesBody.innerHTML = '<tr><td colspan="5" class="text-muted p-3">Nenhum arquivo encontrado.</td></tr>';
      refreshCounter();
      updateSelectionUi();
      return;
    }

    const frag = document.createDocumentFragment();
    list.forEach(item => {
      const tr = document.createElement('tr');

      const name = item.name || '(sem nome)';
      const size = humanSize(item.size || 0);
      const mtime= item.mtime || '';
      const previewUrl = item.preview_url || item.pub_url || '';
      const delUrl = item.delete_url || (siteDeleteUrl ? `${siteDeleteUrl}?path=${encodeURIComponent(item.fs_path || '')}` : '');

      tr.innerHTML = `
        <td><input type="checkbox" class="form-check-input row-check"></td>
        <td class="text-break"><i class="bi bi-filetype-json me-2 text-muted"></i>${name}</td>
        <td class="text-muted">${size}</td>
        <td class="text-muted">${mtime}</td>
        <td>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary btn-preview" data-preview-url="${previewUrl}" data-name="${name}" ${previewUrl ? '' : 'disabled'}>
              <i class="bi bi-eye me-1"></i>Pré-visualizar
            </button>
            <a class="btn btn-outline-secondary" href="${item.pub_url || '#'}" target="_blank" rel="noopener" ${item.pub_url ? '' : 'disabled'}>
              <i class="bi bi-download me-1"></i>Baixar
            </a>
            <button type="button" class="btn btn-outline-danger btn-delete" data-delete-url="${delUrl}" data-name="${name}">
              <i class="bi bi-trash me-1"></i>Excluir
            </button>
          </div>
        </td>
      `;
      bindRowButtons(tr);
      frag.appendChild(tr);
    });

    siteFilesBody.innerHTML = '';
    siteFilesBody.appendChild(frag);
    refreshCounter();
    updateSelectionUi();
  }

  if (checkAllRows) {
    checkAllRows.addEventListener('change', () => {
      $$('.row-check', siteFilesBody).forEach(chk => chk.checked = checkAllRows.checked);
      updateSelectionUi();
    });
  }

  if (btnDeleteSelected) {
    btnDeleteSelected.addEventListener('click', async () => {
      const checks = $$('.row-check:checked', siteFilesBody);
      if (!checks.length) return;
      if (!confirm(`Excluir ${checks.length} arquivo(s) selecionado(s)?`)) return;

      btnDeleteSelected.disabled = true;

      for (const chk of checks) {
        const tr = chk.closest('tr');
        const del = tr?.querySelector('.btn-delete');
        const url = del?.getAttribute('data-delete-url');
        if (!url) continue;

        try {
          let r = await fetch(url, { method:'POST' });
          if (!r.ok || r.status === 405 || r.status === 400) {
            r = await fetch(url);
          }
          let ok = r.ok;
          try {
            const data = await r.clone().json();
            if (typeof data?.ok !== 'undefined') ok = !!data.ok;
          } catch (_) {}

          if (ok) tr.remove();
        } catch (_) { /* next */ }
      }

      refreshCounter();
      updateSelectionUi();
      showToast('Exclusão concluída.');
      btnDeleteSelected.disabled = false;
    });
  }

  // Exposto globalmente para ser chamado após concluir o treinamento do site
  window.showSiteFilesModal = async function (jobId) {
    if (!siteListUrl || !siteFilesModal) return;
    siteFilesBody.innerHTML = '<tr><td colspan="5" class="text-muted p-3">Carregando…</td></tr>';
    checkAllRows.checked = false;
    btnDeleteSelected.disabled = true;

    try {
      const r = await fetch(`${siteListUrl}?job=${encodeURIComponent(jobId)}`, { cache: 'no-store' });
      const data = await r.json();
      if (data && data.files) {
        renderSiteFiles(data.files);
      } else {
        renderSiteFiles([]);
      }
    } catch (e) {
      renderSiteFiles([]);
    }
    siteFilesModal.show();
  };

})();
</script>
</body>
</html>
