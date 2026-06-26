# Deploy na DreamHost — Guia Completo

Este guia cobre o processo de implantação da Cozinha Guiada em hospedagem compartilhada DreamHost.

> **Importante:** O build do frontend (Tailwind/Vite) deve ser feito **na sua máquina local** antes do deploy. O servidor DreamHost não executa Node.js.

---

## Pré-requisitos no DreamHost

- Conta DreamHost com hospedagem compartilhada (Shared Starter, DreamPress ou superior)
- SSH habilitado no painel (Manage Users > Edit > Shell type: Bash)
- PHP 8.2 disponível (verificar em PHP Settings no painel)
- Composer disponível via SSH (já incluso no DreamHost)

---

## 1. Criar subdomínio ou domínio

No painel DreamHost:

1. Acesse **Websites > Add a Domain / Subdomain**
2. Defina o subdomínio, por exemplo: `cozinha.seudominio.com.br`
3. No campo **Web Directory**, aponte para:
   ```
   /home/USUARIO/cozinha-guiada/public
   ```
   > Substitua `USUARIO` pelo seu nome de usuário DreamHost.
4. Habilite **HTTPS** (Let's Encrypt está disponível gratuitamente)
5. Salve e aguarde a propagação (pode levar alguns minutos)

---

## 2. Build do frontend (na sua máquina local)

```bash
# Na sua máquina local, dentro do projeto
npm install
npm run build
```

Os arquivos compilados estarão em `public/build/`.  
Eles **precisam** ser enviados junto com o projeto.

---

## 3. Enviar o projeto para o servidor

### Opção A — Git (recomendado)

```bash
# Na sua máquina local
git init
git add .
git commit -m "Initial deploy"

# Configure um repositório remoto (GitHub, GitLab, etc.)
git remote add origin https://github.com/seu-usuario/cozinha-guiada.git
git push -u origin main
```

No servidor via SSH:

```bash
ssh USUARIO@seudominio.com.br

# Clone o projeto
cd /home/USUARIO/
git clone https://github.com/seu-usuario/cozinha-guiada.git cozinha-guiada
```

Para atualizações futuras:

```bash
cd /home/USUARIO/cozinha-guiada
git pull origin main
```

### Opção B — SFTP (FileZilla ou similar)

1. Conecte via SFTP com suas credenciais DreamHost
2. Crie a pasta `/home/USUARIO/cozinha-guiada/`
3. Envie todos os arquivos, incluindo `public/build/`
4. **Não envie** a pasta `node_modules/`
5. **Não envie** a pasta `.git/` (opcional)
6. **Não envie** o arquivo `.env` por SFTP — crie-o diretamente no servidor

---

## 4. Acessar o servidor via SSH

```bash
ssh USUARIO@seudominio.com.br
cd /home/USUARIO/cozinha-guiada
```

---

## 5. Instalar dependências PHP

```bash
# No servidor, dentro do diretório do projeto
composer install --no-dev --optimize-autoloader
```

> Se o Composer não estiver no PATH, tente: `php /usr/local/php82/bin/composer install ...`

---

## 6. Configurar o arquivo .env

```bash
# Copia o exemplo
cp .env.example .env

# Edita com nano ou vim
nano .env
```

Configure os seguintes valores:

```env
APP_NAME="Cozinha Guiada"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cozinha.seudominio.com.br

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Inicialmente em modo mock para testar
VISION_PROVIDER=mock
```

Salve e feche (`Ctrl+X`, `Y`, `Enter` no nano).

---

## 7. Gerar a APP_KEY

```bash
php artisan key:generate
```

Verifique que o `.env` agora tem `APP_KEY=base64:...`.

---

## 8. Ajustar permissões de storage e cache

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R USUARIO:USUARIO storage bootstrap/cache
```

---

## 9. Otimizar para produção

```bash
# Cache de configuração (obrigatório em produção)
php artisan config:cache

# Cache de rotas
php artisan route:cache

# Cache de views (opcional mas recomendado)
php artisan view:cache
```

> Sempre que alterar o `.env` ou arquivos de config, rode `php artisan config:cache` novamente.

---

## 10. Verificar a pasta public/build

Confirme que os assets compilados estão presentes:

```bash
ls public/build/
# Deve listar algo como:
# assets/  manifest.json
```

Se a pasta estiver vazia, você esqueceu de rodar `npm run build` localmente e enviar os arquivos.

---

## 11. Testar em modo mock

Acesse no navegador: `https://cozinha.seudominio.com.br`

- A interface deve carregar normalmente
- O cabeçalho deve mostrar **"Modo simulado"**
- A câmera deve funcionar após autorização do navegador
- Os cenários de mock devem estar disponíveis no painel lateral

---

## 12. Ativar o Bedrock em produção

Após confirmar que tudo funciona em modo mock:

```bash
nano .env
```

Altere:

```env
VISION_PROVIDER=bedrock
AWS_ACCESS_KEY_ID=sua_access_key
AWS_SECRET_ACCESS_KEY=sua_secret_key
AWS_DEFAULT_REGION=us-east-1
BEDROCK_MODEL_ID=amazon.nova-lite-v1:0
```

Atualize o cache:

```bash
php artisan config:cache
```

Recarregue a página — o cabeçalho deve mostrar **"IA conectada"**.

---

## 13. Configurações AWS necessárias

Antes de ativar o Bedrock, certifique-se de:

1. **Habilitar o modelo no Bedrock Console:**
   - Acesse: https://console.aws.amazon.com/bedrock
   - Vá em **Model access**
   - Habilite **Amazon Nova Lite** na região `us-east-1`

2. **Permissão IAM mínima para o usuário:**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "bedrock:InvokeModel",
        "bedrock:InvokeModelWithResponseStream"
      ],
      "Resource": "arn:aws:bedrock:us-east-1::foundation-model/amazon.nova-lite-v1:0"
    }
  ]
}
```

3. **Nunca** use as credenciais root da AWS. Crie um IAM user dedicado.

---

## 14. Checklist final de deploy

```
[ ] Subdomínio criado apontando para /home/USUARIO/cozinha-guiada/public
[ ] HTTPS habilitado
[ ] PHP 8.2+ configurado no painel DreamHost
[ ] Arquivos do projeto enviados para o servidor
[ ] public/build/ enviado com os assets compilados
[ ] composer install --no-dev executado com sucesso
[ ] .env configurado (APP_KEY gerada, SESSION_DRIVER=file)
[ ] Permissões de storage e bootstrap/cache ajustadas (775)
[ ] php artisan config:cache executado
[ ] php artisan route:cache executado
[ ] Teste em modo mock bem-sucedido
[ ] (Produção) Credenciais AWS configuradas e modelo Bedrock habilitado
[ ] (Produção) APP_DEBUG=false
```

---

## 15. Solução de problemas comuns

### Página em branco ou erro 500

```bash
# Verifique os logs
cat storage/logs/laravel.log | tail -50
```

### "No application encryption key has been specified"

```bash
php artisan key:generate
php artisan config:cache
```

### Sessão não persiste entre requisições

Confirme no `.env`:
```env
SESSION_DRIVER=file
```
E verifique permissões:
```bash
chmod 775 storage/framework/sessions
```

### Assets CSS/JS não carregam (404)

Confirme que `public/build/manifest.json` existe.  
Refaça o build localmente e envie novamente a pasta `public/build/`.

### Bedrock retorna erro de autenticação

- Verifique se as credenciais estão corretas no `.env`
- Confirme que o modelo está habilitado na região correta
- Rode `php artisan config:cache` após alterar o `.env`

### Câmera não funciona

A câmera requer HTTPS em produção. Confirme que o certificado SSL está ativo no domínio.

---

## 16. Atualizações de deploy

Para atualizar o projeto após mudanças:

```bash
# Na sua máquina local
npm run build          # Rebuild do frontend
git add .
git commit -m "Update"
git push

# No servidor
cd /home/USUARIO/cozinha-guiada
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Se apenas os assets mudaram, basta reenviar a pasta `public/build/` via SFTP.
