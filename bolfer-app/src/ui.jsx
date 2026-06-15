import React from 'react';

export function Panel({ title, subtitle, action, children, className = '' }) {
  return (
    <section className={`surface ${className}`.trim()}>
      {title || subtitle || action ? (
        <header className="surface-header">
          <div>
            {title ? <h3>{title}</h3> : null}
            {subtitle ? <p className="surface-subtitle">{subtitle}</p> : null}
          </div>
          {action ? <div className="surface-action">{action}</div> : null}
        </header>
      ) : null}
      <div className="surface-body">{children}</div>
    </section>
  );
}

export function SectionHero({ eyebrow, title, description, action, children }) {
  return (
    <section className="hero-panel">
      <div className="hero-head">
        <div className="hero-copy">
          {eyebrow ? <span className="eyebrow">{eyebrow}</span> : null}
          <h2>{title}</h2>
          {description ? <p>{description}</p> : null}
        </div>
        {action ? <div className="hero-action">{action}</div> : null}
      </div>
      {children ? <div className="hero-content">{children}</div> : null}
    </section>
  );
}

export function Metric({ label, value, toneValue = 'neutral', hint }) {
  return (
    <article className={`metric-card ${toneValue}`}>
      <span>{label}</span>
      <strong>{value}</strong>
      {hint ? <small>{hint}</small> : null}
    </article>
  );
}

export function WorkspaceCard({ label, value, toneValue = 'neutral' }) {
  return (
    <article className={`workspace-card ${toneValue}`}>
      <span>{label}</span>
      <strong>{value}</strong>
    </article>
  );
}

