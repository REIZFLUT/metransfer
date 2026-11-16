function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function jsonHeaders(): Record<string, string> {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
    };
}

export class HttpError extends Error {
    constructor(
        public status: number,
        public data: Record<string, unknown> | null,
    ) {
        super(
            typeof data?.message === 'string'
                ? data.message
                : `Anfrage fehlgeschlagen (${status})`,
        );
    }
}

export async function requestJson<T>(
    method: string,
    url: string,
    body?: unknown,
    signal?: AbortSignal,
): Promise<T> {
    const response = await fetch(url, {
        method: method.toUpperCase(),
        headers: {
            ...jsonHeaders(),
            ...(body !== undefined
                ? { 'Content-Type': 'application/json' }
                : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
        credentials: 'same-origin',
        signal,
    });

    const data = (await response.json().catch(() => null)) as T | null;

    if (!response.ok) {
        throw new HttpError(
            response.status,
            data as Record<string, unknown> | null,
        );
    }

    return data as T;
}

export type ChunkResult = {
    status: number;
    offset: number;
    complete: boolean;
    message?: string;
};

/**
 * Upload a single chunk with XHR so we get fine-grained progress events.
 */
export function uploadChunk(
    url: string,
    chunk: Blob,
    offset: number,
    onProgress: (loaded: number) => void,
    signal: AbortSignal,
): Promise<ChunkResult> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);

        for (const [key, value] of Object.entries(jsonHeaders())) {
            xhr.setRequestHeader(key, value);
        }

        xhr.setRequestHeader('Content-Type', 'application/octet-stream');
        xhr.setRequestHeader('Upload-Offset', String(offset));

        xhr.upload.onprogress = (event) => onProgress(event.loaded);

        xhr.onload = () => {
            let data: Partial<ChunkResult> = {};

            try {
                data = JSON.parse(xhr.responseText) as Partial<ChunkResult>;
            } catch {
                // ignore – handled via status below
            }

            resolve({
                status: xhr.status,
                offset: typeof data.offset === 'number' ? data.offset : offset,
                complete: Boolean(data.complete),
                message: data.message,
            });
        };

        xhr.onerror = () => reject(new Error('Netzwerkfehler'));
        xhr.onabort = () =>
            reject(new DOMException('Upload abgebrochen', 'AbortError'));

        signal.addEventListener('abort', () => xhr.abort(), { once: true });

        xhr.send(chunk);
    });
}
