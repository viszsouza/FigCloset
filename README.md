# VISZ Closet — Plataforma de Aluguel de Roupas de Marca

Aplicação completa em **PHP puro (PDO) + MySQL + HTML/CSS/JS**, sem frameworks e sem
build step — pronta para rodar em qualquer hospedagem compartilhada com PHP 8+ e MySQL.

## 1. Requisitos

- PHP 8.0 ou superior, com extensão `pdo_mysql` habilitada
- MySQL 5.7+ ou MariaDB 10.3+
- Apache com `mod_rewrite`/`mod_headers` (padrão na maioria das hospedagens compartilhadas)

## 2. Instalação

1. Envie todos os arquivos desta pasta para o seu servidor (via FTP/File Manager do cPanel, etc.).
2. Crie um banco de dados MySQL vazio (ex.: `visz_locacoes`) e um usuário com acesso a ele.
3. Importe, **nesta ordem**, os arquivos SQL pelo phpMyAdmin (ou linha de comando):
   1. `sql/schema.sql` — cria as tabelas e insere as configurações + o usuário admin base
   2. `sql/seed_data.sql` — popula marcas, categorias, 30 roupas de demonstração, tamanhos, imagens (via Picsum, apenas para visualização) e clientes fictícios

   > **Já tinha o banco instalado antes da atualização de entrega?** Rode também
   > `sql/migration_entrega.sql` uma única vez. Em instalações novas isso não é
   > necessário — o `schema.sql` já traz os campos de entrega.
4. Edite `config/config.php` com os dados reais do seu banco:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'visz_locacoes');
   define('DB_USER', 'seu_usuario');
   define('DB_PASS', 'sua_senha');
   ```
   Se o site ficar em uma subpasta (ex.: `seudominio.com.br/loja`), ajuste também `BASE_URL`.
5. **Defina as senhas reais** do admin e dos clientes de demonstração. Como o schema
   vem com um hash provisório, rode uma vez (via linha de comando no seu servidor, ou
   localmente com PHP instalado):
   ```
   php sql/gerar_hashes.php
   ```
   Isso vai gerar dois comandos `UPDATE` prontos — execute-os no phpMyAdmin. Por padrão:
   - **Admin**: `admin@viszcloset.com.br` / senha que você definir (sugestão no script: `admin123`)
   - **Clientes fictícios**: qualquer um dos e-mails em `sql/seed_data.sql` / senha `senha123`
6. Acesse `seudominio.com.br/index.php` para o site e `seudominio.com.br/admin/login.php`
   para o painel administrativo.

## 3. Estrutura de pastas

```
config/          Configuração e conexão PDO
includes/        Funções centrais, header/footer do site e do admin, partial de card de produto
assets/          CSS e JS (sem build step — edite direto)
admin/           Painel administrativo completo (protegido por sessão + role)
api/             Endpoints AJAX (favoritar peças)
sql/             Schema, seed de demonstração e gerador de senhas
*.php (raiz)     Páginas públicas do site
```

## 4. O que já está implementado (funcional, não apenas visual)

- Cadastro/login/logout de clientes com senha com hash seguro (`password_hash`/`password_verify`)
- Cadastro de medidas e **motor de recomendação de tamanho** com tolerância configurável
  pelo admin (Configurações → Motor de recomendação)
- Catálogo com filtros combináveis (categoria, marca, tamanho, cor, estilo, preço,
  disponibilidade por data, busca textual) e ordenação
- Página de produto com galeria, ficha técnica, tabela de medidas por tamanho,
  compatibilidade percentual e verificação de disponibilidade por período
- Sacola (carrinho em sessão) → revisão → criação de pedido com **código único**
  (`VISZ-XXXXXX`) → tela de confirmação com botão para o Instagram e "copiar código"
- **Pedidos com entrega**: endereço completo coletado no checkout (destinatário,
  telefone, CEP, rua, número, complemento, bairro, cidade, UF e observações), com opção
  de salvar no perfil. Taxa de entrega e regra de frete grátis são configuráveis pelo
  admin, e o endereço aparece no painel com atalho para o Google Maps.
- Acompanhamento de pedido por código, com timeline visual de status
- Área do cliente completa (visão geral, pedidos, medidas, favoritos, dados pessoais,
  endereço, segurança/senha)
- Painel administrativo: dashboard com métricas, CRUD completo de roupas (com imagens
  e medidas por tamanho), marcas e categorias (com reordenação), gerenciamento de
  pedidos com timeline de status, gerenciamento de clientes (bloquear/desbloquear,
  ver medidas/pedidos/favoritos), calendário (dia/semana/mês), configurações globais
- Segurança: PDO com *prepared statements* em 100% das queries, CSRF token em todos
  os formulários POST, sessões com `httponly`, soft delete de roupas com histórico de
  pedidos, proteção de rotas administrativas por sessão + verificação de `role`
- SEO: URLs com slug, meta tags, Open Graph, JSON-LD (`schema.org/Product`),
  sitemap.php dinâmico e robots.txt

## 5. Limitações conhecidas (documentadas para você evoluir)

Este é um projeto **real e funcional**, mas alguns pontos foram simplificados de
propósito para caber em uma primeira entrega enxuta — fica como roteiro do que
evoluir a seguir:

- **Confirmação via Instagram**: como a Meta não permite validar automaticamente uma
  DM enviada por um cliente, o fluxo atual é manual — o admin confere o código recebido
  no Instagram e muda o status no painel. A arquitetura (tabela `orders`, código único)
  já está pronta para, no futuro, plugar a API oficial da Meta/Instagram caso vocês
  tenham acesso a ela.
- **Recuperação de senha**: gera um token válido por 1h, mas como não há um serviço de
  e-mail configurado (SMTP/PHPMailer), o link é exibido diretamente na tela de
  "recuperar senha" com um aviso claro de que é um modo de demonstração. Para produção,
  troque esse trecho por um envio real de e-mail.
- **Estoque por tamanho vs. disponibilidade por data**: a checagem de disponibilidade
  (`availability`) é feita por peça e período, não por unidade individual de estoque.
  Ou seja, se uma peça tiver 2 unidades de "M" em estoque, o sistema ainda trata a peça
  como "uma reserva por vez" no período. Para lojas com múltiplas unidades da mesma
  peça, vale evoluir `availability` para referenciar uma unidade física específica.
- **Imagens de demonstração**: os 30 produtos de exemplo usam imagens do Picsum
  (`picsum.photos`) apenas para preencher o layout. Substitua pelas fotos reais no
  painel administrativo (campo "Imagens", uma URL por linha — pode ser link do seu
  Google Drive compartilhado publicamente, Cloudinary, etc., no mesmo espírito dos
  outros projetos VISZ).
- **Envio de e-mails transacionais** (confirmação de pedido, mudança de status) não
  está implementado — hoje o cliente acompanha tudo por `pedido-status.php` e pela área
  da conta. Dá para plugar um serviço de e-mail ou o EmailJS que vocês já usam em
  outros projetos para notificações simples.

## 6. Dados de demonstração incluídos

- 5 marcas, 8 categorias, 30 roupas com 2–4 tamanhos cada e 3 imagens cada
- 8 clientes fictícios com medidas cadastradas (para você já testar a recomendação de
  tamanho de cara)
- 1 usuário admin

Bom trabalho com o VISZ Closet! Qualquer ajuste de fluxo, cores ou textos é só pedir.
