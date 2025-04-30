CREATE SCHEMA TarefaN3;

USE TarefaN3;

CREATE TABLE TB_SITUACAO 
( 
 Id INT PRIMARY KEY auto_increment,  
 Descricao varchar(50) UNIQUE NOT NULL
); 

CREATE TABLE TB_SISTEMA 
( 
 Descricao varchar(50) UNIQUE NOT NULL,  
 Id INT PRIMARY KEY auto_increment
); 

CREATE TABLE TB_STATUS 
( 
 Descricao varchar(50) UNIQUE NOT NULL,  
 Id INT PRIMARY KEY auto_increment
); 

CREATE TABLE TB_ANALISES 
( 
 Id INT PRIMARY KEY auto_increment,  
 Descricao varchar(100) NOT NULL,  
 idSituacao INT,  
 idAtendente INT,  
 idSistema INT,  
 idStatus INT,
 idUsuario int,
 Hora_ini DATETIME,  
 Hora_fim DATETIME,  
 Total_hora TIME,
 chkFicha char(1),
 numeroFicha INT DEFAULT NULL,
 chkMultiplica char(1),
 numeroMulti int DEFAULT NULL,
 chkParado char(1),
 Nota tinyint,
 justificativa varchar(255)
); 

CREATE TABLE TB_USUARIO
( 
 Id INT PRIMARY KEY AUTO_INCREMENT,
 Nome varchar(50) NOT NULL,
 Email varchar(50) NOT NULL,
 Senha varchar(255) NOT NULL,
 Cargo varchar(255) NOT NULL
); 

