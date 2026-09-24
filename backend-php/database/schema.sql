-- Schema do Vise Saúde Mental (MySQL 5.7+ / MariaDB 10.3+).
-- Idempotente: pode ser importado no phpMyAdmin ou executado pelo instalador.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  is_superuser TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ix_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS escolas (
  id INT NOT NULL AUTO_INCREMENT,
  nome VARCHAR(255) NOT NULL,
  municipio VARCHAR(255) NOT NULL,
  uf VARCHAR(2) NOT NULL,
  inep VARCHAR(20) NULL,
  responsavel VARCHAR(255) NULL,
  telefone VARCHAR(30) NULL,
  endereco VARCHAR(500) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY inep (inep)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS escola_user (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  escola_id INT NOT NULL,
  role VARCHAR(30) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ativo',
  vinculado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_escola_user_user_escola_role (user_id, escola_id, role),
  KEY ix_escola_user_user_id (user_id),
  KEY ix_escola_user_escola_id (escola_id),
  CONSTRAINT escola_user_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT escola_user_ibfk_2 FOREIGN KEY (escola_id) REFERENCES escolas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS series (
  id INT NOT NULL AUTO_INCREMENT,
  descricao VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY descricao (descricao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS turmas (
  id INT NOT NULL AUTO_INCREMENT,
  nome VARCHAR(255) NOT NULL,
  serie_id INT NOT NULL,
  turno VARCHAR(50) NOT NULL,
  escola_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_turmas_escola_nome (escola_id, nome),
  KEY ix_turmas_serie_id (serie_id),
  KEY ix_turmas_escola_id (escola_id),
  CONSTRAINT turmas_ibfk_1 FOREIGN KEY (serie_id) REFERENCES series (id),
  CONSTRAINT turmas_ibfk_2 FOREIGN KEY (escola_id) REFERENCES escolas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professor_turma (
  id INT NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  turma_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_professor_turma_user_turma (user_id, turma_id),
  KEY ix_professor_turma_user_id (user_id),
  KEY ix_professor_turma_turma_id (turma_id),
  CONSTRAINT professor_turma_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT professor_turma_ibfk_2 FOREIGN KEY (turma_id) REFERENCES turmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alunos (
  id INT NOT NULL AUTO_INCREMENT,
  nome VARCHAR(255) NOT NULL,
  sexo VARCHAR(30) NULL,
  data_nascimento DATE NOT NULL,
  cpf VARCHAR(11) NOT NULL,
  matricula VARCHAR(100) NULL,
  turma_id INT NULL,
  escola_id INT NOT NULL,
  telefone VARCHAR(30) NULL,
  responsavel VARCHAR(255) NULL,
  contato_responsavel VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_alunos_cpf_data_nascimento (cpf, data_nascimento),
  KEY ix_alunos_turma_id (turma_id),
  KEY ix_alunos_escola_id (escola_id),
  CONSTRAINT alunos_ibfk_1 FOREIGN KEY (turma_id) REFERENCES turmas (id),
  CONSTRAINT alunos_ibfk_2 FOREIGN KEY (escola_id) REFERENCES escolas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questionarios (
  id INT NOT NULL AUTO_INCREMENT,
  escola_id INT NOT NULL,
  pesquisador_id INT NOT NULL,
  nome VARCHAR(255) NOT NULL,
  descricao TEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'rascunho',
  publico_alvo VARCHAR(255) NULL,
  versao INT NOT NULL DEFAULT 1,
  parent_id INT NULL,
  compartilhado_na_escola TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_questionarios_escola_id (escola_id),
  KEY ix_questionarios_pesquisador_id (pesquisador_id),
  KEY ix_questionarios_parent_id (parent_id),
  CONSTRAINT questionarios_ibfk_1 FOREIGN KEY (escola_id) REFERENCES escolas (id),
  CONSTRAINT questionarios_ibfk_2 FOREIGN KEY (pesquisador_id) REFERENCES users (id),
  CONSTRAINT questionarios_ibfk_3 FOREIGN KEY (parent_id) REFERENCES questionarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id INT NOT NULL AUTO_INCREMENT,
  questionario_id INT NOT NULL,
  escola_id INT NOT NULL,
  nome VARCHAR(255) NOT NULL,
  ordem INT NOT NULL,
  cor VARCHAR(30) NULL,
  mensagem_avatar TEXT NULL,
  imagem_apoio VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_categorias_questionario_id (questionario_id),
  KEY ix_categorias_escola_id (escola_id),
  CONSTRAINT categorias_ibfk_1 FOREIGN KEY (questionario_id) REFERENCES questionarios (id),
  CONSTRAINT categorias_ibfk_2 FOREIGN KEY (escola_id) REFERENCES escolas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subcategorias (
  id INT NOT NULL AUTO_INCREMENT,
  categoria_id INT NOT NULL,
  escola_id INT NOT NULL,
  nome VARCHAR(255) NOT NULL,
  ordem INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_subcategorias_categoria_id (categoria_id),
  KEY ix_subcategorias_escola_id (escola_id),
  CONSTRAINT subcategorias_ibfk_1 FOREIGN KEY (categoria_id) REFERENCES categorias (id),
  CONSTRAINT subcategorias_ibfk_2 FOREIGN KEY (escola_id) REFERENCES escolas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perguntas (
  id INT NOT NULL AUTO_INCREMENT,
  categoria_id INT NOT NULL,
  subcategoria_id INT NULL,
  escola_id INT NOT NULL,
  tipo VARCHAR(50) NOT NULL,
  texto TEXT NOT NULL,
  ordem INT NOT NULL,
  obrigatoria TINYINT(1) NOT NULL DEFAULT 0,
  peso DECIMAL(8,2) NOT NULL DEFAULT 1.00,
  imagem VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_perguntas_categoria_id (categoria_id),
  KEY ix_perguntas_subcategoria_id (subcategoria_id),
  KEY ix_perguntas_escola_id (escola_id),
  CONSTRAINT perguntas_ibfk_1 FOREIGN KEY (categoria_id) REFERENCES categorias (id),
  CONSTRAINT perguntas_ibfk_2 FOREIGN KEY (subcategoria_id) REFERENCES subcategorias (id),
  CONSTRAINT perguntas_ibfk_3 FOREIGN KEY (escola_id) REFERENCES escolas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opcoes_resposta (
  id INT NOT NULL AUTO_INCREMENT,
  pergunta_id INT NOT NULL,
  escola_id INT NOT NULL,
  descricao VARCHAR(500) NOT NULL,
  pontuacao INT NOT NULL,
  ordem INT NOT NULL,
  cor VARCHAR(30) NULL,
  emoji VARCHAR(50) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_opcoes_resposta_pergunta_id (pergunta_id),
  KEY ix_opcoes_resposta_escola_id (escola_id),
  CONSTRAINT opcoes_resposta_ibfk_1 FOREIGN KEY (pergunta_id) REFERENCES perguntas (id),
  CONSTRAINT opcoes_resposta_ibfk_2 FOREIGN KEY (escola_id) REFERENCES escolas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS regras_classificacao (
  id INT NOT NULL AUTO_INCREMENT,
  escola_id INT NOT NULL,
  categoria_id INT NULL,
  questionario_id INT NULL,
  min_score DECIMAL(10,2) NOT NULL,
  max_score DECIMAL(10,2) NOT NULL,
  rotulo VARCHAR(255) NOT NULL,
  descricao TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_regras_classificacao_escola_id (escola_id),
  KEY ix_regras_classificacao_categoria_id (categoria_id),
  KEY ix_regras_classificacao_questionario_id (questionario_id),
  CONSTRAINT regras_classificacao_ibfk_1 FOREIGN KEY (escola_id) REFERENCES escolas (id),
  CONSTRAINT regras_classificacao_ibfk_2 FOREIGN KEY (categoria_id) REFERENCES categorias (id),
  CONSTRAINT regras_classificacao_ibfk_3 FOREIGN KEY (questionario_id) REFERENCES questionarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aplicacoes_questionario (
  id INT NOT NULL AUTO_INCREMENT,
  questionario_id INT NOT NULL,
  escola_id INT NOT NULL,
  alvo_tipo VARCHAR(50) NOT NULL,
  turma_id INT NULL,
  aluno_id INT NULL,
  inicia_em DATETIME NULL,
  termina_em DATETIME NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'ativa',
  codigo_sala VARCHAR(12) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ix_aplicacoes_questionario_codigo_sala (codigo_sala),
  KEY ix_aplicacoes_questionario_questionario_id (questionario_id),
  KEY ix_aplicacoes_questionario_escola_id (escola_id),
  KEY ix_aplicacoes_questionario_turma_id (turma_id),
  KEY ix_aplicacoes_questionario_aluno_id (aluno_id),
  CONSTRAINT aplicacoes_questionario_ibfk_1 FOREIGN KEY (questionario_id) REFERENCES questionarios (id),
  CONSTRAINT aplicacoes_questionario_ibfk_2 FOREIGN KEY (escola_id) REFERENCES escolas (id),
  CONSTRAINT aplicacoes_questionario_ibfk_3 FOREIGN KEY (turma_id) REFERENCES turmas (id),
  CONSTRAINT aplicacoes_questionario_ibfk_4 FOREIGN KEY (aluno_id) REFERENCES alunos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS respostas (
  id INT NOT NULL AUTO_INCREMENT,
  aplicacao_id INT NOT NULL,
  aluno_id INT NOT NULL,
  pergunta_id INT NOT NULL,
  opcao_id INT NULL,
  texto TEXT NULL,
  tempo_gasto_ms INT NULL,
  dispositivo VARCHAR(255) NULL,
  responded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  client_uuid VARCHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_respostas_aplicacao_aluno_pergunta (aplicacao_id, aluno_id, pergunta_id),
  KEY ix_respostas_aplicacao_id (aplicacao_id),
  KEY ix_respostas_aluno_id (aluno_id),
  KEY ix_respostas_pergunta_id (pergunta_id),
  KEY ix_respostas_opcao_id (opcao_id),
  CONSTRAINT respostas_ibfk_1 FOREIGN KEY (aplicacao_id) REFERENCES aplicacoes_questionario (id),
  CONSTRAINT respostas_ibfk_2 FOREIGN KEY (aluno_id) REFERENCES alunos (id),
  CONSTRAINT respostas_ibfk_3 FOREIGN KEY (pergunta_id) REFERENCES perguntas (id),
  CONSTRAINT respostas_ibfk_4 FOREIGN KEY (opcao_id) REFERENCES opcoes_resposta (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resultados (
  id INT NOT NULL AUTO_INCREMENT,
  aplicacao_id INT NOT NULL,
  aluno_id INT NOT NULL,
  totais_json LONGTEXT NOT NULL,
  classificacao_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_resultados_aplicacao_aluno (aplicacao_id, aluno_id),
  KEY ix_resultados_aplicacao_id (aplicacao_id),
  KEY ix_resultados_aluno_id (aluno_id),
  CONSTRAINT resultados_ibfk_1 FOREIGN KEY (aplicacao_id) REFERENCES aplicacoes_questionario (id),
  CONSTRAINT resultados_ibfk_2 FOREIGN KEY (aluno_id) REFERENCES alunos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS avatars (
  id INT NOT NULL AUTO_INCREMENT,
  escola_id INT NULL,
  nome VARCHAR(255) NOT NULL,
  imagem_path VARCHAR(500) NOT NULL,
  mensagem_padrao TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_avatars_escola_id (escola_id),
  CONSTRAINT avatars_ibfk_1 FOREIGN KEY (escola_id) REFERENCES escolas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS termos_aceite (
  id INT NOT NULL AUTO_INCREMENT,
  aluno_id INT NOT NULL,
  versao VARCHAR(50) NOT NULL,
  texto_hash VARCHAR(64) NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent TEXT NULL,
  accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_termos_aceite_aluno_versao (aluno_id, versao),
  KEY ix_termos_aceite_aluno_id (aluno_id),
  CONSTRAINT termos_aceite_ibfk_1 FOREIGN KEY (aluno_id) REFERENCES alunos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personal_access_tokens (
  id INT NOT NULL AUTO_INCREMENT,
  tokenable_type VARCHAR(255) NOT NULL,
  tokenable_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  token VARCHAR(64) NOT NULL,
  abilities TEXT NULL,
  last_used_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY token (token),
  KEY ix_personal_access_tokens_tokenable (tokenable_type, tokenable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
