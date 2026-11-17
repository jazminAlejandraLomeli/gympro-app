/**
 * fetch JSON con la protección CSRF de Laravel (cookie XSRF-TOKEN).
 * Para llamadas que no son navegación de Inertia, p. ej. el escáner QR.
 *
 *   const { ok, status, data } = await postJson('/panel/asistencia/escanear', { token });
 */
function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export async function requestJson(method, url, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    let data = null;
    try {
        data = await response.json();
    } catch {
        // respuesta sin cuerpo JSON
    }

    return { ok: response.ok, status: response.status, data };
}

export const getJson = (url) => requestJson('GET', url);
export const postJson = (url, body) => requestJson('POST', url, body);
