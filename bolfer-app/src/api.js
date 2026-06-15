export function normalizeApiBaseUrl(value) {
  return String(value ?? '').trim().replace(/\/+$/, '');
}

async function requestViaDesktopBridge(request) {
  const result = await window.bolferDesktop.requestApi(request);

  if (result?.ok) {
    return result.payload;
  }

  const error = new Error(result?.error?.message || 'A API retornou uma falha.');
  error.status = result?.error?.status;
  error.payload = result?.error?.payload;
  error.cause = result?.error?.cause;
  throw error;
}

export async function requestApi({
  baseUrl,
  path = '',
  method = 'GET',
  token,
  body,
}) {
  if (typeof window !== 'undefined' && window.bolferDesktop?.requestApi) {
    return requestViaDesktopBridge({ baseUrl, path, method, token, body });
  }

  const normalizedBaseUrl = normalizeApiBaseUrl(baseUrl);
  const normalizedPath = String(path).replace(/^\/+/, '');
  const url = normalizedPath ? `${normalizedBaseUrl}/${normalizedPath}` : normalizedBaseUrl;

  const headers = {
    Accept: 'application/json',
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  let response;
  try {
    response = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch (error) {
    const networkError = new Error(
      'Nao foi possivel conectar com a API. Em producao, use a URL publica configurada para /api/desktop. Se voce estiver rodando localmente, use http://localhost:8000/api/desktop e confirme que o servidor do site esta ativo.',
    );
    networkError.cause = error;
    throw networkError;
  }

  let payload = null;
  try {
    payload = await response.json();
  } catch {
    payload = {
      ok: false,
      message: 'A API retornou uma resposta inválida.',
    };
  }

  if (!response.ok) {
    const error = new Error(payload?.message || `Erro ${response.status}`);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  if (payload?.ok === false) {
    const error = new Error(payload?.message || 'A API retornou uma falha.');
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}

export async function requestOptionalApi(request, options = {}) {
  const ignoredStatuses = options.ignoredStatuses ?? [404, 405, 409, 422, 501];

  try {
    return await requestApi(request);
  } catch (error) {
    if (ignoredStatuses.includes(error?.status)) {
      return null;
    }

    throw error;
  }
}
