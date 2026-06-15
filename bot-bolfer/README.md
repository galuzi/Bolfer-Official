# Bot Bolfer

Bot em `Node.js` com `discord.js v14` focado em embeds visuais para canais como boas-vindas, regras, cargos, anúncios, eventos e suporte.

## O que ja vem pronto

- `/setup` publica ou atualiza os paineis base do servidor
- `/republicar` reenvia um painel especifico
- `/anuncio` cria comunicados padronizados
- `/evento` cria cards de evento padronizados
- `/embed criar` envia um embed customizado
- `/embed editar` edita uma mensagem do proprio bot usando o ID
- `data/messages.json` guarda os IDs das mensagens base para manutencao futura

## Estrutura

```text
.
|-- data/
|-- src/
|   |-- commands/
|   |-- config/
|   |-- embeds/
|   |-- events/
|   |-- services/
|   |-- storage/
|   `-- utils/
|-- .env.example
|-- index.js
`-- package.json
```

## Instalacao

```bash
npm install
```

## Configuracao

1. Deixe seu `.env` com pelo menos:

```env
DISCORD_TOKEN=...
CHANNEL_ID=...
REGRAS_URL=https://discord.com/channels/...
CARGOS_URL=https://discord.com/channels/...
SUPORTE_URL=https://discord.com/channels/...
BEM_VINDO_URL=https://discord.com/channels/...
```

2. O bot tenta descobrir o `GUILD_ID` pelos links dos canais. Se preferir, informe `GUILD_ID` manualmente.
3. URLs com placeholder como `SEU_SERVER_ID` sao ignoradas ate serem trocadas por links reais.

## Execucao

```bash
npm start
```

## Validacao local

```bash
npm run check
```

## Permissoes recomendadas do bot

- `View Channels`
- `Send Messages`
- `Embed Links`
- `Attach Files`
- `Read Message History`
- `Manage Messages`
- `Use Slash Commands`

Se voce quiser autoatribuir cargos depois, tambem ative `Manage Roles`.
