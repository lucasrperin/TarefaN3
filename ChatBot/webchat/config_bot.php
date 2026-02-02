<?php
require_once __DIR__ . '/../../Includes/auth.php';
require_once __DIR__ . '/../../Config/Database.php';

date_default_timezone_set('America/Sao_Paulo');

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

// busca última do FAQ
$resfaq = $conn->query("SELECT MAX(data_geracao) AS ultima FROM TB_EMBEDDINGS WHERE tipo = 'faq'");
$rowfaq = $resfaq->fetch_assoc();
$ultimafaq = $rowfaq['ultima'] ? date('d/m/Y H:i:s', strtotime($rowfaq['ultima'])) : 'Nunca';

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

// Caminhos para endpoints
$execEtapasFs       = realpath(__DIR__ . '/../../ChatBot/webchat/executar_etapas.php');
$chamarProcVidFs    = realpath(__DIR__ . '/../../ChatBot/scripts/video/chamar_processa_video.php');
$chamarProcSiteFs   = realpath(__DIR__ . '/../../ChatBot/scripts/website/chamar_processa_site.php');
$listarJsonSiteFs   = realpath(__DIR__ . '/../../ChatBot/scripts/website/listar_json_site.php');
$excluirJsonSiteFs  = realpath(__DIR__ . '/../../ChatBot/scripts/website/excluir_json_site.php');

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
  <!-- CSS local existente -->
  <link rel="stylesheet" href="../../Public/config_bot.css">
  <link rel="icon" href="../../Public/Image/LogoTituto.png" type="image/png">

  
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
    <!-- ===== Header (mantido exatamente) ===== -->
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

    <!-- ===== Conteúdo ===== -->
    <div class="page-content p-4">

      <!-- Ações rápidas (sem abas) -->
      <div class="quick-band mb-3">
        <!-- Artigos -->
        <div class="qcard">
          <div class="q-ico"><i class="fa fa-database"></i></div>
          <div class="q-title">Embeddings Artigos</div>
          <div class="q-sub mb-3">Última sincronização: <strong><?= htmlspecialchars($ultima) ?></strong></div>
          <div class="q-cta d-flex align-items-center gap-2">
            <button class="btn btn-primary btn-pill" id="btnExecutar" title="Backup → Gerar → Upload">
              <i class="fa fa-bolt me-1"></i> Gerar & Publicar
            </button>
          </div>
          <div id="log" class="log-box mt-3" style="display:none;"></div>
        </div>

        <!-- Vídeos -->
        <div class="qcard">
          <div class="q-ico"><i class="fa fa-video"></i></div>
          <div class="q-title">Embeddings Vídeos</div>
          <div class="q-sub mb-3">Última sincronização: <strong id="ultimaVideoText"><?= htmlspecialchars($ultimavideo) ?></strong></div>
          <div class="q-cta d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-pill" id="btnUploadVideos" title="Publica os embeddings das transcrições no Supabase">
              <i class="fa fa-cloud-arrow-up me-1"></i> Publicar
            </button>
          </div>
          <div id="uploadVideosStatus" class="alert alert-success py-2 px-3 mt-2 d-none"></div>
          <div id="logVideos" class="log-box mt-3" style="display:none;"></div>
        </div>

        <!-- Websites -->
        <div class="qcard">
          <div class="q-ico"><i class="fa fa-globe"></i></div>
          <div class="q-title">Embeddings Websites</div>
          <div class="q-sub mb-3">Última sincronização: <strong id="ultimaWebsiteText"><?= htmlspecialchars($ultimaweb) ?></strong></div>
          <div class="q-cta d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-pill" id="btnUploadSites" title="Sincroniza os embeddings coletados de websites">
              <i class="fa fa-cloud-arrow-up me-1"></i> Publicar
            </button>
          </div>
          <div id="uploadSitesStatus" class="alert alert-success py-2 px-3 mt-2 d-none"></div>
        </div>

        <!-- Faq -->
        <div class="qcard">
          <div class="q-ico"><i class="fa fa-database"></i></div>
          <div class="q-title">Embeddings FAQ</div>
          <div class="q-sub mb-3">Última sincronização: <strong><?= htmlspecialchars($ultimafaq) ?></strong></div>
          <div class="q-cta d-flex align-items-center gap-2">
            <button class="btn btn-primary btn-pill" id="btnExecutarFaq" title="Backup → Gerar → Upload">
              <i class="fa fa-bolt me-1"></i> Gerar & Publicar
            </button>
          </div>
          <div id="logFaq" class="log-box mt-3" style="display:none;"></div>
        </div>
        <div id="logSites" class="log-box" style="display:none;"></div>
      </div>

      <!-- Acordeão de operações -->
      <div class="acc-card mb-3">
        <div class="acc-head" data-bs-toggle="collapse" data-bs-target="#accVideo" role="button" aria-expanded="true" aria-controls="accVideo">
          <h6><span class="badge bg-primary-subtle text-primary"><i class="bi bi-easel2 me-1"></i>Vídeo</span> Transcrever & Treinar</h6>
          <i class="bi bi-chevron-down"></i>
        </div>
        <div id="accVideo" class="collapse show">
          <div class="acc-body">
            <form id="formTreinamento" enctype="multipart/form-data">
              <div class="row g-3">
                <div class="col-lg-7">
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
                    <label for="videoLink" class="form-label">Link do vídeo (opcional)</label>
                    <input class="form-control" type="url" name="link" id="videoLink" placeholder="https://...">
                  </div>

                  <div class="d-flex align-items-center gap-3">
                    <button type="submit" class="btn btn-success btn-pill">
                      <span class="me-1"><i class="fa fa-brain"></i></span> Transcrever e Treinar
                    </button>
                  </div>

                  <div class="progress mt-3" id="progressBarContainer" style="display: none;">
                    <div class="progress-bar bg-primary" role="progressbar" id="uploadProgressBar" style="width: 0%"></div>
                  </div>

                  <div id="logTreinamento" class="log-box mt-3" style="display:none;"></div>
                </div>

                <div class="col-lg-5">
                  <div class="section-title" style="margin-top:.25rem">
                    <div class="ico"><i class="bi bi-lightbulb"></i></div>
                    <div>Boas práticas</div>
                  </div>
                  <ul class="mb-0 subtle">
                    <li>Use títulos claros — viram o <em>nome do JSON</em>.</li>
                    <li>Envie <strong>arquivo OU link</strong> (não ambos).</li>
                    <li>Acompanhe o status no <strong>Histórico</strong>.</li>
                    <li>Após treinar, publique em <strong>Vídeos</strong> na faixa acima.</li>
                  </ul>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="acc-card mb-3">
        <div class="acc-head" data-bs-toggle="collapse" data-bs-target="#accSite" role="button" aria-expanded="true" aria-controls="accSite">
          <h6><span class="badge bg-info-subtle text-info"><i class="bi bi-globe2 me-1"></i>Website</span> Coletar & Treinar</h6>
          <i class="bi bi-chevron-down"></i>
        </div>
        <div id="accSite" class="collapse show">
          <div class="acc-body">
            <form id="formTreinamentoSite">
              <div class="row g-3">
                <div class="col-lg-7">
                  <div class="mb-3">
                    <label for="siteUrl" class="form-label">URL inicial</label>
                    <input class="form-control" type="url" id="siteUrl" name="url" placeholder="https://suporte.clipp.com.br/artigos" required>
                    <div class="form-text">Coletaremos páginas a partir desta URL.</div>
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
                        <label for="siteUseSitemap" class="form-check-label">Usar sitemap.xml</label>
                      </div>
                    </div>
                  </div>

                  <div class="d-flex align-items-center gap-3 mt-3">
                    <button type="submit" class="btn btn-success btn-pill">
                      <span class="me-1"><i class="fa fa-brain"></i></span> Coletar & Treinar
                    </button>
                  </div>

                  <div id="logTreinamentoSite" class="log-box mt-3" style="display:none;"></div>
                </div>

                <div class="col-lg-5">
                  <div class="section-title" style="margin-top:.25rem">
                    <div class="ico"><i class="bi bi-info-circle"></i></div>
                    <div>Como funciona</div>
                  </div>
                  <ul class="mb-0 subtle">
                    <li>Coleta páginas e extrai o texto principal.</li>
                    <li>Gera um JSON por página com <em>titulo</em>, <em>conteudo</em>, <em>embedding</em> e <em>link</em>.</li>
                    <li>Depois, publique na faixa acima (Websites).</li>
                  </ul>
                  <hr>
                  <button type="button" class="btn btn-outline-secondary btn-pill" onclick="window.showSiteFilesModal && window.showSiteFilesModal('latest')">
                    <i class="bi bi-files me-1"></i> Ver arquivos coletados
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Histórico -->
      <div class="acc-card">
        <div class="acc-head" data-bs-toggle="collapse" data-bs-target="#accHist" role="button" aria-expanded="true" aria-controls="accHist">
          <h6><span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-clock-history me-1"></i>Histórico</span> Treinamentos</h6>
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-secondary btn-sm btn-pill" id="btnReloadHist"><i class="bi bi-arrow-clockwise me-1"></i> Atualizar</button>
            <i class="bi bi-chevron-down"></i>
          </div>
        </div>
        <div id="accHist" class="collapse show">
          <div class="acc-body">
            <div class="table-responsive mt-2" id="histContainer">
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

    </div><!-- /page-content -->
  </div><!-- /content -->
</div><!-- /wrapper -->

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
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="checkAllRows">
            <label class="form-check-label" for="checkAllRows">Selecionar todos</label>
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
  // empilhamento de modais (preview sempre na frente)
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
</script>
</body>
</html>
