# Cozinha Guiada

Uma experiência educacional de visão computacional aplicada à Gastronomia.
A pessoa escolhe uma receita simples, segue orientações passo a passo, mostra a bancada ou o preparo pela câmera e recebe uma validação visual curta antes de avançar.

---

## Visão geral

A Cozinha Guiada é um assistente de bancada educativo. Ela ajuda a:

- Organizar ingredientes e utensílios antes de começar
- Acompanhar etapas visualmente observáveis de uma receita
- Tornar a experiência culinária mais interativa e guiada

O sistema **não substitui** conhecimento culinário, avaliação humana ou supervisão de segurança alimentar. Ele é apresentado explicitamente como um apoio visual e educativo.

---

## Arquitetura

```
Navegador
  -> câmera (getUserMedia)
  -> captura de foto
  -> POST /session/analyze-step (multipart/form-data)
  -> Laravel valida e processa em memória
  -> VisionProvider (Bedrock ou Mock)
  -> Regras determinísticas (StepEvaluationService)
  -> JSON de resposta
  -> JavaScript atualiza a interface
```

Sem banco de dados, sem filas, sem WebSockets, sem processos persistentes.
Funciona em request-response tradicional — ideal para hospedagem compartilhada.

---

## Tecnologias

| Camada         | Tecnologia                              |
|----------------|-----------------------------------------|
| Framework PHP  | Laravel 13 (PHP 8.2+)                  |
| Renderização   | Blade                                   |
| CSS            | Tailwind CSS 3 + PostCSS               |
| JavaScript     | Vanilla JS moderno (sem framework)      |
| Build frontend | Vite                                    |
| IA / Visão     | Amazon Bedrock — Amazon Nova Lite       |
| AWS SDK        | aws/aws-sdk-php                         |
| Sessão         | Laravel Session (file driver)           |
| Testes         | PHPUnit (nativo Laravel)                |

---

## Como rodar localmente

### Pré-requisitos

- PHP 8.2+
- Composer
- Node.js 20+ e npm
- (Opcional) Credenciais AWS para modo Bedrock

### Instalação

```bash
# Clone ou baixe o projeto
cd cozinha-guiada

# Instalar dependências PHP
composer install

# Copiar configuração de ambiente
cp .env.example .env

# Gerar chave da aplicação
php artisan key:generate

# Instalar dependências frontend
npm install

# Build do frontend (para desenvolvimento com hot-reload)
npm run dev

# Em outro terminal, iniciar o servidor
php artisan serve
```

Acesse: **http://localhost:8000**

---

## Como configurar o modo mock

O modo mock não requer nenhuma credencial AWS. É o padrão para desenvolvimento.

No `.env`:

```env
VISION_PROVIDER=mock
```

Com esse modo ativo:
- A câmera continua funcionando normalmente
- A análise retorna respostas simuladas (não depende da foto)
- Na interface aparece o painel de cenários para escolher o comportamento simulado:
  - Etapa concluída
  - Item ausente
  - Imagem ruim
  - Análise incerta

---

## Como configurar o Amazon Bedrock

1. Crie ou use uma conta AWS com acesso ao Amazon Bedrock
2. Habilite o modelo `amazon.nova-lite-v1:0` na região `us-east-1`
3. Crie um IAM user com permissão `bedrock:InvokeModel`
4. Copie as credenciais para o `.env`:

```env
VISION_PROVIDER=bedrock
BEDROCK_MODEL_ID=amazon.nova-lite-v1:0
AWS_ACCESS_KEY_ID=sua_key_aqui
AWS_SECRET_ACCESS_KEY=sua_secret_aqui
AWS_DEFAULT_REGION=us-east-1
```

5. Reinicie o servidor: `php artisan serve`

A interface passa a exibir **"IA conectada"** no cabeçalho.

---

## Como rodar os testes

