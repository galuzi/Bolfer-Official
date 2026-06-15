import {
  DEFAULT_API_BASE_URL,
  INVITE_FILTERS,
  LOCAL_API_BASE_URL,
  ORDER_FILTERS,
  USER_FILTERS,
  buildRoleOptions,
  fmtCount,
  fmtDate,
  fmtMoney,
  formatUnlockRequirement,
  getOrderSla,
  getStatusLabel,
  maskSensitiveValue,
  normalizeLegacyText,
  presenceLabel,
  safeList,
  tone,
} from './app-utils.js';
import { Badge, DataRow, Empty, Field, FilterPresets, Metric, Notice, Panel, SectionHero, Table, Timeline, WorkspaceCard } from './ui.jsx';

function maskIdentity(value, hideSensitiveInfo) {
  const normalized = String(value ?? '').trim();
  if (!hideSensitiveInfo || !normalized) {
    return normalized || '-';
  }

  return normalized.includes('@') ? maskSensitiveValue(normalized, 'email') : maskSensitiveValue(normalized);
}

function formatProductTypeLabel(value) {
  return value === 'conta' ? 'Conta' : 'Item';
}

function formatProductStock(value) {
  return value === null || value === '' || value === undefined ? 'Ilimitado' : fmtCount(value);
}

function buildProductAssetUrl(siteBaseUrl, assetPath) {
  const normalizedBase = String(siteBaseUrl ?? '').trim().replace(/\/+$/, '');
  const normalizedPath = String(assetPath ?? '').trim();
  if (!normalizedBase || !normalizedPath) {
    return '';
  }

  return normalizedPath.startsWith('http') ? normalizedPath : `${normalizedBase}${normalizedPath.startsWith('/') ? normalizedPath : `/${normalizedPath}`}`;
}

function describeUpdateStatus(updateState) {
  switch (updateState?.status) {
    case 'checking':
      return 'Verificando atualizações...';
    case 'available':
      return 'Nova versão encontrada.';
    case 'downloading':
      return `Baixando atualização (${Math.round(Number(updateState?.progress ?? 0))}%).`;
    case 'downloaded':
      return 'Atualização pronta para instalar.';
    case 'not-available':
      return 'Este desktop já está atualizado.';
    case 'error':
      return updateState?.lastError || 'Falha ao verificar atualizações.';
    case 'unsupported':
      return updateState?.message || 'As atualizações automáticas exigem a versão instalada do desktop.';
    default:
      return updateState?.message || 'Pronto para verificar novas versões.';
  }
}

export function PresenceStrip({ status, onTest, busy, compact = false, hideSensitiveInfo = false }) {
  return (
    <section className={`presence-strip ${compact ? 'compact' : ''}`.trim()}>
      <div className="presence-copy">
        <span className="eyebrow">Discord</span>
        <strong>Rich Presence</strong>
        <p>{status.text}</p>
        {status.clientId ? <span className="presence-detail">Client ID: {hideSensitiveInfo ? maskSensitiveValue(status.clientId, 'clientId') : status.clientId}</span> : null}
        {status.lastError && status.lastError !== status.text ? <span className="presence-detail">{status.lastError}</span> : null}
      </div>
      <div className="presence-actions">
        <Badge toneValue={status.toneValue}>{presenceLabel(status)}</Badge>
        <button type="button" className="ghost-button" onClick={onTest}>
          {busy ? 'Testando Discord...' : 'Testar Discord'}
        </button>
      </div>
    </section>
  );
}

export function LoginScreen({
  appInfo,
  apiInfo,
  login,
  setLogin,
  onSubmit,
  onVerifyApi,
  onOpenAdminPanel,
  presenceStatus,
  onTestDiscord,
  busy,
  notice,
  onDismissNotice,
}) {
  const setupSteps = [
    {
      id: 'api',
      label: '01',
      title: 'Conecte a API',
      text: 'A equipe pode operar no desktop sem abrir o site principal a cada ação.',
    },
    {
      id: 'auth',
      label: '02',
      title: 'Valide senha e 2FA',
      text: 'O desktop acompanha a segurança do painel web e pede o código do autenticador quando a conta exigir.',
    },
    {
      id: 'ops',
      label: '03',
      title: 'Gerencie a operação',
      text: 'Pedidos, usuários, logs e Discord ficam no mesmo fluxo de trabalho.',
    },
  ];

  return (
    <div className="login-layout">
      <section className="brand-column">
        <div className="brand-card brand-hero">
          <span className="eyebrow">Aplicativo Desktop da Equipe</span>
          <h1>Bolfer</h1>
          <p>
            Um posto de comando mais direto para moderadores e administradores. A proposta agora é reduzir atrito:
            menos cliques, mais contexto e uma leitura mais rápida do que merece atenção.
          </p>

          <div className="metric-grid">
            <Metric label="Operação" value="Fluxo centralizado" toneValue="good" hint="Pedidos, usuários e logs lado a lado." />
            <Metric label="Desktop" value={appInfo.platform} toneValue="neutral" hint={`Versão ${appInfo.version}`} />
            <Metric label="Discord" value={presenceLabel(presenceStatus)} toneValue={presenceStatus.toneValue} hint="Presença opcional para a equipe." />
          </div>

          <div className="step-grid">
            {setupSteps.map((step) => (
              <article key={step.id} className="step-card">
                <span>{step.label}</span>
                <strong>{step.title}</strong>
                <p>{step.text}</p>
              </article>
            ))}
          </div>
        </div>

        <div className="brand-card muted support-card">
          <div className="support-grid">
            <div>
              <span className="eyebrow">Saúde da API</span>
              <h3>{apiInfo ? 'Conexão validada' : 'Pronta para validar'}</h3>
              <p>
                {apiInfo
                  ? `${apiInfo.service || 'Serviço identificado'} respondeu e o desktop pode seguir para autenticação.`
                  : 'Valide a URL antes do login para evitar perder tempo com uma rota incorreta.'}
              </p>
            </div>
            <div className="stack-list">
              <DataRow label="URL de produção" value={DEFAULT_API_BASE_URL} />
              <DataRow label="URL local" value={LOCAL_API_BASE_URL} />
              <DataRow label="Sessão" value="Token por login, salvo localmente no desktop" />
              <DataRow label="Rich Presence" value="Só funciona no Electron aberto localmente" />
            </div>
          </div>
        </div>
      </section>

      <section className="form-column">
        <form className="auth-card" onSubmit={onSubmit}>
          <span className="eyebrow">Acesso</span>
          <h2>Entrar no desktop</h2>
          <p className="auth-copy">Configure a conexão uma vez e deixe o painel pronto para a moderação do dia a dia.</p>

          <Field
            label="URL da API"
            value={login.apiBaseUrl}
            onChange={(value) => setLogin((current) => ({ ...current, apiBaseUrl: value }))}
            placeholder={DEFAULT_API_BASE_URL}
          />
          <Field
            label="E-mail do admin"
            value={login.username}
            onChange={(value) =>
              setLogin((current) => ({
                ...current,
                username: value,
                twoFactorRequired: false,
                twoFactorSetupRequired: false,
                twoFactorMessage: '',
                twoFactorCode: '',
              }))
            }
            placeholder="equipe@bolfer.com"
          />
          <Field
            label="Senha"
            type="password"
            value={login.password}
            onChange={(value) =>
              setLogin((current) => ({
                ...current,
                password: value,
                twoFactorSetupRequired: false,
                twoFactorMessage: current.twoFactorRequired ? current.twoFactorMessage : '',
              }))
            }
            placeholder="Sua senha do painel"
          />
          {login.twoFactorRequired && !login.twoFactorSetupRequired ? (
            <Field
              label="Código 2FA ou recuperação"
              value={login.twoFactorCode}
              onChange={(value) => setLogin((current) => ({ ...current, twoFactorCode: value }))}
              placeholder="000000 ou código de recuperação"
              description={login.twoFactorMessage || 'Use o código atual do autenticador ou um dos códigos de recuperação da conta.'}
            />
          ) : null}
          <Field
            label="Discord Client ID"
            value={login.discordClientId}
            onChange={(value) => setLogin((current) => ({ ...current, discordClientId: value }))}
            placeholder="Opcional"
            description="Se preenchido, o desktop pode exibir status de presença no Discord."
          />

          {login.twoFactorSetupRequired ? (
            <div className="inline-hint danger">
              {login.twoFactorMessage || 'Esta conta precisa ativar o 2FA no painel web antes de usar o desktop.'}
            </div>
          ) : null}

          <label className="checkbox-row">
            <input
              type="checkbox"
              checked={login.presenceEnabled}
              onChange={(event) => setLogin((current) => ({ ...current, presenceEnabled: event.target.checked }))}
            />
            <span>Ativar Rich Presence quando houver Client ID configurado</span>
          </label>

          {notice ? <Notice notice={notice} onClose={onDismissNotice} /> : null}

          <div className="button-row">
            <button type="submit" className="primary-button">
              {busy === 'login' ? 'Entrando...' : login.twoFactorRequired ? 'Validar 2FA e entrar' : 'Entrar'}
            </button>
            <button type="button" className="secondary-button" onClick={onVerifyApi}>
              {busy === 'verify' ? 'Validando...' : 'Verificar API'}
            </button>
            {login.twoFactorSetupRequired ? (
              <button type="button" className="ghost-button" onClick={onOpenAdminPanel}>
                Abrir painel web
              </button>
            ) : null}
          </div>

          <PresenceStrip status={presenceStatus} onTest={onTestDiscord} busy={busy === 'presence'} compact />
        </form>
      </section>
    </div>
  );
}

export function Sidebar({ appInfo, session, view, setView, navBadges, navItems, hideSensitiveInfo }) {
  return (
    <aside className="sidebar">
      <div className="sidebar-brand">
        <span className="eyebrow">Bolfer</span>
        <h1>Desktop</h1>
        <p>Painel conectado ao site por API, com foco em resposta rápida da equipe.</p>
      </div>

      <div className="sidebar-session">
        <strong>{maskIdentity(session.admin.username, hideSensitiveInfo)}</strong>
        <span>{session.admin.role}</span>
      </div>

      <nav className="sidebar-nav">
        {safeList(navItems).map((item) => (
          <button key={item.id} type="button" className={`nav-button ${view === item.id ? 'active' : ''}`} onClick={() => setView(item.id)}>
            <span className="nav-copy">
              <strong>{item.label}</strong>
              <small>{item.eyebrow}</small>
            </span>
            {navBadges[item.id] ? <span className="nav-counter">{navBadges[item.id]}</span> : null}
          </button>
        ))}
      </nav>

      <div className="sidebar-footer">
        <span>v{appInfo.version}</span>
        <span>{appInfo.platform}</span>
      </div>
    </aside>
  );
}

export function Topbar({ viewMeta, session, summaryCards, onRefresh, onLogout, refreshing, busy, hideSensitiveInfo }) {
  return (
    <>
      <header className="topbar">
        <div className="topbar-copy">
          <span className="eyebrow">{viewMeta.eyebrow}</span>
          <h2>{viewMeta.label}</h2>
          <p>{viewMeta.description}</p>
        </div>

        <div className="topbar-actions">
          <div className="account-chip">
            <strong>{maskIdentity(session.admin.username, hideSensitiveInfo)}</strong>
            <span>{session.admin.role}</span>
          </div>

          <button type="button" className="ghost-button" onClick={onRefresh}>
            {refreshing ? 'Atualizando...' : 'Atualizar seção'}
          </button>
          <button type="button" className="secondary-button" onClick={onLogout}>
            {busy === 'logout' ? 'Saindo...' : 'Sair'}
          </button>
        </div>
      </header>

      <section className="workspace-strip">
        {summaryCards.map((card) => (
          <WorkspaceCard key={card.label} label={card.label} value={card.value} toneValue={card.toneValue} />
        ))}
      </section>
    </>
  );
}