-- Inserindo Atendentes
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (1, 'Lucas Perin', 'lucas.perin@zucchetti.com', '1234', 'Admin');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (2, 'Guilherme Ferri', 'guilherme.ferri@zucchetti.com', '1234', 'Admin');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (3, 'Gabriel Battistella', 'gabriel.battistella@zucchetti.com', '1234', 'Admin');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (4, 'Bernardo Rachadel', 'bernardo.rachadel@zucchetti.com', '1234', 'Admin');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (5, 'Douglas da Silva', 'douglas.silva@zucchetti.com', '1234', 'Admin');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (6, 'Vagner Wilske', 'vagner.wilske@zucchetti.com', '1234', 'Viewer');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (7, 'Germano Santos', 'germano.santos@zucchetti.com', '1234', 'Viewer');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (8, 'Leandro Haefliger', 'leandro.haefliger@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (9, 'Wesley Zamarchi', 'wesley.zamarchi@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (10, 'Lucas Rossatto', 'lucas.rossato@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (11, 'Giselle Goetz', 'giselle.goetz@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (12, 'Caio Oliveira', 'caio.oliveira@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (13, 'Eduardo Forcellini', 'eduardo.forcellini@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (14, 'Antonio Zampeze', 'antonio.zampeze@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (15, 'Gabriel Deggerone', 'gabriel.deggerone@zucchetti.com', '1234', 'Conversor');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (16, 'Douglas Santana', 'douglas.santana@zucchetti.com', 'Senha09=', 'Conversor');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (17, 'Ian Savoldi', 'ian.savoldi@zucchetti.com', '1234', 'Conversor');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (18, 'Iago Pereira', 'iago.pereira@zucchetti.com', '1234', 'Conversor');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (19, 'Eduardo Renan', 'eduardo.renan@zucchetti.com', '1234', 'Conversor');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (20, 'Thiago Marques', 'thiago.marques@zucchetti.com', '1234', 'Admin');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (21, 'Gabriel Debiasi', 'gabriel.debiasi@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (22, 'Marcelo Mattos', 'marcelo.mattos@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (23, 'Mateus Balbinot', 'mateus.balbinot@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (24, 'Caua Luz', 'caua.luz@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (25, 'Thiago Maran', 'Thiago.Maran@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (26, 'Fabiano Martini', 'fabiano.martini@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (27, 'Guilherme Stallbaum', 'guilherme.stallbaum@zucchetti.com', '1234', 'Admin');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (28, 'Filipe Simioni', 'filipe.simioni@zucchetti.com', '1234', 'User');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (29, 'Vanessa Maia', 'vanessa.maia@zucchetti.com', '1234', 'Comercial');
INSERT INTO `TB_USUARIO` (`Id`, `Nome`, `Email`, `Senha`, `Cargo`) VALUES (30, 'Felipe Barimacker', 'Felipe.Barimacker@zucchetti.com', '1234', 'User');


ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idSituacao) REFERENCES TB_SITUACAO (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idSistema) REFERENCES TB_SISTEMA (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idStatus) REFERENCES TB_STATUS (Id);
ALTER TABLE TB_ANALISES ADD FOREIGN KEY(idUsuario) REFERENCES TB_USUARIO (Id);

-- Inserindo Sistemas
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp 360');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp PRO');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('NFS-e');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('DAV');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('ZWeb');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('SmallSoft');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp Facil');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('ECF');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('NFC-e');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp Cheff');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('AppsCloud');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Clipp Service');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Mercado Livre');
INSERT INTO TB_SISTEMA (Descricao) VALUES ('Minhas Notas');

-- Inserindo Status
INSERT INTO TB_STATUS (Descricao) VALUES ('Resolvido');
INSERT INTO TB_STATUS (Descricao) VALUES ('Desenvolvimento');
INSERT INTO TB_STATUS (Descricao) VALUES ('Aguardando');

-- Inserindo Tipos de Situação
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Analise N3');
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Auxilio Suporte/Vendas');
INSERT INTO TB_SITUACAO (Descricao) VALUES ('Ficha Criada');


-- Criação do controle de Conversão
CREATE TABLE TB_SISTEMA_CONVER (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL UNIQUE
);

INSERT INTO TB_SISTEMA_CONVER (nome) VALUES
    ('Conc p/ ClippPro'), ('Clipp p/ ClippPro'), ('Zeta p/ ClippPro'), ('Conc p/ ClippFacil'), ('Clipp p/ ClippFacil'),
    ('Zeta p/ Clipp360'), ('Small p/ Clipp360'), ('Conc p/ Clipp360'), ('Clipp p/ Clipp360'), ('Conc p/ ClippMEI'),
    ('Clipp p/ ClippMEI'), ('Clipp p/ ZetaWeb'), ('Conc p/ ZetaWeb'), ('Small p/ ZetaWeb'), ('Gdoor p/ ZetaWeb'),
    ('Gdoor p/ ClippPro'), ('AC p/ Clipp360'), ('ClippMei p/ ClippPro'), ('AC p/ ClippPro');

CREATE TABLE TB_STATUS_CONVER (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL UNIQUE
);

INSERT INTO TB_STATUS_CONVER (descricao) VALUES
    ('Concluido'), ('Aguardando cliente'), ('Analise'), ('Em fila'), ('Cancelada'),
    ('Dar prioridade'), ('Aguardando Conversor'), ('Ficha'), ('Fin - Aguar. Login');

CREATE TABLE TB_ANALISTA_CONVER (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL UNIQUE
);

INSERT INTO `TB_ANALISTA_CONVER` (`id`, `nome`) VALUES
(16, 'Douglas Santana'),
(17, 'Ian Savoldi'),
(15, 'Gabriel Deggerone'),
(1, 'Lucas Perin'),
(2, 'Guilherme Ferri'),
(3, 'Gabriel Battistella'),
(4, 'Bernardo Rachadel'),
(5, 'Douglas da Silva'),
(6, 'Vagner Wilske'),
(7, 'Germano Santos'),
(8, 'Leandro Haefliger'),
(9, 'Wesley Zamarchi'),
(10, 'Lucas Rossato'),
(11, 'Giselle Goetz'),
(12, 'Caio Oliveira'),
(13, 'Eduardo Forcellini'),
(14, 'Antonio Zampeze'),
(18, 'Eduardo Renan'),
(19, 'Iago Pereira');

CREATE TABLE TB_CONVERSOES (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contato VARCHAR(255) NOT NULL,
    serial VARCHAR(255) NULL,
    retrabalho ENUM('Sim', 'Nao') NOT NULL,
    sistema_id INT NOT NULL,
    status_id INT NOT NULL,
    data_recebido DATETIME NOT NULL,
    prazo_entrega DATETIME NULL,
    data_inicio DATETIME NULL,
    data_conclusao DATETIME NULL,
    analista_id INT NOT NULL,
    observacao TEXT NULL,
    tempo_total TIME GENERATED ALWAYS AS (TIMEDIFF(data_conclusao, data_recebido)) VIRTUAL,
    tempo_conver TIME GENERATED ALWAYS AS (TIMEDIFF(data_conclusao, data_inicio)) VIRTUAL,
    FOREIGN KEY (sistema_id) REFERENCES TB_SISTEMA_CONVER(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES TB_STATUS_CONVER(id) ON DELETE CASCADE,
    FOREIGN KEY (analista_id) REFERENCES TB_ANALISTA_CONVER(id) ON DELETE CASCADE
);

-- Índices para otimizar buscas
CREATE INDEX idx_status ON TB_CONVERSOES(status_id);
CREATE INDEX idx_data_recebido ON TB_CONVERSOES(data_recebido);
CREATE INDEX idx_data_inicio ON TB_CONVERSOES(data_inicio);

-- Tabela de Classificação
CREATE TABLE IF NOT EXISTS TB_CLASSIFICACAO (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao varchar(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Tabela de Escutas
CREATE TABLE IF NOT EXISTS TB_ESCUTAS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    classi_id INT NOT NULL,
    data_escuta DATE NOT NULL,
    transcricao TEXT NOT NULL,
    feedback TEXT NOT NULL,
    data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    P_N ENUM('Sim', 'Nao') NOT NULL,
    solicitaAva ENUM('Sim', 'Nao', 'Caiu') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES TB_USUARIO(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES TB_USUARIO(id) ON DELETE CASCADE,
    FOREIGN KEY (classi_id) REFERENCES TB_CLASSIFICACAO(id) ON DELETE CASCADE
);

CREATE TABLE TB_INCIDENTES (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sistema VARCHAR(50) NOT NULL,
  gravidade VARCHAR(20) NOT NULL,
  indisponibilidade VARCHAR(20) NOT NULL,  
  problema TEXT NOT NULL,
  hora_inicio DATETIME NOT NULL,
  hora_fim DATETIME NOT NULL,
  tempo_total TIME NOT NULL,
  data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE TB_PLUGIN (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL
);

INSERT INTO TB_PLUGIN (nome) VALUES ('ClippCheff');
INSERT INTO TB_PLUGIN (nome) VALUES ('ClippService');
INSERT INTO TB_PLUGIN (nome) VALUES ('ClippFarma');
INSERT INTO TB_PLUGIN (nome) VALUES ('Service MEI');
INSERT INTO TB_PLUGIN (nome) VALUES ('Licença Adicional ClippMei (5 usuários)');
INSERT INTO TB_PLUGIN (nome) VALUES ('Licença Adicional ClippPro (1 usuário)');
INSERT INTO TB_PLUGIN (nome) VALUES ('Licença Adicional ClippPro (2 usuários)');
INSERT INTO TB_PLUGIN (nome) VALUES ('Licença Adicional ClippPro (3 usuários)');
INSERT INTO TB_PLUGIN (nome) VALUES ('Licença Adicional ClippPro (4 usuários)');
INSERT INTO TB_PLUGIN (nome) VALUES ('Licença Adicional ClippPro (5 usuários)');
INSERT INTO TB_PLUGIN (nome) VALUES ('Licença Adicional ClippPro (a partir de 6 usuários');
INSERT INTO TB_PLUGIN (nome) VALUES ('eCommerce C4 (500 itens)');
INSERT INTO TB_PLUGIN (nome) VALUES ('eCommerce C4 (1.000 itens)');
INSERT INTO TB_PLUGIN (nome) VALUES ('eCommerce C4 (1.500 itens)');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZPOS');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZWeb - Cadastro de grades');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZWeb - Pré-venda gerencial');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZWeb - Ecommerce próprio');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZWeb - Integrações com marketplaces');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZWeb - OS e NFSe');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZWeb - Retaguarda offline');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZWeb - Tabela de Preços');
INSERT INTO TB_PLUGIN (nome) VALUES ('ZWeb - Sintegra e SPED');
INSERT INTO TB_PLUGIN (nome) VALUES ('Imendes');
INSERT INTO TB_PLUGIN (nome) VALUES ('BRBackup');
INSERT INTO TB_PLUGIN (nome) VALUES ('Shipay');


CREATE TABLE IF NOT EXISTS TB_INDICACAO (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plugin_id INT NOT NULL,       -- Referência ao plugin na TB_PLUGIN
    data DATE NOT NULL,
    cnpj VARCHAR(20),
    serial VARCHAR(50),
    contato VARCHAR(100),
    fone VARCHAR(20),
    user_id INT NOT NULL,         -- Referência ao usuário que cadastrou a indicação (TB_USUARIO)
	idConsultor INT NOT NULL,	  -- Referência ao consultor que editou a indicação (TB_USUARIO)
    status ENUM('Faturado', 'Pendente', 'Cancelado') NOT NULL DEFAULT 'Pendente',
    vlr_total numeric(18,4),
    n_venda int,
    FOREIGN KEY (plugin_id) REFERENCES TB_PLUGIN(id),
    FOREIGN KEY (user_id) REFERENCES TB_USUARIO(id),
    FOREIGN KEY (idConsultor) REFERENCES TB_USUARIO(id)
);

CREATE TABLE TB_FOLGA (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo ENUM('Ferias','Folga') NOT NULL,
  data_inicio DATE NOT NULL,
  data_fim DATE NOT NULL,
  quantidade_dias INT NOT NULL,
  justificativa TEXT
);

CREATE TABLE IF NOT EXISTS TB_EQUIPE (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao varchar(100)
);

INSERT INTO TB_EQUIPE (descricao) VALUES ('Linha Clipp');
INSERT INTO TB_EQUIPE (descricao) VALUES ('Linha Small');
INSERT INTO TB_EQUIPE (descricao) VALUES ('Todos');


CREATE TABLE IF NOT EXISTS TB_NIVEL (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao varchar(100)
);

INSERT INTO TB_NIVEL (descricao) VALUES ('Treinamento');
INSERT INTO TB_NIVEL (descricao) VALUES ('Nível 1');
INSERT INTO TB_NIVEL (descricao) VALUES ('Nível 2');
INSERT INTO TB_NIVEL (descricao) VALUES ('Exclusivo');
INSERT INTO TB_NIVEL (descricao) VALUES ('Nível 3');
INSERT INTO TB_NIVEL (descricao) VALUES ('Comercial');
INSERT INTO TB_NIVEL (descricao) VALUES ('Supervisão');
INSERT INTO TB_NIVEL (descricao) VALUES ('Gestão');
INSERT INTO TB_NIVEL (descricao) VALUES ('Conversão');

CREATE TABLE IF NOT EXISTS TB_EQUIPE_NIVEL_ANALISTA (
    idUsuario int,
    idEquipe int,
    idNivel int,
    FOREIGN KEY (idUsuario) REFERENCES TB_USUARIO(id),
    FOREIGN KEY (idEquipe) REFERENCES TB_EQUIPE(id),
    FOREIGN KEY (idNivel) REFERENCES TB_NIVEL(id)
);

CREATE TABLE TB_CRITERIOS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    peso DECIMAL(5,2) NOT NULL
);

-- Indicadores para todos os níveis (Grupo ALL)
INSERT INTO TB_CRITERIOS (nome, peso) VALUES
('boa_relacao_colegas', 1.0),
('participacao_novos_projetos', 1.0),
('engajamento_supervisao', 1.0),
('uso_celular', -1.0),
('engajamento_cultura', 1.0),
('pontualidade', -1.0),
('criacao_novos_projetos', 1.0);

-- Indicadores para Nível 1, Nível 2 e Exclusivo
INSERT INTO TB_CRITERIOS (nome, peso) VALUES
('elogios_atendimento_externo', 1.0),
('nota_1_plausivel', -1.0);

-- Indicadores para Nível 3
INSERT INTO TB_CRITERIOS (nome, peso) VALUES
('elogio_interno_auxilio', 1.0),
('retorno_analise', -1.0);

-- Indicadores para Conversão
INSERT INTO TB_CRITERIOS (nome, peso) VALUES
('elogio_atendimento_externo_conversao', 1.0),
('retorno_conversao', -1.0);

CREATE TABLE TB_AVALIACOES (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    trimestre VARCHAR(25) NOT NULL,
    criterio INT NOT NULL,
    valor INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES TB_USUARIO(Id),
    FOREIGN KEY (criterio) REFERENCES TB_CRITERIOS(id)
);


CREATE TABLE TB_CLIENTES (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cnpjcpf VARCHAR(20) UNIQUE,
    serial VARCHAR(50) UNIQUE,
    cliente VARCHAR(100) NOT NULL,
    whatsapp VARCHAR(20) DEFAULT NULL,
    data_conclusao DATE DEFAULT NULL,
    horas_adquiridas INT NOT NULL,
    horas_utilizadas INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    faturamento ENUM('BRINDE','FATURADO') NOT NULL DEFAULT 'BRINDE',
    valor_faturamento DECIMAL(10,2) DEFAULT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE TB_TREINAMENTOS (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data DATE NOT NULL,
    hora TIME NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'TREINAMENTO',  -- TREINAMENTO, INSTALACAO ou AMBOS
    duracao INT NOT NULL DEFAULT 30,                   -- Duração do agendamento em minutos
    cliente_id INT NOT NULL,                           -- Chave estrangeira para TB_CLIENTES
    sistema VARCHAR(50) NOT NULL,
    consultor VARCHAR(50) NOT NULL,
    status ENUM('PENDENTE','CONCLUIDO','CANCELADO') DEFAULT 'PENDENTE',
    observacoes TEXT,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    dt_ini DATETIME,
    dt_fim DATETIME,
    total_tempo TIME,
    CONSTRAINT fk_cliente FOREIGN KEY (cliente_id) REFERENCES TB_CLIENTES(id)
);

CREATE TABLE TB_NOTIFICACOES (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    mensagem TEXT NOT NULL,
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    cliente_id INT,
    FOREIGN KEY (cliente_id) REFERENCES TB_CLIENTES(id)
);


CREATE TABLE IF NOT EXISTS TB_RECORRENTES (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  situacao     VARCHAR(100) NOT NULL,
  resolvido    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME              NULL,
  resposta     TEXT                  NULL,
  INDEX idx_resolvido  (resolvido),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS TB_RECORRENTES_CARDS (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recorrente_id INT UNSIGNED NOT NULL,
  card_num      VARCHAR(20)  NOT NULL COMMENT 'Ex: 22640',
  PRIMARY KEY (id),
  KEY idx_recorrente (recorrente_id),
  CONSTRAINT fk_recorrentes_cards
    FOREIGN KEY (recorrente_id)
      REFERENCES TB_RECORRENTES(id)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
