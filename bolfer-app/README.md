# Bolfer Desktop

Aplicativo desktop opcional da equipe Bolfer.

## O que esta incluido

- Cliente desktop em Electron + React.
- Login por token consumindo a API do site.
- Dashboard com resumo operacional.
- Gestao de pedidos.
- Visualizacao de usuarios, inventario e ajuste de coins.
- Leitura de logs.
- Rich Presence opcional no Discord.

## Antes de rodar

1. Aplique no site a migration equivalente para criar os tokens da API administrativa.
2. Garanta que o site esteja servindo as rotas `/api/desktop/*`.
3. No app, informe a URL completa da API.

Exemplos:

- `http://localhost:8000/api/desktop`
- `https://seudominio.com/api/desktop`

Se o CBW estiver rodando localmente em `localhost:8000`, essa e a URL correta para o app desktop.

## Scripts

- `npm install`
- `npm run dev` abre o Vite e o Electron juntos para desenvolvimento.
- `npm run build` gera apenas os arquivos web em `dist`.
- `npm start` abre o aplicativo Electron usando o build atual.
- `npm run dist:dir` gera a versao empacotada sem instalador.
- `npm run dist:win` gera os executaveis do Windows em `release`.

## Usar sem VS Code

Se voce nao quiser mais abrir o VS Code para rodar `npm run dev`, use este fluxo:

1. Rode `npm run dist:win` uma vez.
2. Abra a pasta `release`.
3. Use o executavel portatil `Bolfer Desktop 0.1.0.exe` ou o instalador `Bolfer Desktop Setup 0.1.0.exe`.

Depois disso, o app abre como programa normal do Windows, sem precisar iniciar o projeto pelo terminal.

## Observacoes

- O site continua independente do app.
- Tickets aparecem como espaco preparado no app, mas o backend atual ainda nao possui essa entidade.
- Configure `VITE_DISCORD_APP_ID` no `.env` local para habilitar o Rich Presence.
- No fluxo atual, apenas o `App ID` e usado pelo Rich Presence. `VITE_DISCORD_PUBLIC_KEY` fica reservado para futuras integracoes.
- O Rich Presence so funciona quando o `bolfer-app` esta aberto no Electron. Se voce abrir apenas a interface web no navegador, o Discord nao recebe status.
- Como o executavel e local e sem assinatura digital, o Windows pode mostrar um aviso na primeira abertura.