```bash
# Todos os testes
php artisan test

# Somente testes unitários
php artisan test --testsuite=Unit

# Somente testes de feature
php artisan test --testsuite=Feature

# Com saída detalhada
php artisan test --verbose
```

Os testes **não** requerem câmera física, conexão AWS ou banco de dados.
Tudo é executado com o MockVisionProvider e sessões em memória.

---

## Como gerar o build do frontend

O build de CSS e JS deve ser feito **localmente** antes do deploy.
O servidor DreamHost (hospedagem compartilhada) não executa Node.js.

```bash
# Build otimizado para produção
npm run build
```

Os arquivos serão gerados em `public/build/`.
Envie essa pasta junto com o restante do projeto no deploy.

---

## Como publicar na DreamHost

Consulte o arquivo **DEPLOY_DREAMHOST.md** para o guia completo e detalhado.

Resumo:
1. Criar subdomínio apontando para `public/`
2. Enviar projeto via Git ou SFTP
3. `composer install --no-dev --optimize-autoloader`
4. Configurar `.env` com credenciais
5. `php artisan config:cache && php artisan route:cache`
6. Enviar os arquivos de `public/build/` (build feito localmente)

---

## Privacidade

- Fotos são **processadas em memória** e nunca salvas em disco
- Nenhuma imagem é registrada em logs
- Nenhum dado pessoal é enviado ao Bedrock
- Sem reconhecimento facial
- Sem identificação de pessoas
- Sem cadastro obrigatório
- Sem rastreamento de dados pessoais

---

## Limites da ferramenta

A Cozinha Guiada:

- NÃO avalia sabor, aroma ou textura
- NÃO garante higiene ou segurança alimentar
- NÃO substitui supervisão humana qualificada
- NÃO confirma ponto de cozimento ou temperatura
- NÃO faz alegações nutricionais ou médicas

O feedback do modelo é visual e educativo. A pessoa sempre pode avançar manualmente.

---

## Possíveis evoluções

**Receitas e conteúdo:** mais receitas (saladas, massas, sobremesas), painel de cadastro por professores de Gastronomia, painel de critérios visuais por etapa, receitas com vídeos de demonstração.

**Usuários e histórico:** cadastro opcional de usuários, histórico de sessões, banco de dados (SQLite para MySQL/PostgreSQL), painel de turmas para laboratórios e oficinas.

**Tecnologia e IA:** integração com Amazon Rekognition para detecção personalizada, modelos customizados para utensílios e ingredientes, registro opcional de imagens com consentimento explícito, integração com balança digital, integração com sensores de temperatura.

**Acessibilidade:** acessibilidade por voz (comando e resposta), legendas automáticas, modo alto contraste.

**Institucional:** modo para laboratórios e oficinas presenciais, modo para aulas remotas, relatórios de desempenho por turma, exportação de histórico em PDF.

---

## Estrutura de arquivos

```
cozinha-guiada/
├── app/
│   ├── Contracts/VisionProvider.php
│   ├── Http/Controllers/
│   │   ├── RecipeController.php
│   │   ├── SessionController.php
│   │   └── StepAnalysisController.php
│   ├── Providers/AppServiceProvider.php
│   ├── Services/
│   │   ├── BedrockVisionProvider.php
│   │   ├── MockVisionProvider.php
│   │   ├── RecipeService.php
│   │   └── StepEvaluationService.php
│   └── ValueObjects/VisionAnalysisResult.php
├── config/
│   ├── recipes.php
│   └── vision.php
├── resources/
│   ├── css/app.css
│   ├── js/app.js
│   └── views/
│       ├── layouts/app.blade.php
│       └── recipes/
│           ├── show.blade.php
│           └── complete.blade.php
├── routes/web.php
├── tests/
│   ├── Feature/StepAnalysisTest.php
│   └── Unit/
│       ├── VisionAnalysisResultTest.php
│       └── StepEvaluationServiceTest.php
├── .env.example
├── README.md
└── DEPLOY_DREAMHOST.md
```