export function DashboardView({
  data,
  statusLabels,
  onRefresh,
  onNavigate,
  tasks,
  activityFeed,
  onClearActivity,
  availableViews,
  healthItems,
  shortcutHints,
  onboardingVisible,
  onDismissOnboarding,
}) {
  const recentOrders = safeList(data?.recentOrders);
  const discordActivity = safeList(data?.recentDiscordActivity);
  const allowedViews = new Set(safeList(availableViews).map((item) => item.id));
  const modules = [
    {
      id: 'orders',
      label: 'Pedidos',
      toneValue: 'good',
      text: 'Fila, detalhes, notas e troca de status dentro do mesmo fluxo.',
      action: 'Abrir pedidos',
    },
    {
      id: 'users',
      label: 'Usuários',
      toneValue: 'neutral',
      text: 'Moderação, saldo e inventário com contexto mais claro para a equipe.',
      action: 'Abrir usuários',
    },
    {
      id: 'security',
      label: 'Banimentos',
      toneValue: 'danger',
      text: 'Banimentos, tentativas, IPs e acessos em uma área separada para análise com mais calma.',
      action: 'Abrir banimentos',
    },
    {
      id: 'logs',
      label: 'Logs',
      toneValue: 'warn',
      text: 'Mercado, desbloqueios, recargas e ajustes em uma leitura mais limpa para a operação.',
      action: 'Abrir logs',
    },
    {
      id: 'invites',
      label: 'Convites',
      toneValue: 'neutral',
      text: 'Gere links seguros para novos staff sem abrir nenhuma brecha para criar founder.',
      action: 'Abrir convites',
    },
    {
      id: 'tickets',
      label: 'Tickets',
      toneValue: 'neutral',
      text: data?.features?.tickets?.message ?? 'Espaço preparado, aguardando backend dedicado.',
      action: null,
    },
  ].filter((module) => module.id === 'tickets' || allowedViews.has(module.id));

  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Panorama"
        title="Centro de operações"
        description="Abertura rápida do turno com o que está mais quente agora e atalhos para a fila certa."
        action={
          <div className="button-row">
            <button type="button" className="primary-button" onClick={() => onNavigate('orders')}>
              Revisar pedidos
            </button>
            <button type="button" className="secondary-button" onClick={() => onNavigate('users')}>
              Abrir moderação
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Aguardando contato" value={data?.stats?.orders?.paidWaitingContact ?? '--'} toneValue="warn" hint="Pedidos pagos que ainda precisam de retorno." />
          <Metric label="Em entrega" value={data?.stats?.orders?.inDelivery ?? '--'} toneValue="good" hint="Fluxo em andamento para acompanhamento rápido." />
          <Metric label="Entregues" value={data?.stats?.orders?.delivered ?? '--'} toneValue="neutral" hint="Histórico recente já concluído." />
          <Metric label="Usuários banidos" value={data?.stats?.users?.banned ?? '--'} toneValue="danger" hint="Casos ativos que podem merecer revisão." />
        </div>
      </SectionHero>

      <div className="view-grid">
        <Panel
          title="Fila prioritária"
          subtitle="Resumo de foco para o moderador entrar no app e saber por onde começar."
          action={
            <button type="button" className="ghost-button" onClick={onRefresh}>
              Atualizar
            </button>
          }
        >
          <div className="priority-list">
            {tasks.map((task) => (
              <button key={task.id} type="button" className={`priority-item ${task.toneValue}`} onClick={() => onNavigate(task.target)}>
                <div>
                  <strong>{task.title}</strong>
                  <p>{task.text}</p>
                </div>
                <span>Abrir</span>
              </button>
            ))}
          </div>
        </Panel>

        <Panel title="Pedidos recentes" subtitle="Leitura curta do que entrou ou mudou recentemente.">
          <Table
            headers={['Código', 'Produto', 'Status', 'SLA', 'Valor']}
            rows={recentOrders.map((order) => [
              order.publicId,
              order.productName,
              <Badge key={order.id} toneValue={tone(order.status)}>
                {getStatusLabel(statusLabels, order.status)}
              </Badge>,
              <Badge key={`dashboard-sla-${order.id}`} toneValue={getOrderSla(order).toneValue}>
                {getOrderSla(order).label}
              </Badge>,
              fmtMoney(order.totalAmount),
            ])}
            empty="Nenhum pedido recente."
          />
        </Panel>

        <Panel title="Atividade no Discord" subtitle="Últimos sinais da integração e dos eventos mais visíveis.">
          <Timeline
            items={discordActivity.map((entry) => ({
              id: entry.id,
              title: entry.title,
              text: entry.description,
              badge: entry.status,
              badgeTone: tone(entry.status),
              meta: fmtDate(entry.createdAt),
            }))}
            empty="Nenhuma atividade recente."
          />
        </Panel>

        <Panel
          title="Central de atividade"
          subtitle="Mudanças importantes da operação registradas pelo desktop para manter a equipe alinhada."
          action={
            <button type="button" className="ghost-button" onClick={onClearActivity} disabled={!activityFeed.length}>
              Limpar central
            </button>
          }
        >
          <Timeline items={activityFeed} empty="Nenhuma atividade interna registrada ainda." />
        </Panel>

        <Panel title="Saúde do sistema" subtitle="Leitura rápida para saber se o app está pronto para o turno atual.">
          <div className="metric-grid">
            {healthItems.map((item) => (
              <Metric key={item.label} label={item.label} value={item.value} toneValue={item.toneValue} hint={item.hint} />
            ))}
          </div>
        </Panel>

        {onboardingVisible ? (
          <Panel
            title="Guia rápido do moderador"
            subtitle="Atalhos e lembretes para trabalhar mais rápido sem se perder no fluxo."
            action={
              <button type="button" className="ghost-button" onClick={onDismissOnboarding}>
                Entendi
              </button>
            }
          >
            <div className="tips-grid">
              {shortcutHints.map((item) => (
                <article key={item.id} className="tip-card">
                  <strong>{item.keys}</strong>
                  <p>{item.action}</p>
                </article>
              ))}
            </div>
          </Panel>
        ) : null}

        <Panel title="Módulos do desktop" subtitle="Áreas prontas para acelerar o fluxo de suporte e moderação.">
          <div className="module-grid">
            {modules.map((module) => (
              <article key={module.id} className={`module-card ${module.toneValue}`}>
                <span>{module.label}</span>
                <p>{module.text}</p>
                {module.action ? (
                  <button type="button" className="ghost-button" onClick={() => onNavigate(module.id)}>
                    {module.action}
                  </button>
                ) : null}
              </article>
            ))}
          </div>
        </Panel>
      </div>
    </div>
  );
}

