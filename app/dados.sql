CREATE DATABASE osaudatex;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    senha VARCHAR(255),
    tipo ENUM('admin','tecnico','usuario','oficina','audatex') DEFAULT 'usuario',
    posto_graduacao VARCHAR(10),
    nome_guerra VARCHAR(20),
    om VARCHAR(20),
    status ENUM('ativo','inativo','pendente') DEFAULT 'ativo',
    data_cadastro DATETIME
);

INSERT INTO usuarios (nome, email, senha, tipo, posto_graduacao, nome_guerra)
VALUES ('Diego Rodrigues Cristaldo', 'diegorcristaldo@hotmail.com', 
        '$2y$10$0.nnefQKjxTufdCaqfJa4O5P5zAFECQ/pZJXgqq/HTqw3nWYyH76m', 
        'admin', '2°Sgt', 'Diego');
-- A senha acima é: 123456 (já criptografada com password_hash)

CREATE TABLE chamados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NOT NULL,
    arquivos TEXT,
    prioridade ENUM('baixa','media','alta') DEFAULT 'baixa',
    data_abertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_usuario_abriu INT NOT NULL,
    id_tecnico_responsavel INT DEFAULT NULL,
    id_equipamento INT DEFAULT NULL,
    placa_veiculo VARCHAR(10),
    marca_veiculo VARCHAR(50),
    modelo_veiculo VARCHAR(50),
    ano_veiculo INT,
    cor_veiculo VARCHAR(30),
    tipo_servico ENUM('colisao','mecanica','eletrica','funilaria','pintura','preventiva'),
    codigo_cotacao_audatex VARCHAR(100),
    total_pecas DECIMAL(10,2) DEFAULT 0,
    total_materiais DECIMAL(10,2) DEFAULT 0,
    total_geral DECIMAL(10,2) DEFAULT 0,
    status_os ENUM('cotacao_pendente','em_cotacao','cotado','aguardando_aprovacao','aprovado','executando','concluido','cancelado'),
    data_cotacao DATETIME,
    operador_audatex_id INT,
    observacoes_audatex TEXT,
    data_inicio_cotacao DATETIME,
    data_solicitacao_aprovacao DATETIME,
    observacoes_aprovacao TEXT,
    contato_responsavel VARCHAR(100),
    telefone_contato VARCHAR(20),
    data_aprovacao DATETIME,
    data_conclusao DATETIME,
    FOREIGN KEY (operador_audatex_id) REFERENCES usuarios(id);
    FOREIGN KEY (id_usuario_abriu) REFERENCES usuarios(id)
);

CREATE TABLE comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_chamado INT NOT NULL,
    id_usuario INT NOT NULL,
    comentario TEXT NOT NULL,
    lido TINYINT(1) DEFAULT 0,
    data DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_chamado) REFERENCES chamados(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE chamados_arquivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(500) NOT NULL,
    data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE
);

CREATE TABLE historico_chamados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT NOT NULL,
    tecnico_id INT DEFAULT NULL,
    acao VARCHAR(100) NOT NULL,
    observacao TEXT,
    data_acao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE,
    FOREIGN KEY (tecnico_id) REFERENCES usuarios(id)
);

CREATE TABLE recuperacao_senha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    codigo VARCHAR(6) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela para itens da cotação Audatex
CREATE TABLE os_itens_cotacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT NOT NULL,
    tipo ENUM('peca','mao_obra','material'),
    codigo_audatex VARCHAR(50),
    descricao VARCHAR(200) NOT NULL,
    quantidade DECIMAL(8,2) DEFAULT 1,
    valor_unitario DECIMAL(10,2),
    valor_total DECIMAL(10,2),
    acao ENUM('substituir','recuperar','reutilizar') DEFAULT 'substituir',
    status ENUM('pendente','aprovado','rejeitado') DEFAULT 'pendente',
    observacoes TEXT,
    data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE
);

-- Tabela de histórico específico para OS Veículos
CREATE TABLE historico_os_veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT NOT NULL,
    usuario_id INT NOT NULL,
    acao VARCHAR(100) NOT NULL,
    detalhes TEXT,
    data_acao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de templates para serviços comuns
CREATE TABLE os_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    tipo_servico ENUM('colisao','mecanica','eletrica','funilaria','pintura','preventiva'),
    itens_json TEXT, -- JSON com peças/tempos padrão
    tempo_estimado DECIMAL(4,2),
    valor_estimado DECIMAL(10,2),
    ativo TINYINT(1) DEFAULT 1
);

-- Criar tabela de notificações para registrar todas as atualizações
CREATE TABLE notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    chamado_id INT NOT NULL,
    tipo ENUM('comentario', 'atualizacao', 'atribuicao', 'fechamento') NOT NULL,
    mensagem TEXT NOT NULL,
    lida TINYINT(1) DEFAULT 0,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (chamado_id) REFERENCES chamados(id) ON DELETE CASCADE
);

-- Criar índice para melhor performance
CREATE INDEX idx_notificacoes_usuario_lida ON notificacoes(usuario_id, lida);
CREATE INDEX idx_notificacoes_data ON notificacoes(data_criacao DESC);