export function DataRow({ label, value }) {
  return (
    <div className="info-row">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

export function Field({ label, value, onChange, placeholder = '', type = 'text', description = '', disabled = false, inputProps = {} }) {
  return (
    <label>
      <span>{label}</span>
      <input type={type} value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} disabled={disabled} {...inputProps} />
      {description ? <small className="field-help">{description}</small> : null}
    </label>
  );
}

export function Badge({ children, toneValue = 'neutral' }) {
  return <span className={`status-pill ${toneValue}`}>{children}</span>;
}

export function Notice({ notice, onClose }) {
  return (
    <div className={`notice-banner ${notice.type}`}>
      <span>{notice.text}</span>
      {onClose ? (
        <button type="button" className="notice-close" onClick={onClose}>
          Fechar
        </button>
      ) : null}
    </div>
  );
}

export function CommandBar({
  searchQuery,
  onSearchChange,
  onSearchClear,
  autoRefreshEnabled,
  autoRefreshInterval,
  autoRefreshOptions,
  onToggleAutoRefresh,
  onAutoRefreshIntervalChange,
  activityCount,
  latestActivity,
  onClearActivity,
  hideSensitiveInfo,
  canRevealSensitiveInfo,
  onToggleSensitiveInfo,
}) {
  return (
    <section className="command-bar">
      <div className="command-block command-search-block">
        <span className="eyebrow">Busca global</span>
        <label className="command-search-field">
          <span>Pesquisa rápida</span>
          <input
            id="global-search-input"
            type="search"
            value={searchQuery}
            onChange={(event) => onSearchChange(event.target.value)}
            placeholder="Pedido, usuário, produto, log ou evento interno"
          />
        </label>
        <div className="button-row">
          <button type="button" className="ghost-button" onClick={onSearchClear}>
            Limpar busca
          </button>
        </div>
      </div>

      <div className="command-block">
        <span className="eyebrow">Ritmo do painel</span>
        <div className="command-summary">
          <strong>{autoRefreshEnabled ? `Atualizando a cada ${autoRefreshInterval}s` : 'Autoatualização pausada'}</strong>
          <p>Ideal para acompanhar a fila sem precisar forçar refresh manual o tempo todo.</p>
        </div>
        <div className="command-inline">
          <button type="button" className={autoRefreshEnabled ? 'secondary-button' : 'ghost-button'} onClick={onToggleAutoRefresh}>
            {autoRefreshEnabled ? 'Pausar autoatualização' : 'Ativar autoatualização'}
          </button>
          <label className="command-select">
            <span>Intervalo</span>
            <select
              value={autoRefreshInterval}
              disabled={!autoRefreshEnabled}
              onChange={(event) => onAutoRefreshIntervalChange(Number(event.target.value))}
            >
              {autoRefreshOptions.map((option) => (
                <option key={option} value={option}>
                  {option}s
                </option>
              ))}
            </select>
          </label>
        </div>
      </div>

      <div className="command-block">
        <span className="eyebrow">Central interna</span>
        <div className="command-summary">
          <strong>{activityCount ? `${activityCount} eventos recentes` : 'Sem alertas recentes'}</strong>
          <p>{latestActivity?.title || 'O desktop vai registrar mudanças importantes da operação aqui.'}</p>
        </div>
        <div className="button-row">
          <button type="button" className="ghost-button" onClick={onClearActivity} disabled={!activityCount}>
            Limpar central
          </button>
        </div>
      </div>

      <div className="command-block">
        <span className="eyebrow">Privacidade</span>
        <div className="command-summary">
          <strong>{hideSensitiveInfo ? 'Dados sensíveis ocultos' : 'Dados sensíveis visíveis'}</strong>
          <p>
            {canRevealSensitiveInfo
              ? 'Use este atalho para esconder API, e-mails e IDs durante o compartilhamento de tela.'
              : 'Seu cargo mantém as informações sensíveis protegidas por padrão neste desktop.'}
          </p>
        </div>
        <div className="button-row">
          <button
            type="button"
            className={hideSensitiveInfo ? 'secondary-button' : 'ghost-button'}
            onClick={onToggleSensitiveInfo}
            disabled={!canRevealSensitiveInfo}
          >
            {canRevealSensitiveInfo ? (hideSensitiveInfo ? 'Mostrar dados' : 'Ocultar dados') : 'Protegido pelo cargo'}
          </button>
        </div>
      </div>
    </section>
  );
}

export function SearchResults({ query, results, onSelect, onClose }) {
  if (!query.trim()) {
    return null;
  }

  return (
    <section className="surface search-surface">
      <header className="surface-header">
        <div>
          <h3>Resultados da busca</h3>
          <p className="surface-subtitle">Atalhos locais para abrir pedidos, usuários, produtos, logs e eventos recentes sem trocar de tela no escuro.</p>
        </div>
        <div className="surface-action">
          <button type="button" className="ghost-button" onClick={onClose}>
            Fechar busca
          </button>
        </div>
      </header>
      <div className="surface-body">
        {results.length ? (
          <div className="search-results">
            {results.map((result) => (
              <button key={result.id} type="button" className={`search-result ${result.toneValue || 'neutral'}`} onClick={() => onSelect(result)}>
                <div className="search-result-copy">
                  <strong>{result.title}</strong>
                  <p>{result.text}</p>
                </div>
                <span>{result.meta || result.targetView}</span>
              </button>
            ))}
          </div>
        ) : (
          <Empty label="Nenhum resultado local encontrado. Tente outro termo ou carregue a seção desejada primeiro." />
        )}
      </div>
    </section>
  );
}

export function FilterPresets({ presets, onSave, onApply, onDelete }) {
  return (
    <div className="preset-strip">
      <div className="preset-head">
        <div>
          <span className="eyebrow">Filtros salvos</span>
          <p>Guarde recortes que a equipe usa com frequência e reaplique com um clique.</p>
        </div>
        <button type="button" className="ghost-button" onClick={onSave}>
          Salvar filtro atual
        </button>
      </div>

      {presets.length ? (
        <div className="preset-list">
          {presets.map((preset) => (
            <article key={preset.id} className="preset-item">
              <button type="button" className="preset-apply" onClick={() => onApply(preset)}>
                {preset.name}
              </button>
              <button type="button" className="preset-remove" onClick={() => onDelete(preset)}>
                Remover
              </button>
            </article>
          ))}
        </div>
      ) : (
        <div className="empty-state compact-empty">
          <p>Nenhum filtro salvo ainda.</p>
        </div>
      )}
    </div>
  );
}

export function Modal({ open, title, text, confirmLabel, cancelLabel = 'Cancelar', toneValue = 'neutral', busy = false, onConfirm, onClose }) {
  if (!open) {
    return null;
  }

  const confirmClass = toneValue === 'danger' ? 'danger-button' : 'primary-button';

  return (
    <div className="modal-overlay" role="presentation" onClick={onClose}>
      <div className="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-title" onClick={(event) => event.stopPropagation()}>
        <span className="eyebrow">Confirmação</span>
        <h3 id="modal-title">{title}</h3>
        <p>{text}</p>
        <div className="button-row">
          <button type="button" className="ghost-button" onClick={onClose} disabled={busy}>
            {cancelLabel}
          </button>
          <button type="button" className={confirmClass} onClick={onConfirm} disabled={busy}>
            {busy ? 'Processando...' : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}

export function Empty({ label, action }) {
  return (
    <div className="empty-state">
      <p>{label}</p>
      {action ? <div className="empty-action">{action}</div> : null}
    </div>
  );
}

export function Table({ headers, rows, empty }) {
  if (!rows.length) return <Empty label={empty} />;

  return (
    <div className="table-wrap responsive-table">
      <table>
        <thead>
          <tr>
            {headers.map((header) => (
              <th key={header}>{header}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, rowIndex) => (
            <tr key={rowIndex}>
              {row.map((cell, cellIndex) => (
                <td key={`${rowIndex}-${cellIndex}`} data-label={headers[cellIndex] ?? ''}>
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function Timeline({ items, empty }) {
  if (!items.length) return <Empty label={empty} />;

  return (
    <div className="timeline">
      {items.map((item) => (
        <article key={item.id} className="timeline-item">
          <div className="timeline-copy">
            <strong>{item.title}</strong>
            <p>{item.text}</p>
          </div>
          <div className="timeline-meta">
            {item.badge ? <Badge toneValue={item.badgeTone}>{item.badge}</Badge> : null}
            <span>{item.meta}</span>
          </div>
        </article>
      ))}
    </div>
  );
}