export function OrdersView({
  data,
  detail,
  orderId,
  orderStatus,
  orderNote,
  setOrderId,
  setOrderStatus,
  setOrderNote,
  onRefresh,
  onStatusSubmit,
  onNoteSubmit,
  filters,
  setFilters,
  filteredOrders,
  stats,
  busy,
  selectedVisible,
  presets,
  onSavePreset,
  onApplyPreset,
  onDeletePreset,
  quickStatusOptions,
  noteTemplates,
  onQuickStatus,
  onQuickNote,
  permissionFlags,
  getOrderOwner,
  currentOwner,
  currentSla,
  onAssignOwner,
  onReleaseOwner,
  onCopySummary,
  timeline,
  workspace,
  orderAuditReason,
  setOrderAuditReason,
  hideSensitiveInfo,
}) {
  const currentOrder = detail?.order;
  const statusEntries = Object.entries(detail?.statusLabels ?? {});
  const visibleContact = currentOrder?.contactValue
    ? hideSensitiveInfo
      ? currentOrder.contactValue.includes('@')
        ? maskSensitiveValue(currentOrder.contactValue, 'email')
        : maskSensitiveValue(currentOrder.contactValue)
      : currentOrder.contactValue
    : '-';
  const workspaceOwnerLabel = currentOwner?.username ? maskIdentity(currentOwner.username, hideSensitiveInfo) : 'Sem responsável';
  const otherViewerNames = safeList(workspace?.otherViewers).map((viewer) => maskIdentity(viewer.username, hideSensitiveInfo)).join(', ');

  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Operação"
        title="Fila de pedidos"
        description="Busca local, leitura rápida e painel lateral para tratar o pedido selecionado sem sair do contexto."
        action={
          <div className="button-row">
            <button type="button" className="ghost-button" onClick={() => setFilters({ ...ORDER_FILTERS })}>
              Limpar filtros
            </button>
            <button type="button" className="primary-button" onClick={onRefresh}>
              Recarregar pedidos
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Pedidos visíveis" value={stats.total} toneValue="neutral" hint="Resultado após aplicar seus filtros locais." />
          <Metric label="Pendentes" value={stats.pending} toneValue="warn" hint="Pedidos que ainda exigem retorno inicial." />
          <Metric label="Em entrega" value={stats.inDelivery} toneValue="good" hint="Pedidos acompanhados pela equipe agora." />
          <Metric label="SLA crítico" value={stats.slaCritical} toneValue="danger" hint="Pedidos que já passaram do limite de atenção." />
          <Metric label="Receita visível" value={fmtMoney(stats.revenue)} toneValue="neutral" hint="Somatório do recorte atual." />
        </div>
      </SectionHero>

      <div className="split-layout">
        <Panel
          title="Lista filtrada"
          subtitle={`${filteredOrders.length} de ${safeList(data.orders).length} pedidos carregados`}
          action={
            <button type="button" className="ghost-button" onClick={onRefresh}>
              Atualizar
            </button>
          }
        >
          <div className="filter-grid">
            <Field
              label="Buscar pedido"
              value={filters.q}
              onChange={(value) => setFilters((current) => ({ ...current, q: value }))}
              placeholder="Código, produto ou contato"
            />

            <label>
              <span>Status</span>
              <select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}>
                <option value="all">Todos</option>
                {Object.entries(data.statusLabels ?? {}).map(([status, label]) => (
                  <option key={status} value={status}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <FilterPresets presets={presets} onSave={onSavePreset} onApply={onApplyPreset} onDelete={onDeletePreset} />

          {!selectedVisible && orderId ? <div className="inline-hint">O pedido selecionado não aparece nos filtros atuais, mas continua aberto no painel lateral.</div> : null}

          <Table
            headers={['Código', 'Produto', 'Status', 'SLA', 'Responsável', 'Qtd', 'Valor']}
            rows={filteredOrders.map((order) => [
              <button key={order.id} type="button" className={`table-link ${orderId === order.id ? 'active' : ''}`} onClick={() => setOrderId(order.id)}>
                {order.publicId}
              </button>,
              order.productName,
              <Badge key={`status-${order.id}`} toneValue={tone(order.status)}>
                {getStatusLabel(data.statusLabels, order.status)}
              </Badge>,
              <Badge key={`sla-${order.id}`} toneValue={getOrderSla(order).toneValue}>
                {getOrderSla(order).label}
              </Badge>,
              getOrderOwner(order.id)?.username ? maskIdentity(getOrderOwner(order.id).username, hideSensitiveInfo) : 'Livre',
              order.quantity,
              fmtMoney(order.totalAmount),
            ])}
            empty="Nenhum pedido corresponde aos filtros atuais."
          />
        </Panel>

        <Panel
          title={currentOrder ? `Pedido ${currentOrder.publicId}` : 'Detalhes do pedido'}
          subtitle={currentOrder ? currentOrder.productName : 'Selecione um pedido na lista ao lado'}
          action={
            currentOrder ? (
              <div className="button-row">
                <Badge toneValue={tone(currentOrder.status)}>{getStatusLabel(detail?.statusLabels, currentOrder.status)}</Badge>
                <button type="button" className="ghost-button" onClick={onCopySummary}>
                  Copiar resumo
                </button>
              </div>
            ) : null
          }
        >
          {currentOrder ? (
            <>
              <div className="detail-hero">
                <div>
                  <span className="eyebrow">Resumo</span>
                  <h3>{currentOrder.productName}</h3>
                  <p>Um espaço de contexto rápido para decidir a próxima ação da equipe.</p>
                </div>
                <div className="detail-badges">
                  <Badge toneValue="neutral">{fmtMoney(currentOrder.totalAmount)}</Badge>
                  <Badge toneValue="neutral">Qtd {currentOrder.quantity ?? '-'}</Badge>
                  <Badge toneValue={currentSla.toneValue}>{currentSla.label}</Badge>
                </div>
              </div>

              <div className="detail-grid">
                <DataRow label="Código" value={currentOrder.publicId} />
                <DataRow label="Servidor" value={currentOrder.inGameServer || '-'} />
                <DataRow label="Nick" value={currentOrder.inGameNick || '-'} />
                <DataRow label="Contato" value={visibleContact} />
                <DataRow label="SLA atual" value={currentSla.description} />
                <DataRow label={workspace?.supported ? 'Responsável da equipe' : 'Responsável local'} value={workspaceOwnerLabel} />
              </div>

              {workspace?.conflict ? (
                <div className="inline-hint danger">
                  Outro moderador está com este pedido aberto agora: {otherViewerNames || 'equipe ativa neste pedido'}.{' '}
                  {permissionFlags.resolveOrderConflicts
                    ? 'Confirme o contexto antes de seguir com a tratativa.'
                    : 'Sinalize o caso para alguém com permissão de coordenação antes de alterar o fluxo.'}
                </div>
              ) : null}

              <div className="owner-card">
                <div>
                  <span className="eyebrow">{workspace?.supported ? 'Acompanhamento compartilhado' : 'Acompanhamento'}</span>
                  <strong>{currentOwner?.username ? maskIdentity(currentOwner.username, hideSensitiveInfo) : 'Pedido livre para a equipe'}</strong>
                  <p>
                    {currentOwner?.assignedAt
                      ? `Assumido em ${fmtDate(currentOwner.assignedAt)}.`
                      : workspace?.supported
                        ? workspace.message || 'A API está sincronizando o responsável deste pedido para toda a equipe.'
                        : 'Use esta marcação local para evitar tratativa duplicada no mesmo desktop.'}
                  </p>
                </div>
                {permissionFlags.manageOrderOwnership ? (
                  <div className="button-row">
                    <button type="button" className="primary-button" onClick={onAssignOwner}>
                      Assumir pedido
                    </button>
                    <button type="button" className="secondary-button" onClick={onReleaseOwner} disabled={!currentOwner}>
                      Liberar pedido
                    </button>
                  </div>
                ) : (
                  <div className="inline-hint">Seu cargo pode acompanhar o pedido, mas não alterar o responsável desta fila.</div>
                )}
              </div>

              {permissionFlags.updateOrderStatus || permissionFlags.addOrderNote ? (
                <div className="quick-action-block">
                  {permissionFlags.updateOrderStatus ? (
                    <p className="surface-subtitle">As mudanças de status usam o motivo obrigatório informado abaixo e entram no histórico de auditoria.</p>
                  ) : null}
                  {permissionFlags.updateOrderStatus ? (
                    <>
                      <span className="eyebrow">Ações rápidas</span>
                      <div className="quick-action-row">
                        {quickStatusOptions.map((option) => (
                          <button key={option.value} type="button" className="quick-chip" onClick={() => onQuickStatus(option.value)}>
                            {option.label}
                          </button>
                        ))}
                      </div>
                    </>
                  ) : null}

                  {permissionFlags.addOrderNote ? (
                    <>
                      <span className="eyebrow">Modelos de nota</span>
                      <div className="quick-action-row">
                        {noteTemplates.map((template) => (
                          <button key={template.id} type="button" className="quick-chip" onClick={() => onQuickNote(template)}>
                            {template.label}
                          </button>
                        ))}
                      </div>
                    </>
                  ) : null}
                </div>
              ) : null}

              {permissionFlags.updateOrderStatus ? (
                <form className="stack-form" onSubmit={onStatusSubmit}>
                  <Field
                    label="Motivo obrigatório da mudança"
                    value={orderAuditReason}
                    onChange={setOrderAuditReason}
                    placeholder="Descreva por que este status está sendo alterado"
                  />
                  <label>
                    <span>Status do pedido</span>
                    <select value={orderStatus} onChange={(event) => setOrderStatus(event.target.value)}>
                      {statusEntries.map(([status, label]) => (
                        <option key={status} value={status}>
                          {label}
                        </option>
                      ))}
                    </select>
                  </label>
                  <button type="submit" className="primary-button">
                    {busy === 'order-status' ? 'Atualizando...' : 'Atualizar status'}
                  </button>
                </form>
              ) : (
                <div className="inline-hint">Seu cargo não liberou alteração manual de status neste desktop.</div>
              )}

              {permissionFlags.addOrderNote ? (
                <form className="stack-form" onSubmit={onNoteSubmit}>
                  <label>
                    <span>Nota interna</span>
                    <textarea
                      rows="4"
                      value={orderNote}
                      onChange={(event) => setOrderNote(event.target.value)}
                      placeholder="Escreva um contexto para o próximo moderador ou para registrar uma tratativa."
                    />
                  </label>
                  <button type="submit" className="secondary-button">
                    {busy === 'order-note' ? 'Salvando nota...' : 'Adicionar nota'}
                  </button>
                </form>
              ) : (
                <div className="inline-hint">Seu cargo não liberou registro de notas internas neste painel.</div>
              )}

              <Timeline items={timeline} empty="Sem histórico adicional." />
            </>
          ) : (
            <Empty label="Selecione um pedido para abrir o resumo, atualizar status e registrar notas internas." />
          )}
        </Panel>
      </div>
    </div>
  );
}

export function UsersView({
  users,
  detail,
  userId,
  coinForm,
  setUserId,
  setCoinForm,
  onRefresh,
  onBan,
  onUnban,
  onCoins,
  busy,
  filters,
  setFilters,
  filteredUsers,
  stats,
  selectedVisible,
  presets,
  onSavePreset,
  onApplyPreset,
  onDeletePreset,
  coinTemplates,
  banReasons,
  onQuickCoins,
  onQuickBan,
  userHistory,
  permissionFlags,
  onCopySummary,
  moderationReason,
  setModerationReason,
  hideSensitiveInfo,
}) {
  const roleOptions = buildRoleOptions(users);
  const currentUser = detail?.user;
  const inventory = safeList(detail?.inventory);
  const unlocked = inventory.filter((item) => item.isUnlocked).length;

  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Moderação"
        title="Painel de usuários"
        description="A equipe consegue separar a fila, abrir o perfil certo e executar ações sem perder o histórico."
        action={
          <div className="button-row">
            <button type="button" className="ghost-button" onClick={() => setFilters({ ...USER_FILTERS })}>
              Limpar filtros
            </button>
            <button type="button" className="primary-button" onClick={onRefresh}>
              Recarregar usuários
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Usuários visíveis" value={stats.total} toneValue="neutral" hint="Recorte atual após a busca local." />
          <Metric label="Ativos" value={stats.active} toneValue="good" hint="Usuários liberados no momento." />
          <Metric label="Banidos" value={stats.banned} toneValue="danger" hint="Casos que podem exigir revisita do moderador." />
          <Metric label="Coins no recorte" value={stats.coins} toneValue="warn" hint="Somatório do saldo exibido na lista." />
        </div>
      </SectionHero>

      <div className="split-layout">
        <Panel
          title="Lista de usuários"
          subtitle={`${filteredUsers.length} de ${safeList(users).length} usuários carregados`}
          action={
            <button type="button" className="ghost-button" onClick={onRefresh}>
              Atualizar
            </button>
          }
        >
          <div className="filter-grid filter-grid-three">
            <Field
              label="Buscar usuário"
              value={filters.q}
              onChange={(value) => setFilters((current) => ({ ...current, q: value }))}
              placeholder="Usuário, e-mail ou cargo"
            />

            <label>
              <span>Status</span>
              <select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}>
                <option value="all">Todos</option>
                <option value="active">Ativos</option>
                <option value="banned">Banidos</option>
              </select>
            </label>

            <label>
              <span>Cargo</span>
              <select value={filters.role} onChange={(event) => setFilters((current) => ({ ...current, role: event.target.value }))}>
                <option value="all">Todos</option>
                {roleOptions.map((role) => (
                  <option key={role} value={role}>
                    {role}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <FilterPresets presets={presets} onSave={onSavePreset} onApply={onApplyPreset} onDelete={onDeletePreset} />

          {!selectedVisible && userId ? <div className="inline-hint">O usuário selecionado não aparece no recorte atual, mas o painel lateral continua disponível.</div> : null}

          <Table
            headers={['Usuário', 'Cargo', 'Coins', 'Status']}
            rows={filteredUsers.map((user) => [
              <button key={user.id} type="button" className={`table-link ${userId === user.id ? 'active' : ''}`} onClick={() => setUserId(user.id)}>
                {maskIdentity(user.username, hideSensitiveInfo)}
              </button>,
              user.role,
              user.marketCoins,
              <Badge key={`user-${user.id}`} toneValue={user.isBanned ? 'danger' : 'good'}>
                {user.isBanned ? 'Banido' : 'Ativo'}
              </Badge>,
            ])}
            empty="Nenhum usuário corresponde aos filtros atuais."
          />
        </Panel>

        <Panel
          title={currentUser ? maskIdentity(currentUser.username, hideSensitiveInfo) : 'Detalhes do usuário'}
          subtitle={currentUser ? maskIdentity(currentUser.email, hideSensitiveInfo) : 'Selecione um usuário para abrir a moderação'}
          action={
            currentUser ? (
              <div className="button-row">
                <Badge toneValue={currentUser.isBanned ? 'danger' : 'good'}>{currentUser.isBanned ? 'Banido' : 'Ativo'}</Badge>
                <button type="button" className="ghost-button" onClick={onCopySummary}>
                  Copiar resumo
                </button>
              </div>
            ) : null
          }
        >
          {currentUser ? (
            <>
              <div className="detail-hero">
                <div>
                  <span className="eyebrow">Perfil</span>
                  <h3>{maskIdentity(currentUser.username, hideSensitiveInfo)}</h3>
                  <p>Área focada em moderação, saldo e leitura rápida do inventário.</p>
                </div>
                <div className="detail-badges">
                  <Badge toneValue="neutral">{currentUser.role || 'Sem cargo'}</Badge>
                  <Badge toneValue="warn">{currentUser.marketCoins} coins</Badge>
                </div>
              </div>

              <div className="detail-grid">
                <DataRow label="Email" value={maskIdentity(currentUser.email, hideSensitiveInfo)} />
                <DataRow label="Coins" value={String(currentUser.marketCoins ?? 0)} />
                <DataRow label="Itens" value={String(currentUser.inventorySummary?.entries ?? inventory.length)} />
                <DataRow label="Último login" value={fmtDate(currentUser.lastLoginAt)} />
              </div>

              <div className="metric-grid compact-grid">
                <Metric label="Itens liberados" value={unlocked} toneValue="good" />
                <Metric label="Itens bloqueados" value={inventory.length - unlocked} toneValue="warn" />
                <Metric label="Inventário total" value={inventory.length} toneValue="neutral" />
                <Metric label="Moderação" value={currentUser.isBanned ? 'Restrito' : 'Liberado'} toneValue={currentUser.isBanned ? 'danger' : 'good'} />
              </div>

              {permissionFlags.moderateUsers ? (
                <div className="stack-form">
                  <Field
                    label="Motivo obrigatório da moderação"
                    value={moderationReason}
                    onChange={setModerationReason}
                    placeholder={currentUser.isBanned ? 'Explique a liberação do usuário' : 'Explique o motivo do banimento'}
                  />
                  <div className="button-row">
                    {currentUser.isBanned ? (
                      <button type="button" className="secondary-button" onClick={onUnban}>
                        {busy === 'unban' ? 'Processando...' : 'Desbanir usuário'}
                      </button>
                    ) : (
                      <button type="button" className="danger-button" onClick={() => onBan()}>
                        {busy === 'ban' ? 'Processando...' : 'Banir usuário'}
                      </button>
                    )}
                  </div>
                </div>
              ) : (
                <div className="inline-hint">Seu cargo não liberou ações de moderação neste ambiente.</div>
              )}

              {permissionFlags.moderateUsers || permissionFlags.adjustCoins ? (
                <div className="quick-action-block">
                  {permissionFlags.moderateUsers ? (
                    <>
                      <span className="eyebrow">Atalhos da moderação</span>
                      <div className="quick-action-row">
                        {banReasons.map((reason) => (
                          <button
                            key={reason.id}
                            type="button"
                            className="quick-chip"
                            onClick={() => onQuickBan(reason)}
                            disabled={currentUser.isBanned}
                          >
                            {reason.label}
                          </button>
                        ))}
                      </div>
                    </>
                  ) : null}

                  {permissionFlags.adjustCoins ? (
                    <>
                      <span className="eyebrow">Ajustes rápidos de coins</span>
                      <div className="quick-action-row">
                        {coinTemplates.map((template) => (
                          <button key={template.id} type="button" className="quick-chip" onClick={() => onQuickCoins(template)}>
                            {template.label}
                          </button>
                        ))}
                      </div>
                    </>
                  ) : null}
                </div>
              ) : null}

              {permissionFlags.adjustCoins ? (
                <form className="stack-form" onSubmit={onCoins}>
                  <div className="inline-fields">
                    <Field
                      label="Ajuste de coins"
                      type="number"
                      value={coinForm.amount}
                      onChange={(value) => setCoinForm((current) => ({ ...current, amount: value }))}
                      placeholder="250 ou -100"
                    />
                    <Field
                      label="Observação obrigatória"
                      value={coinForm.note}
                      onChange={(value) => setCoinForm((current) => ({ ...current, note: value }))}
                      placeholder="Motivo do ajuste"
                    />
                  </div>
                  <button type="submit" className="primary-button">
                    {busy === 'coins' ? 'Aplicando...' : 'Aplicar ajuste'}
                  </button>
                </form>
              ) : (
                <div className="inline-hint">Seu cargo não liberou ajuste de saldo neste desktop.</div>
              )}

              <Timeline items={userHistory} empty="Sem histórico unificado disponível para este usuário." />

              <Table
                headers={['Item', 'Tipo', 'Qtd', 'Estado']}
                rows={inventory.map((item) => [
                  item.itemName,
                  item.itemTypeLabel,
                  item.quantity,
                  <Badge key={`inv-${item.id}`} toneValue={item.isUnlocked ? 'good' : 'warn'}>
                    {item.isUnlocked ? 'Liberado' : 'Bloqueado'}
                  </Badge>,
                ])}
                empty="Inventário vazio."
              />
            </>
          ) : (
            <Empty label="Selecione um usuário para abrir perfil, moderação e ajuste de saldo." />
          )}
        </Panel>
      </div>
    </div>
  );
}

export function LogsView({ data, filters, setFilters, onRefresh, onReset, highlights, feed, presets, onSavePreset, onApplyPreset, onDeletePreset }) {
  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Auditoria"
        title="Radar de logs"
        description="Cruze a busca da API com uma leitura visual de risco para decidir se vale investigar bans, acessos ou mercado."
        action={
          <div className="button-row">
            <button type="button" className="ghost-button" onClick={onReset}>
              Limpar filtros
            </button>
            <button type="button" className="primary-button" onClick={onRefresh}>
              Atualizar logs
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Bans ativos" value={data?.summary?.active_bans ?? '--'} toneValue="danger" hint="Casos atualmente ativos no recorte." />
          <Metric label="Tentativas hoje" value={data?.summary?.attempts_today ?? '--'} toneValue="warn" hint="Volume recente de tentativas registradas." />
          <Metric label="Vendas" value={data?.summary?.market_sales ?? '--'} toneValue="good" hint="Eventos de mercado identificados no recorte." />
          <Metric label="IPs únicos" value={data?.summary?.unique_ips ?? '--'} toneValue="neutral" hint="Base rápida para cruzamento manual." />
        </div>
      </SectionHero>

      <div className="view-grid">
        <Panel title="Filtros da API" subtitle="Combine escopo, busca e IP antes de pedir um novo recorte.">
          <div className="filter-grid filter-grid-three">
            <label>
              <span>Escopo</span>
              <select value={filters.scope} onChange={(event) => setFilters((current) => ({ ...current, scope: event.target.value }))}>
                <option value="all">Tudo</option>
                <option value="ban">Banimentos</option>
                <option value="market">Mercado</option>
                <option value="access">IPs e acessos</option>
              </select>
            </label>

            <Field
              label="Busca"
              value={filters.q}
              onChange={(value) => setFilters((current) => ({ ...current, q: value }))}
              placeholder="Usuário, evento ou nota"
            />

            <Field label="IP" value={filters.ip} onChange={(value) => setFilters((current) => ({ ...current, ip: value }))} placeholder="127.0.0.1" />

            <Field
              label="Status do ban"
              value={filters.ban_status}
              onChange={(value) => setFilters((current) => ({ ...current, ban_status: value }))}
              placeholder="active, expired..."
            />

            <Field
              label="Evento de mercado"
              value={filters.market_event}
              onChange={(value) => setFilters((current) => ({ ...current, market_event: value }))}
              placeholder="sale, trade..."
            />
          </div>

          <FilterPresets presets={presets} onSave={onSavePreset} onApply={onApplyPreset} onDelete={onDeletePreset} />

          <div className="button-row">
            <button type="button" className="secondary-button" onClick={onRefresh}>
              Aplicar filtros
            </button>
            <button type="button" className="ghost-button" onClick={onReset}>
              Voltar ao padrão
            </button>
          </div>
        </Panel>

        <Panel title="Sinais de risco" subtitle="Leitura curta para o moderador entender o tamanho do alerta.">
          <div className="priority-list static">
            {highlights.map((item) => (
              <article key={item.id} className={`priority-item ${item.toneValue}`}>
                <div>
                  <strong>{item.title}</strong>
                  <p>{item.text}</p>
                </div>
              </article>
            ))}
          </div>
        </Panel>

        <Panel title="Banimentos recentes" subtitle="Últimos registros de ação disciplinar no recorte atual.">
          <Timeline
            items={safeList(data?.banLogs).slice(0, 8).map((entry) => ({
              id: entry.id,
              title: entry.targetUsername || 'Usuário',
              text: entry.reason || 'Sem motivo informado.',
              badge: entry.status,
              badgeTone: tone(entry.status),
              meta: fmtDate(entry.createdAt),
            }))}
            empty="Nenhum banimento encontrado nos filtros atuais."
          />
        </Panel>

        <Panel title="Linha do tempo cruzada" subtitle="Mercado, acessos e bans na mesma leitura visual.">
          <Timeline items={feed} empty="Nenhum log carregado." />
        </Panel>
      </div>
    </div>
  );
}

export function LogsWorkspaceView({ data, filters, setFilters, onRefresh, onReset, highlights, feed, presets, onSavePreset, onApplyPreset, onDeletePreset }) {
  const marketLogs = safeList(data?.marketLogs);
  const accessLogs = safeList(data?.accessLogs);
  const accessIpSummary = safeList(data?.accessIpSummary);
  const banAttempts = safeList(data?.banAttempts);
  const marketEventLabels = data?.marketEventLabels ?? {};
  const showMarket = Boolean(data?.showMarket);
  const showBan = Boolean(data?.showBan);
  const showAccess = Boolean(data?.showAccess);
  const hasActiveFilters = Boolean(filters.q || filters.market_event);

  const marketStats = marketLogs.reduce(
    (acc, entry) => {
      acc.total += 1;
      if (entry.eventType === 'listing_sold') acc.sales += 1;
      if (entry.eventType === 'topup_created' || entry.eventType === 'topup_paid') acc.topups += 1;
      if (entry.eventType === 'inventory_unlocked') acc.unlocks += 1;
      if (entry.eventType === 'admin_coin_adjust') acc.adjustments += 1;
      return acc;
    },
    {
      total: 0,
      sales: 0,
      topups: 0,
      unlocks: 0,
      adjustments: 0,
    },
  );

  function marketTone(entry) {
    if (entry.eventType === 'listing_sold' || entry.eventType === 'topup_paid') return 'good';
    if (entry.eventType === 'admin_coin_adjust' || entry.eventType === 'inventory_unlocked') return 'warn';
    if (entry.eventType === 'listing_cancelled') return 'danger';
    return 'neutral';
  }

  function marketLabel(entry) {
    return marketEventLabels?.[entry.eventType] || entry.eventType || 'Evento de mercado';
  }

  function marketSummary(entry) {
    const note = normalizeLegacyText(entry.note);
    const buyer = entry.buyerUsername || entry.actorUsername || '';
    const seller = entry.sellerUsername || '';
    const destination = entry.targetUsername || buyer || '';

    if (entry.eventType === 'listing_sold') {
      return [buyer && `Comprador: ${buyer}`, seller && `Vendedor: ${seller}`, destination && `Destino: ${destination}`].filter(Boolean).join(' | ');
    }

    if (entry.eventType === 'listing_created') {
      return seller ? `Oferta publicada por ${seller}.` : 'Oferta publicada no mercado.';
    }

    if (entry.eventType === 'listing_cancelled') {
      return seller ? `Oferta cancelada por ${seller}.` : 'Oferta cancelada no mercado.';
    }

    if (entry.eventType === 'inventory_unlocked') {
      const unlockText = formatUnlockRequirement(entry.unlockCost, entry.unlockUnit);
      return destination ? `Item liberado para ${destination}${unlockText !== 'Não exige' ? ` com ${unlockText}` : ''}.` : 'Item desbloqueado.';
    }

    if (entry.eventType === 'admin_coin_adjust') {
      return destination ? `Saldo ajustado para ${destination}.` : 'Ajuste manual de coins.';
    }

    if (entry.eventType === 'topup_created' || entry.eventType === 'topup_paid') {
      return destination ? `Recarga vinculada a ${destination}.` : 'Recarga registrada.';
    }

    return note || 'Sem resumo adicional.';
  }

  function marketReferences(entry) {
    return [
      entry.listingId ? `Anúncio #${entry.listingId}` : null,
      entry.inventoryId ? `Inventário #${entry.inventoryId}` : null,
      entry.orderId ? `Pedido #${entry.orderId}` : null,
      entry.topupId ? `Recarga #${entry.topupId}` : null,
    ].filter(Boolean);
  }

  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Auditoria"
        title="Histórico operacional"
        description="Uma leitura mais direta dos logs para entender quem fez o quê, quando aconteceu e quem foi envolvido."
        action={
          <div className="button-row">
            <button type="button" className="ghost-button" onClick={onReset}>
              Limpar filtros
            </button>
            <button type="button" className="primary-button" onClick={onRefresh}>
              Atualizar logs
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Movimentos" value={fmtCount(marketStats.total)} toneValue="neutral" hint="Eventos de mercado carregados neste recorte." />
          <Metric label="Vendas" value={fmtCount(marketStats.sales)} toneValue="good" hint="Compras concluídas no mercado." />
          <Metric label="Recargas" value={fmtCount(marketStats.topups)} toneValue="neutral" hint="Criações e aprovações de recarga." />
          <Metric label="Desbloqueios e ajustes" value={fmtCount(marketStats.unlocks + marketStats.adjustments)} toneValue="warn" hint="Ações que merecem leitura de contexto." />
        </div>
      </SectionHero>

      <Panel title="Filtros do mercado" subtitle="Busque por nick, item ou evento para enxergar só o histórico operacional que importa agora.">
        <div className="filter-grid filter-grid-three">
          <Field
            label="Busca"
            value={filters.q}
            onChange={(value) => setFilters((current) => ({ ...current, q: value }))}
            placeholder="Nick, item, nota, admin, comprador ou vendedor"
          />

          <label>
            <span>Evento do mercado</span>
            <select value={filters.market_event} onChange={(event) => setFilters((current) => ({ ...current, market_event: event.target.value }))}>
              <option value="">Todos</option>
              {Object.entries(marketEventLabels).map(([eventKey, eventLabel]) => (
                <option key={eventKey} value={eventKey}>
                  {eventLabel}
                </option>
              ))}
            </select>
          </label>
        </div>

        <p className="logs-filter-tip">A busca aceita nick do comprador, vendedor, destino do item, nome do item, admin e nota interna.</p>

        <FilterPresets presets={presets} onSave={onSavePreset} onApply={onApplyPreset} onDelete={onDeletePreset} />

        <div className="button-row invite-filter-actions">
          <button type="button" className="secondary-button" onClick={onRefresh}>
            Aplicar filtros
          </button>
          <button type="button" className="ghost-button" onClick={onReset}>
            Voltar ao padrão
          </button>
        </div>

        {hasActiveFilters ? (
          <div className="logs-filter-summary">
            <Badge toneValue="warn">Filtros ativos</Badge>
            <span>O recorte atual foi reduzido para facilitar a leitura da operação.</span>
          </div>
        ) : null}
      </Panel>

      <div className="split-layout logs-overview-layout">
        <Panel title="Histórico simplificado" subtitle="Linha do tempo curta para o moderador bater o olho e entender o movimento recente.">
          <Timeline items={feed} empty="Nenhum log carregado." />
        </Panel>

        <Panel title="Leitura rápida do recorte" subtitle="Resumo curto dos sinais mais importantes antes de abrir o detalhe.">
          <div className="metric-grid compact-grid">
            <Metric label="Movimentos" value={fmtCount(marketStats.total)} toneValue="neutral" hint="Total de eventos do mercado carregados." />
            <Metric label="Vendas" value={fmtCount(marketStats.sales)} toneValue="good" hint="Compras concluídas no mercado." />
            <Metric label="Recargas" value={fmtCount(marketStats.topups)} toneValue="neutral" hint="Criações e aprovações de recarga." />
            <Metric label="Desbloqueios" value={fmtCount(marketStats.unlocks)} toneValue="warn" hint="Itens liberados com custo." />
          </div>

          <div className="priority-list static">
            {highlights.map((item) => (
              <article key={item.id} className={`priority-item ${item.toneValue}`}>
                <div>
                  <strong>{item.title}</strong>
                  <p>{item.text}</p>
                </div>
              </article>
            ))}
          </div>
        </Panel>
      </div>

      {showMarket ? (
        <Panel title="Mercado organizado" subtitle="Comprador, vendedor, destino do item, horário e contexto do evento em uma leitura simples.">
          {marketLogs.length ? (
            <div className="market-log-list">
              {marketLogs.map((entry) => (
                <article key={entry.id} className="market-log-card">
                  <header className="market-log-head">
                    <div className="market-log-copy">
                      <strong>{marketLabel(entry)}</strong>
                      <p>{entry.itemName || 'Sem item vinculado'}</p>
                    </div>

                    <div className="market-log-meta">
                      <span className="market-log-date">{fmtDate(entry.createdAt)}</span>
                      <div className="market-log-badges">
                        <Badge toneValue={marketTone(entry)}>{entry.itemType || 'mercado'}</Badge>
                        <Badge toneValue={entry.itemLockState === 'locked' ? 'warn' : 'good'}>
                          {entry.itemLockState === 'locked' ? 'Bloqueado' : 'Aberto'}
                        </Badge>
                      </div>
                    </div>
                  </header>

                  <p className="market-log-summary">{marketSummary(entry)}</p>

                  <div className="market-log-grid">
                    <DataRow label="Data e hora" value={fmtDate(entry.createdAt)} />
                    <DataRow label="Vendedor" value={entry.sellerUsername || '-'} />
                    <DataRow label="Comprador" value={entry.buyerUsername || entry.actorUsername || '-'} />
                    <DataRow label="Destino do item" value={entry.targetUsername || entry.buyerUsername || entry.actorUsername || '-'} />
                    <DataRow label="Quantidade" value={`x${fmtCount(entry.quantity || 0)}`} />
                    <DataRow label="Preço" value={entry.priceCoins !== null ? `${fmtCount(entry.priceCoins)} coins` : '-'} />
                    <DataRow label="Coins" value={entry.coinsAmount !== null ? `${fmtCount(entry.coinsAmount)} coins` : '-'} />
                    <DataRow label="Desbloqueio" value={formatUnlockRequirement(entry.unlockCost, entry.unlockUnit)} />
                    <DataRow label="Valor em reais" value={entry.amountBrl !== null ? fmtMoney(entry.amountBrl) : '-'} />
                    <DataRow label="Admin" value={entry.adminUsername || '-'} />
                  </div>

                  {marketReferences(entry).length ? (
                    <div className="market-log-secondary">
                      {marketReferences(entry).map((reference) => (
                        <span key={`${entry.id}-${reference}`} className="market-log-ref">
                          {reference}
                        </span>
                      ))}
                    </div>
                  ) : null}

                  {entry.note ? <p className="market-log-note">{normalizeLegacyText(entry.note)}</p> : null}
                </article>
              ))}
            </div>
          ) : (
            <Empty label="Nenhum evento do mercado bateu com o filtro atual." />
          )}
        </Panel>
      ) : null}

      <div className="split-layout logs-secondary-layout">
        {showBan ? (
          <Panel title="Banimentos recentes" subtitle="Últimos registros de ação disciplinar no recorte atual.">
            <Timeline
              items={safeList(data?.banLogs).slice(0, 8).map((entry) => ({
                id: entry.id,
                title: entry.targetUsername || 'Usuário',
                text: entry.reason || 'Sem motivo informado.',
                badge: entry.status,
                badgeTone: tone(entry.status),
                meta: fmtDate(entry.createdAt),
              }))}
              empty="Nenhum banimento encontrado nos filtros atuais."
            />
          </Panel>
        ) : null}

        {showBan ? (
          <Panel title="Tentativas bloqueadas" subtitle="Histórico recente para facilitar investigação de abuso ou conta comprometida.">
            <Timeline
              items={banAttempts.slice(0, 8).map((entry) => ({
                id: entry.id,
                title: entry.matchedUsername || entry.loginInput || entry.emailInput || 'Tentativa bloqueada',
                text:
                  [
                    entry.action || 'tentativa',
                    entry.ipAddress ? `IP: ${entry.ipAddress}` : '',
                    entry.matchedBanReason ? `Motivo: ${entry.matchedBanReason}` : entry.note || '',
                  ]
                    .filter(Boolean)
                    .join(' | ') || 'Sem detalhes adicionais.',
                badge: 'tentativa',
                badgeTone: 'warn',
                meta: fmtDate(entry.createdAt),
              }))}
              empty="Nenhuma tentativa bloqueada encontrada."
            />
          </Panel>
        ) : null}

        {showAccess ? (
          <Panel title="Acessos recentes" subtitle="Quem entrou, de onde veio e qual rota apareceu no histórico técnico.">
            <Timeline
              items={accessLogs.slice(0, 8).map((entry) => ({
                id: entry.id,
                title: `${entry.targetUsername || 'Conta'} • ${entry.action || 'Acesso'}`,
                text: [entry.route, entry.ipAddress].filter(Boolean).join(' | ') || 'Sem rota registrada.',
                badge: 'acesso',
                badgeTone: 'neutral',
                meta: fmtDate(entry.createdAt),
              }))}
              empty="Nenhum acesso recente encontrado."
            />

            {accessIpSummary.length ? (
              <div className="market-log-list compact-market-list">
                {accessIpSummary.slice(0, 4).map((entry, index) => (
                  <article key={`ip-${entry.ipAddress || index}`} className="market-log-card compact-market-card">
                    <div className="market-log-grid">
                      <DataRow label="Conta" value={entry.targetUsername || '-'} />
                      <DataRow label="IP" value={entry.ipAddress || '-'} />
                      <DataRow label="Hits" value={fmtCount(entry.totalHits)} />
                      <DataRow label="Último acesso" value={fmtDate(entry.lastSeenAt)} />
                    </div>
                  </article>
                ))}
              </div>
            ) : null}
          </Panel>
        ) : null}
      </div>
    </div>
  );
}

export function SecurityWorkspaceView({ data, filters, setFilters, onRefresh, onReset, highlights, feed, presets, onSavePreset, onApplyPreset, onDeletePreset }) {
  const banLogs = safeList(data?.banLogs);
  const banAttempts = safeList(data?.banAttempts);
  const accessLogs = safeList(data?.accessLogs);
  const accessIpSummary = safeList(data?.accessIpSummary);
  const summary = data?.summary ?? {};
  const hasActiveFilters = Boolean(filters.q || filters.ip || filters.ban_status);

  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Segurança"
        title="Central de banimentos"
        description="Banimentos, tentativas, IPs e acessos em uma área própria para a equipe analisar com mais calma e agir com responsabilidade."
        action={
          <div className="button-row">
            <button type="button" className="ghost-button" onClick={onReset}>
              Limpar filtros
            </button>
            <button type="button" className="primary-button" onClick={onRefresh}>
              Atualizar banimentos
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Bans ativos" value={fmtCount(summary.active_bans ?? 0)} toneValue="danger" hint="Contas que seguem bloqueadas neste momento." />
          <Metric label="Tentativas hoje" value={fmtCount(summary.attempts_today ?? 0)} toneValue="warn" hint="Tentativas bloqueadas registradas hoje." />
          <Metric label="IPs únicos" value={fmtCount(summary.unique_ips ?? 0)} toneValue="neutral" hint="IPs vistos no recorte técnico atual." />
          <Metric label="Contas rastreadas" value={fmtCount(summary.tracked_accounts ?? 0)} toneValue="neutral" hint="Contas com histórico técnico disponível." />
        </div>
      </SectionHero>

      <Panel title="Filtros de segurança" subtitle="Busque por usuário, e-mail, IP, motivo ou nota para revisar cada caso sem ruído.">
        <div className="filter-grid filter-grid-three">
          <Field
            label="Busca"
            value={filters.q}
            onChange={(value) => setFilters((current) => ({ ...current, q: value }))}
            placeholder="Usuário, e-mail, motivo, nota ou ação"
          />

          <Field label="IP" value={filters.ip} onChange={(value) => setFilters((current) => ({ ...current, ip: value }))} placeholder="127.0.0.1" />

          <label>
            <span>Status do ban</span>
            <select value={filters.ban_status} onChange={(event) => setFilters((current) => ({ ...current, ban_status: event.target.value }))}>
              <option value="">Todos</option>
              <option value="active">Ativos</option>
              <option value="revoked">Revogados</option>
            </select>
          </label>
        </div>

        <p className="logs-filter-tip">A busca aceita usuário, e-mail, IP, motivo do ban, ação registrada e rota do acesso.</p>

        <FilterPresets presets={presets} onSave={onSavePreset} onApply={onApplyPreset} onDelete={onDeletePreset} />

        <div className="button-row invite-filter-actions">
          <button type="button" className="secondary-button" onClick={onRefresh}>
            Aplicar filtros
          </button>
          <button type="button" className="ghost-button" onClick={onReset}>
            Voltar ao padrão
          </button>
        </div>

        {hasActiveFilters ? (
          <div className="logs-filter-summary">
            <Badge toneValue="warn">Filtros ativos</Badge>
            <span>O recorte atual foi reduzido para facilitar a revisão dos casos de segurança.</span>
          </div>
        ) : null}
      </Panel>

      <div className="split-layout logs-overview-layout">
        <Panel title="Histórico simplificado" subtitle="Linha do tempo curta para entender o movimento recente antes de revisar um caso em detalhe.">
          <Timeline items={feed} empty="Nenhum evento de segurança carregado." />
        </Panel>

        <Panel title="Leitura rápida do risco" subtitle="Resumo curto para ajudar a equipe a decidir o que merece atenção primeiro.">
          <div className="priority-list static">
            {highlights.map((item) => (
              <article key={item.id} className={`priority-item ${item.toneValue}`}>
                <div>
                  <strong>{item.title}</strong>
                  <p>{item.text}</p>
                </div>
              </article>
            ))}
          </div>
        </Panel>
      </div>

      <div className="split-layout logs-secondary-layout">
        <Panel title="Banimentos recentes" subtitle="Últimos registros de ação disciplinar para revisão rápida da equipe.">
          <Timeline
            items={banLogs.slice(0, 10).map((entry) => ({
              id: entry.id,
              title: entry.targetUsername || 'Usuário',
              text: [entry.reason, entry.note, entry.ipAddress ? `IP: ${entry.ipAddress}` : ''].filter(Boolean).join(' | ') || 'Sem motivo informado.',
              badge: entry.status,
              badgeTone: tone(entry.status),
              meta: fmtDate(entry.createdAt),
            }))}
            empty="Nenhum banimento encontrado nos filtros atuais."
          />
        </Panel>

        <Panel title="Tentativas bloqueadas" subtitle="Tentativas recentes para cruzar conta, IP e padrão de abuso sem pressa.">
          <Timeline
            items={banAttempts.slice(0, 10).map((entry) => ({
              id: entry.id,
              title: entry.matchedUsername || entry.loginInput || entry.emailInput || 'Tentativa bloqueada',
              text:
                [entry.action || 'tentativa', entry.ipAddress ? `IP: ${entry.ipAddress}` : '', entry.matchedBanReason ? `Motivo: ${entry.matchedBanReason}` : entry.note || '']
                  .filter(Boolean)
                  .join(' | ') || 'Sem detalhes adicionais.',
              badge: 'tentativa',
              badgeTone: 'warn',
              meta: fmtDate(entry.createdAt),
            }))}
            empty="Nenhuma tentativa bloqueada encontrada."
          />
        </Panel>
      </div>

      <div className="split-layout logs-secondary-layout">
        <Panel title="Acessos recentes" subtitle="Rotas e horários recentes para acompanhar a movimentação técnica das contas.">
          <Timeline
            items={accessLogs.slice(0, 10).map((entry) => ({
              id: entry.id,
              title: `${entry.targetUsername || 'Conta'} | ${entry.action || 'Acesso'}`,
              text: [entry.route, entry.ipAddress].filter(Boolean).join(' | ') || 'Sem rota registrada.',
              badge: 'acesso',
              badgeTone: 'neutral',
              meta: fmtDate(entry.createdAt),
            }))}
            empty="Nenhum acesso recente encontrado."
          />
        </Panel>

        <Panel title="Resumo de IPs" subtitle="Cruzamento rápido de contas, IPs e últimos acessos para revisar casos com mais responsabilidade.">
          {accessIpSummary.length ? (
            <div className="market-log-list compact-market-list">
              {accessIpSummary.slice(0, 8).map((entry, index) => (
                <article key={`ip-summary-${entry.ipAddress || index}`} className="market-log-card compact-market-card">
                  <div className="market-log-grid">
                    <DataRow label="Conta" value={entry.targetUsername || '-'} />
                    <DataRow label="IP" value={entry.ipAddress || '-'} />
                    <DataRow label="Hits" value={fmtCount(entry.totalHits)} />
                    <DataRow label="Primeiro acesso" value={fmtDate(entry.firstSeenAt)} />
                    <DataRow label="Último acesso" value={fmtDate(entry.lastSeenAt)} />
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <Empty label="Nenhum IP encontrado com o filtro atual." />
          )}
        </Panel>
      </div>
    </div>
  );
}

export function InvitesView({
  data,
  filters,
  setFilters,
  onRefresh,
  onReset,
  onGenerate,
  onCopyInvite,
  onCopyLink,
  onDeleteInvite,
  busy,
  hideSensitiveInfo,
}) {
  const invites = safeList(data?.invites);
  const summary = data?.summary ?? {};
  const policyMessage = data?.policy?.message || 'Os convites do desktop criam apenas contas staff. Founder nunca é criado por convite.';

  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Founder"
        title="Convites para staff"
        description="Gere links seguros para novos moderadores da equipe sem abrir nenhuma brecha para criar founder."
        action={
          <div className="button-row">
            <button type="button" className="ghost-button" onClick={onReset}>
              Limpar filtros
            </button>
            <button type="button" className="primary-button" onClick={onRefresh}>
              Atualizar convites
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Convites visíveis" value={summary.visible ?? invites.length} toneValue="neutral" hint="Recorte atual vindo da API." />
          <Metric label="Disponíveis" value={summary.available ?? 0} toneValue="good" hint="Links prontos para enviar a novos staff." />
          <Metric label="Usados" value={summary.used ?? 0} toneValue="warn" hint="Convites que já viraram conta staff." />
          <Metric label="Destino" value="Somente staff" toneValue="danger" hint="Nenhum convite do desktop cria founder." />
        </div>
      </SectionHero>

      <div className="view-grid">
        <Panel title="Geração segura" subtitle="Atalho rápido para criar convites e mandar para quem vai entrar como staff.">
          <div className="quick-action-block">
            <span className="eyebrow">Política</span>
            <p className="surface-subtitle">{policyMessage}</p>
            <div className="button-row">
              <button type="button" className="primary-button" onClick={() => onGenerate(1)} disabled={busy === 'invite-create'}>
                {busy === 'invite-create' ? 'Gerando...' : 'Gerar 1 convite'}
              </button>
              <button type="button" className="secondary-button" onClick={() => onGenerate(3)} disabled={busy === 'invite-create'}>
                Gerar 3 convites
              </button>
              <button type="button" className="ghost-button" onClick={() => onGenerate(5)} disabled={busy === 'invite-create'}>
                Gerar 5 convites
              </button>
            </div>
          </div>

          <div className="priority-list static">
            <article className="priority-item neutral">
              <div>
                <strong>Convite exclusivo para staff</strong>
                <p>O cadastro aberto por esse link sempre termina com conta staff. Founder continua travado nas contas fixas do banco.</p>
              </div>
            </article>
            <article className="priority-item warn">
              <div>
                <strong>Compartilhamento com cuidado</strong>
                <p>Se a proteção visual estiver ativa, o código fica mascarado na tela, mas os botões continuam copiando o convite real.</p>
              </div>
            </article>
          </div>
        </Panel>

        <Panel title="Lista de convites" subtitle={`${invites.length} convite(s) carregado(s) no recorte atual.`}>
          <div className="filter-grid">
            <Field
              label="Buscar convite"
              value={filters.search}
              onChange={(value) => setFilters((current) => ({ ...current, search: value }))}
              placeholder="Código, founder ou staff"
            />

            <label>
              <span>Status</span>
              <select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}>
                <option value="all">Todos</option>
                <option value="available">Disponíveis</option>
                <option value="used">Usados</option>
              </select>
            </label>
          </div>

          <div className="button-row">
            <button type="button" className="secondary-button" onClick={onRefresh}>
              Aplicar filtros
            </button>
            <button type="button" className="ghost-button" onClick={onReset}>
              Voltar ao padrão
            </button>
          </div>

          <Table
            headers={['Código', 'Destino', 'Status', 'Criado por', 'Criado em', 'Usado por', 'Ações']}
            rows={invites.map((invite) => [
              <code key={`invite-${invite.id}`}>{hideSensitiveInfo ? maskSensitiveValue(invite.inviteKey) : invite.inviteKey}</code>,
              invite.targetRole || 'staff',
              <Badge key={`invite-status-${invite.id}`} toneValue={invite.status === 'used' ? 'warn' : 'good'}>
                {invite.status === 'used' ? 'Usado' : 'Disponível'}
              </Badge>,
              maskIdentity(invite.createdByUsername || '-', hideSensitiveInfo),
              fmtDate(invite.createdAt),
              invite.usedByUsername ? maskIdentity(invite.usedByUsername, hideSensitiveInfo) : '-',
              <div key={`invite-actions-${invite.id}`} className="button-row">
                {invite.status !== 'used' ? (
                  <>
                    <button type="button" className="ghost-button" onClick={() => onCopyInvite(invite)}>
                      Copiar convite
                    </button>
                    <button type="button" className="secondary-button" onClick={() => onCopyLink(invite)}>
                      Copiar link
                    </button>
                  </>
                ) : null}
                <button type="button" className="ghost-button" onClick={() => onDeleteInvite(invite)} disabled={busy === 'invite-delete'}>
                  Remover
                </button>
              </div>,
            ])}
            empty="Nenhum convite encontrado nos filtros atuais."
          />

          {!invites.length ? <div className="inline-hint">Gere um novo convite para staff e envie o link pelo canal interno da equipe.</div> : null}
        </Panel>
      </div>
    </div>
  );
}

export function InvitesWorkspaceView({
  data,
  filters,
  setFilters,
  onRefresh,
  onReset,
  onGenerate,
  onCopyInvite,
  onCopyLink,
  onDeleteInvite,
  busy,
  hideSensitiveInfo,
}) {
  const invites = safeList(data?.invites);
  const summary = data?.summary ?? {};
  const policyMessage = data?.policy?.message || 'Os convites do desktop criam apenas contas staff. Founder nunca é criado por convite.';

  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Founder"
        title="Convites para staff"
        description="Gere links seguros para novos moderadores da equipe sem abrir nenhuma brecha para criar founder."
        action={
          <div className="button-row">
            <button type="button" className="ghost-button" onClick={onReset}>
              Limpar filtros
            </button>
            <button type="button" className="primary-button" onClick={onRefresh}>
              Atualizar convites
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Convites visíveis" value={summary.visible ?? invites.length} toneValue="neutral" hint="Recorte atual vindo da API." />
          <Metric label="Disponíveis" value={summary.available ?? 0} toneValue="good" hint="Links prontos para enviar a novos staff." />
          <Metric label="Usados" value={summary.used ?? 0} toneValue="warn" hint="Convites que já viraram conta staff." />
          <Metric label="Destino" value="Somente staff" toneValue="danger" hint="Nenhum convite do desktop cria founder." />
        </div>
      </SectionHero>

      <div className="split-layout invites-layout">
        <Panel className="invite-side-panel" title="Geração segura" subtitle="Atalho rápido para criar convites e mandar para quem vai entrar como staff.">
          <div className="quick-action-block">
            <span className="eyebrow">Política</span>
            <p className="surface-subtitle">{policyMessage}</p>
            <div className="button-row">
              <button type="button" className="primary-button" onClick={() => onGenerate(1)} disabled={busy === 'invite-create'}>
                {busy === 'invite-create' ? 'Gerando...' : 'Gerar 1 convite'}
              </button>
              <button type="button" className="secondary-button" onClick={() => onGenerate(3)} disabled={busy === 'invite-create'}>
                Gerar 3 convites
              </button>
              <button type="button" className="ghost-button" onClick={() => onGenerate(5)} disabled={busy === 'invite-create'}>
                Gerar 5 convites
              </button>
            </div>
          </div>

          <div className="priority-list static">
            <article className="priority-item neutral">
              <div>
                <strong>Convite exclusivo para staff</strong>
                <p>O cadastro aberto por esse link sempre termina com conta staff. Founder continua travado nas contas fixas do banco.</p>
              </div>
            </article>
            <article className="priority-item warn">
              <div>
                <strong>Compartilhamento com cuidado</strong>
                <p>Se a proteção visual estiver ativa, o código fica mascarado na tela, mas os botões continuam copiando o convite real.</p>
              </div>
            </article>
          </div>
        </Panel>

        <Panel className="invite-main-panel" title="Lista de convites" subtitle={`${invites.length} convite(s) carregado(s) no recorte atual.`}>
          <div className="filter-grid invites-filter-grid">
            <Field
              label="Buscar convite"
              value={filters.search}
              onChange={(value) => setFilters((current) => ({ ...current, search: value }))}
              placeholder="Código, founder ou staff"
            />

            <label>
              <span>Status</span>
              <select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}>
                <option value="all">Todos</option>
                <option value="available">Disponíveis</option>
                <option value="used">Usados</option>
              </select>
            </label>
          </div>

          <div className="button-row invite-filter-actions">
            <button type="button" className="secondary-button" onClick={onRefresh}>
              Aplicar filtros
            </button>
            <button type="button" className="ghost-button" onClick={onReset}>
              Voltar ao padrão
            </button>
          </div>

          {invites.length ? (
            <div className="invite-card-list">
              {invites.map((invite) => (
                <article key={invite.id} className="invite-card">
                  <div className="invite-card-head">
                    <div className="invite-card-code">
                      <span className="eyebrow">Código</span>
                      <code>{hideSensitiveInfo ? maskSensitiveValue(invite.inviteKey) : invite.inviteKey}</code>
                    </div>

                    <div className="invite-card-badges">
                      <Badge toneValue={invite.status === 'used' ? 'warn' : 'good'}>{invite.status === 'used' ? 'Usado' : 'Disponível'}</Badge>
                      <Badge toneValue="neutral">{invite.targetRole || 'staff'}</Badge>
                    </div>
                  </div>

                  <div className="invite-card-grid">
                    <DataRow label="Criado por" value={maskIdentity(invite.createdByUsername || '-', hideSensitiveInfo)} />
                    <DataRow label="Criado em" value={fmtDate(invite.createdAt)} />
                    <DataRow label="Usado por" value={invite.usedByUsername ? maskIdentity(invite.usedByUsername, hideSensitiveInfo) : '-'} />
                    <DataRow label="Usado em" value={fmtDate(invite.usedAt)} />
                  </div>

                  <div className="invite-card-actions">
                    {invite.status !== 'used' ? (
                      <>
                        <button type="button" className="ghost-button" onClick={() => onCopyInvite(invite)}>
                          Copiar convite
                        </button>
                        <button type="button" className="secondary-button" onClick={() => onCopyLink(invite)}>
                          Copiar link
                        </button>
                      </>
                    ) : null}
                    <button type="button" className="ghost-button" onClick={() => onDeleteInvite(invite)} disabled={busy === 'invite-delete'}>
                      Remover
                    </button>
                  </div>
                </article>
              ))}
            </div>
          ) : (
            <>
              <Empty label="Nenhum convite encontrado nos filtros atuais." />
              <div className="inline-hint">Gere um novo convite para staff e envie o link pelo canal interno da equipe.</div>
            </>
          )}
        </Panel>
      </div>
    </div>
  );
}

export function ProductsWorkspaceView({
  data,
  filters,
  setFilters,
  filteredProducts,
  stats,
  productId,
  productDraft,
  categoryDraft,
  busy,
  siteBaseUrl,
  onRefresh,
  onReset,
  onNewProduct,
  onEditProduct,
  onDraftChange,
  onCategoryDraftChange,
  onResetCategoryDraft,
  onSaveCategory,
  onSaveProduct,
  onDeleteProduct,
  onPickImages,
  onRemovePendingImage,
  onToggleRemoveExistingImage,
  presets,
  onSavePreset,
  onApplyPreset,
  onDeletePreset,
}) {
  const categories = safeList(data?.categories);
  const typeOptions = safeList(data?.typeOptions);
  const resolvedTypeOptions = typeOptions.length
    ? typeOptions
    : [
        { value: 'item', label: 'Item' },
        { value: 'conta', label: 'Conta' },
      ];
  const activeExistingImages = safeList(productDraft.existingAccountImages).filter((path) => !productDraft.removeAccountImages.includes(path));
  const totalImages = activeExistingImages.length + productDraft.newImages.length;
  const isSaving = busy === 'products-save';
  const isDeleting = busy === 'products-delete';
  const isLoading = busy === 'products';
  const isSavingCategory = busy === 'categories-save';

  return (
    <div className="view-stack">
      <SectionHero
        eyebrow="Founder"
        title="Produtos"
        description="Gerencie catálogo, estoque, descrição, anúncio e imagens WEBP no mesmo fluxo, sem sair do desktop."
        action={
          <div className="button-row">
            <button type="button" className="ghost-button" onClick={onReset}>
              Limpar filtros
            </button>
            <button type="button" className="secondary-button" onClick={onNewProduct}>
              Novo produto
            </button>
            <button type="button" className="primary-button" onClick={onRefresh}>
              {isLoading ? 'Atualizando...' : 'Atualizar produtos'}
            </button>
          </div>
        }
      >
        <div className="metric-grid">
          <Metric label="Catálogo" value={fmtCount(stats.total)} toneValue="neutral" hint="Produtos visíveis no recorte atual." />
          <Metric label="Ativos" value={fmtCount(stats.active)} toneValue="good" hint="Itens e contas publicados para venda." />
          <Metric label="Contas" value={fmtCount(stats.accounts)} toneValue="warn" hint="Produtos do tipo conta neste recorte." />
          <Metric label="Com imagem" value={fmtCount(stats.withImages)} toneValue="neutral" hint="Contas que já têm galeria WEBP pronta." />
        </div>
      </SectionHero>

      <div className="split-layout products-layout">
        <Panel className="products-list-panel" title="Catálogo filtrado" subtitle={`${filteredProducts.length} produto(s) encontrado(s) com o recorte atual.`}>
          <div className="filter-grid products-filter-grid">
            <Field
              label="Buscar produto"
              value={filters.q}
              onChange={(value) => setFilters((current) => ({ ...current, q: value }))}
              placeholder="Nome, slug, categoria ou descrição"
            />

            <label>
              <span>Status</span>
              <select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))}>
                <option value="all">Todos</option>
                <option value="active">Ativos</option>
                <option value="hidden">Ocultos</option>
              </select>
            </label>

            <label>
              <span>Tipo</span>
              <select value={filters.type} onChange={(event) => setFilters((current) => ({ ...current, type: event.target.value }))}>
                <option value="all">Todos</option>
                {resolvedTypeOptions.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>

            <label>
              <span>Categoria</span>
              <select value={filters.category} onChange={(event) => setFilters((current) => ({ ...current, category: event.target.value }))}>
                <option value="all">Todas</option>
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <FilterPresets presets={presets} onSave={onSavePreset} onApply={onApplyPreset} onDelete={onDeletePreset} />

          {!categories.length ? (
            <div className="inline-hint danger">
              Nenhuma categoria foi carregada da API. Sem categorias, o founder não consegue vincular o produto ao catálogo.
            </div>
          ) : null}

          {filteredProducts.length ? (
            <div className="product-card-list">
              {filteredProducts.map((product) => (
                <button
                  key={product.id}
                  type="button"
                  className={`product-card ${product.id === productId ? 'active' : ''}`.trim()}
                  onClick={() => onEditProduct(product)}
                >
                  <div className="product-card-head">
                    <div>
                      <strong>{product.name || 'Produto sem nome'}</strong>
                      <p>{product.categoryName || 'Sem categoria'} • {formatProductTypeLabel(product.productType)}</p>
                    </div>

                    <div className="product-card-badges">
                      <Badge toneValue={product.isActive ? 'good' : 'warn'}>{product.isActive ? 'Ativo' : 'Oculto'}</Badge>
                      <Badge toneValue="neutral">{formatProductStock(product.stock)} em estoque</Badge>
                    </div>
                  </div>

                  <div className="product-card-grid">
                    <DataRow label="Slug" value={product.slug || '-'} />
                    <DataRow label="Compra mínima" value={`${fmtCount(product.minimumQuantity || 1)} un.`} />
                    <DataRow label="Preço" value={fmtMoney(product.unitPrice)} />
                    <DataRow label="Entrega" value={product.deliveryEta || '-'} />
                    <DataRow label="Imagens" value={`${fmtCount(safeList(product.accountImages).length)} WEBP`} />
                  </div>

                  <p className="product-card-note">{product.productDescription || product.description || 'Sem descrição rápida cadastrada.'}</p>
                </button>
              ))}
            </div>
          ) : (
            <Empty label="Nenhum produto encontrado neste recorte. Ajuste os filtros ou crie um novo item." />
          )}
        </Panel>

        <Panel
          className="products-editor-panel"
          title={productId ? 'Editor de produto' : 'Novo produto'}
          subtitle={productId ? 'Atualize preço, estoque, anúncio e imagens sem sair do desktop.' : 'Preencha os dados do catálogo e publique um novo item ou conta.'}
          action={
            productId ? (
              <button type="button" className="ghost-button" onClick={onDeleteProduct} disabled={isDeleting}>
                {isDeleting ? 'Removendo...' : 'Remover produto'}
              </button>
            ) : null
          }
        >
          {data?.policy?.message ? <div className="inline-hint">{data.policy.message}</div> : null}

          <form
            className="stack-form"
            onSubmit={(event) => {
              event.preventDefault();
              void onSaveProduct();
            }}
          >
            <div className="filter-grid filter-grid-three">
              <Field label="Nome" value={productDraft.name} onChange={(value) => onDraftChange('name', value)} placeholder="Ex.: Conta com set inicial" />
              <Field label="Slug" value={productDraft.slug} onChange={(value) => onDraftChange('slug', value)} placeholder="conta-set-inicial" />

              <label>
                <span>Categoria</span>
                <select value={productDraft.categoryId} onChange={(event) => onDraftChange('categoryId', event.target.value)}>
                  <option value="">Selecione</option>
                  {categories.map((category) => (
                    <option key={category.id} value={category.id}>
                      {category.name}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <div className="category-quick-panel">
              <div className="category-quick-head">
                <div>
                  <strong>Categoria rápida</strong>
                  <p>Crie uma nova categoria sem sair do desktop e já deixe o produto apontando para ela.</p>
                </div>
              </div>

              <div className="filter-grid filter-grid-three">
                <Field label="Nome da categoria" value={categoryDraft.name} onChange={(value) => onCategoryDraftChange('name', value)} placeholder="Ex.: Eventos especiais" />
                <Field label="Slug da categoria" value={categoryDraft.slug} onChange={(value) => onCategoryDraftChange('slug', value)} placeholder="eventos-especiais" />
                <Field label="Ordem" type="number" value={categoryDraft.sortOrder} onChange={(value) => onCategoryDraftChange('sortOrder', value)} placeholder="0" inputProps={{ step: 1 }} />
              </div>

              <label className="checkbox-row">
                <input
                  type="checkbox"
                  checked={Boolean(categoryDraft.isActive)}
                  onChange={(event) => onCategoryDraftChange('isActive', event.target.checked)}
                />
                <span>Deixar categoria ativa para aparecer no catálogo do site</span>
              </label>

              <div className="button-row">
                <button type="button" className="ghost-button" onClick={onResetCategoryDraft}>
                  Limpar categoria
                </button>
                <button type="button" className="secondary-button" onClick={onSaveCategory} disabled={isSavingCategory}>
                  {isSavingCategory ? 'Criando categoria...' : 'Criar categoria'}
                </button>
              </div>
            </div>

            {!categories.length ? (
              <div className="inline-hint danger">
                Cadastre ou publique as categorias no painel admin do site e confirme que a API desktop está atualizada.
              </div>
            ) : null}

            <div className="filter-grid filter-grid-three">
              <Field label="Preço em reais" type="number" value={productDraft.unitPrice} onChange={(value) => onDraftChange('unitPrice', value)} placeholder="0.00" />
              <Field label="Estoque" type="number" value={productDraft.stock} onChange={(value) => onDraftChange('stock', value)} placeholder="Vazio = ilimitado" />
              <Field
                label="Compra mínima"
                type="number"
                value={productDraft.minimumQuantity}
                onChange={(value) => onDraftChange('minimumQuantity', value)}
                placeholder="1"
                description="Quantidade mínima que o usuário precisa comprar deste produto."
                inputProps={{ min: 1, step: 1 }}
              />

              <label>
                <span>Tipo</span>
                <select value={productDraft.productType} onChange={(event) => onDraftChange('productType', event.target.value)}>
                  {resolvedTypeOptions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
            </div>

            <div className="filter-grid filter-grid-three">
              <Field label="Servidor" value={productDraft.serverLabel} onChange={(value) => onDraftChange('serverLabel', value)} placeholder="LDMO Omegamon" />
              <Field label="Prazo de entrega" value={productDraft.deliveryEta} onChange={(value) => onDraftChange('deliveryEta', value)} placeholder="5min-1h" />
              <Field label="Método de entrega" value={productDraft.deliveryMethod} onChange={(value) => onDraftChange('deliveryMethod', value)} placeholder="E-mail, WhatsApp, chat..." />
            </div>

            <label className="product-textarea">
              <span>Descrição curta do anúncio</span>
              <textarea
                rows={4}
                value={productDraft.productDescription}
                onChange={(event) => onDraftChange('productDescription', event.target.value)}
                placeholder="Resumo que aparece no anúncio do produto."
              />
            </label>

            {productDraft.productType === 'conta' ? (
              <>
                <label className="product-textarea">
                  <span>Informações da conta</span>
                  <textarea
                    rows={4}
                    value={productDraft.accountInfo}
                    onChange={(event) => onDraftChange('accountInfo', event.target.value)}
                    placeholder="Login, observações internas, detalhes da conta..."
                  />
                </label>

                <div className="product-images-panel">
                  <div className="product-images-head">
                    <div>
                      <strong>Galeria WEBP</strong>
                      <p>{totalImages}/8 imagem(ns) prontas para esta conta. Use apenas arquivos WEBP.</p>
                    </div>
                    <label className="secondary-button product-upload-button">
                      <input
                        type="file"
                        accept=".webp,image/webp"
                        multiple
                        onChange={(event) => {
                          const nextFiles = event.target.files ? Array.from(event.target.files) : [];
                          event.target.value = '';
                          void onPickImages(nextFiles);
                        }}
                      />
                      Adicionar imagens WEBP
                    </label>
                  </div>

                  {(activeExistingImages.length || productDraft.newImages.length) ? (
                    <div className="product-image-grid">
                      {activeExistingImages.map((imagePath) => {
                        const imageUrl = buildProductAssetUrl(siteBaseUrl, imagePath);
                        return (
                          <article key={imagePath} className="product-image-card">
                            {imageUrl ? <img src={imageUrl} alt="Imagem da conta" className="product-image-preview" /> : null}
                            <div className="product-image-copy">
                              <strong>Imagem salva</strong>
                              <p>{imagePath.split('/').pop() || imagePath}</p>
                            </div>
                            <button type="button" className="ghost-button" onClick={() => onToggleRemoveExistingImage(imagePath)}>
                              Remover desta conta
                            </button>
                          </article>
                        );
                      })}

                      {productDraft.newImages.map((image) => (
                        <article key={image.id} className="product-image-card pending">
                          <img src={image.data} alt={image.name} className="product-image-preview" />
                          <div className="product-image-copy">
                            <strong>Nova imagem</strong>
                            <p>{image.name}</p>
                          </div>
                          <button type="button" className="ghost-button" onClick={() => onRemovePendingImage(image.id)}>
                            Tirar do rascunho
                          </button>
                        </article>
                      ))}
                    </div>
                  ) : (
                    <Empty label="Nenhuma imagem WEBP adicionada ainda para esta conta." />
                  )}
                </div>
              </>
            ) : null}

            <label className="product-textarea">
              <span>Descrição completa</span>
              <textarea
                rows={8}
                value={productDraft.description}
                onChange={(event) => onDraftChange('description', event.target.value)}
                placeholder="Texto completo que aparece na página do produto."
              />
            </label>

            <label className="product-textarea">
              <span>Notas e políticas</span>
              <textarea
                rows={8}
                value={productDraft.notes}
                onChange={(event) => onDraftChange('notes', event.target.value)}
                placeholder="Direitos, reembolso, observações e regras deste produto."
              />
            </label>

            <label className="checkbox-row">
              <input
                type="checkbox"
                checked={Boolean(productDraft.isActive)}
                onChange={(event) => onDraftChange('isActive', event.target.checked)}
              />
              <span>Deixar anúncio ativo e visível no site</span>
            </label>

            <div className="button-row">
              <button type="button" className="ghost-button" onClick={onNewProduct}>
                Limpar editor
              </button>
              <button type="submit" className="primary-button" disabled={isSaving}>
                {isSaving ? 'Salvando...' : productId ? 'Salvar alterações' : 'Criar produto'}
              </button>
            </div>
          </form>
        </Panel>
      </div>
    </div>
  );
}

export function SettingsView({
  appInfo,
  login,
  setLogin,
  saveSettings,
  verifyApi,
  openDiscord,
  busy,
  apiInfo,
  presenceStatus,
  config,
  uiPrefs,
  toggleCompactMode,
  toggleSoundAlerts,
  toggleDesktopNotifications,
  restoreOnboarding,
  shortcutHints,
  healthItems,
  updateState,
  onCheckUpdates,
  onInstallUpdate,
  hideSensitiveInfo,
  canRevealSensitiveInfo,
  onToggleSensitiveInfo,
  permissionFlags,
}) {
  const routines = [
    'Use a mesma URL da API do site para manter o token coerente.',
    'Teste o Discord depois de salvar um Client ID válido.',
    'Se trocar a API, o app encerra a sessão para renovar o token com segurança.',
  ];

  return (
    <div className="view-grid">
      <Panel title="Conexão do desktop" subtitle="Preferências locais para API e Rich Presence.">
        <form className="stack-form" onSubmit={saveSettings}>
          <Field
            label="URL da API"
            type={hideSensitiveInfo ? 'password' : 'text'}
            value={login.apiBaseUrl}
            onChange={(value) => setLogin((current) => ({ ...current, apiBaseUrl: value }))}
            description={hideSensitiveInfo ? 'URL mascarada para proteger o compartilhamento de tela.' : ''}
            disabled={!permissionFlags.saveSettings}
          />
          <Field
            label="Discord Client ID"
            type={hideSensitiveInfo ? 'password' : 'text'}
            value={login.discordClientId}
            onChange={(value) => setLogin((current) => ({ ...current, discordClientId: value }))}
            placeholder="Opcional"
            description={hideSensitiveInfo ? 'Client ID mascarado enquanto a proteção visual estiver ativa.' : ''}
            disabled={!permissionFlags.saveSettings}
          />

          <label className="checkbox-row">
            <input
              type="checkbox"
              checked={login.presenceEnabled}
              onChange={(event) => setLogin((current) => ({ ...current, presenceEnabled: event.target.checked }))}
              disabled={!permissionFlags.saveSettings}
            />
            <span>Habilitar Rich Presence local</span>
          </label>

          <label className="checkbox-row">
            <input type="checkbox" checked={hideSensitiveInfo} onChange={onToggleSensitiveInfo} disabled={!canRevealSensitiveInfo} />
            <span>{canRevealSensitiveInfo ? 'Ocultar dados sensíveis durante o uso' : 'Dados sensíveis protegidos pelo seu cargo'}</span>
          </label>

          <div className="button-row">
            <button type="submit" className="primary-button" disabled={!permissionFlags.saveSettings}>
              {busy === 'settings' ? 'Salvando...' : 'Salvar preferências'}
            </button>
            <button type="button" className="secondary-button" onClick={verifyApi} disabled={!permissionFlags.saveSettings}>
              {busy === 'verify' ? 'Testando...' : 'Testar API'}
            </button>
          </div>
        </form>

        {!permissionFlags.saveSettings ? <div className="inline-hint">Seu cargo pode visualizar esta área, mas não alterar as preferências locais do desktop.</div> : null}
      </Panel>

      <Panel title="Resumo do ambiente" subtitle="Contexto rápido para saber como o desktop está configurado.">
        <div className="stack-list">
          <DataRow label="Versão" value={appInfo.version} />
          <DataRow label="Plataforma" value={appInfo.platform} />
          <DataRow label="API salva" value={hideSensitiveInfo ? maskSensitiveValue(config.apiBaseUrl, 'endpoint') || 'Oculta' : config.apiBaseUrl || '-'} />
          <DataRow label="Discord" value={presenceLabel(presenceStatus)} />
          <DataRow label="Serviço validado" value={apiInfo?.service || 'Ainda não validado nesta sessão'} />
        </div>
      </Panel>

      <Panel title="Atualizações do app" subtitle="Mantenha founder, admin e staff na mesma versão sem precisar reinstalar o desktop.">
        <div className="stack-list">
          <DataRow label="Status" value={describeUpdateStatus(updateState)} />
          <DataRow label="Versão atual" value={updateState?.currentVersion || appInfo.version} />
          <DataRow label="Nova versão" value={updateState?.availableVersion || updateState?.downloadedVersion || '-'} />
          <DataRow label="Progresso" value={`${Math.round(Number(updateState?.progress ?? 0))}%`} />
          <DataRow label="Última verificação" value={fmtDate(updateState?.lastCheckedAt)} />
        </div>

        <div className={`inline-hint ${updateState?.status === 'error' ? 'danger' : ''}`.trim()}>
          {updateState?.supported === false
            ? updateState?.message || 'As atualizações automáticas exigem a versão instalada do Windows.'
            : updateState?.status === 'downloaded'
              ? 'A nova versão já foi baixada. Reinicie o app para concluir a instalação.'
              : updateState?.status === 'downloading'
                ? 'O download está em andamento em segundo plano. A equipe pode continuar usando o app normalmente.'
                : 'Use o botão abaixo para verificar novas versões. O fluxo automático funciona na build instalada via Setup.'}
        </div>

        <div className="button-row">
          <button
            type="button"
            className="secondary-button"
            onClick={onCheckUpdates}
            disabled={updateState?.supported === false || updateState?.status === 'checking'}
          >
            {updateState?.status === 'checking' ? 'Verificando...' : 'Verificar atualizações'}
          </button>

          <button
            type="button"
            className="primary-button"
            onClick={onInstallUpdate}
            disabled={updateState?.status !== 'downloaded'}
          >
            Reiniciar e atualizar
          </button>
        </div>
      </Panel>

      <Panel title="Interface e alertas" subtitle="Preferências locais para deixar o desktop mais confortável e claro para a equipe.">
        <div className="stack-list">
          <label className="checkbox-row">
            <input type="checkbox" checked={uiPrefs.compactMode} onChange={toggleCompactMode} />
            <span>Ativar modo compacto para telas menores</span>
          </label>

          <label className="checkbox-row">
            <input type="checkbox" checked={uiPrefs.soundAlertsEnabled} onChange={toggleSoundAlerts} />
            <span>Ativar alertas sonoros para eventos novos</span>
          </label>

          <label className="checkbox-row">
            <input type="checkbox" checked={uiPrefs.desktopNotificationsEnabled} onChange={toggleDesktopNotifications} />
            <span>Ativar notificações do sistema</span>
          </label>

          <label className="checkbox-row">
            <input type="checkbox" checked={hideSensitiveInfo} onChange={onToggleSensitiveInfo} disabled={!canRevealSensitiveInfo} />
            <span>{canRevealSensitiveInfo ? 'Ocultar API, e-mails e IDs sensíveis' : 'Ocultação sensível ativa por permissão'}</span>
          </label>
        </div>

        <div className="button-row">
          <button type="button" className="ghost-button" onClick={restoreOnboarding}>
            Mostrar guia rápido novamente
          </button>
        </div>
      </Panel>

      <Panel title="Atalhos e saúde" subtitle="Resumo das teclas rápidas e da situação atual do desktop.">
        <div className="tips-grid compact-tips">
          {shortcutHints.map((item) => (
            <article key={item.id} className="tip-card">
              <strong>{item.keys}</strong>
              <p>{item.action}</p>
            </article>
          ))}
        </div>

        <div className="metric-grid compact-grid">
          {healthItems.map((item) => (
            <Metric key={`health-${item.label}`} label={item.label} value={item.value} toneValue={item.toneValue} hint={item.hint} />
          ))}
        </div>
      </Panel>

      <Panel title="Rotina recomendada" subtitle="Pequenos cuidados para deixar o fluxo mais previsível para o time.">
        <div className="priority-list static">
          {routines.map((item) => (
            <article key={item} className="priority-item neutral">
              <div>
                <strong>Boas práticas</strong>
                <p>{item}</p>
              </div>
            </article>
          ))}
        </div>

        <div className="button-row">
          <button type="button" className="ghost-button" onClick={openDiscord}>
            Abrir portal do Discord
          </button>
        </div>
      </Panel>
    </div>
  );
}
