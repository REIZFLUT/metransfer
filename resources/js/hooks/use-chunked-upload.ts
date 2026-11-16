import { useCallback, useRef, useState } from 'react';
import { requestJson, uploadChunk } from '@/lib/http';
import chunks from '@/routes/transfers/files/chunks';
import files from '@/routes/transfers/files';
import type { TransferFile } from '@/types';

export type UploadStatus = 'pending' | 'uploading' | 'done' | 'error';

export type UploadItem = {
    key: string;
    file: File;
    uploaded: number;
    status: UploadStatus;
    error?: string;
    remoteId?: number;
};

const PARALLEL_FILES = 3;
const MAX_RETRIES = 6;

function sleep(ms: number, signal: AbortSignal): Promise<void> {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(resolve, ms);
        signal.addEventListener(
            'abort',
            () => {
                clearTimeout(timer);
                reject(new DOMException('Upload abgebrochen', 'AbortError'));
            },
            { once: true },
        );
    });
}

function isAbort(error: unknown): boolean {
    return error instanceof DOMException && error.name === 'AbortError';
}

export function useChunkedUpload(chunkSize: number) {
    const [items, setItems] = useState<UploadItem[]>([]);
    const itemsRef = useRef<UploadItem[]>([]);
    const controller = useRef<AbortController | null>(null);

    const commit = useCallback((next: UploadItem[]) => {
        itemsRef.current = next;
        setItems(next);
    }, []);

    const patch = useCallback(
        (key: string, changes: Partial<UploadItem>) => {
            commit(
                itemsRef.current.map((item) =>
                    item.key === key ? { ...item, ...changes } : item,
                ),
            );
        },
        [commit],
    );

    const addFiles = useCallback(
        (list: FileList | File[]) => {
            const existing = new Set(
                itemsRef.current.map(
                    (item) =>
                        `${item.file.name}:${item.file.size}:${item.file.lastModified}`,
                ),
            );

            const added = Array.from(list)
                .filter(
                    (file) =>
                        !existing.has(
                            `${file.name}:${file.size}:${file.lastModified}`,
                        ),
                )
                .map<UploadItem>((file) => ({
                    key: crypto.randomUUID(),
                    file,
                    uploaded: 0,
                    status: 'pending',
                }));

            commit([...itemsRef.current, ...added]);
        },
        [commit],
    );

    const removeFile = useCallback(
        (key: string) => {
            commit(itemsRef.current.filter((item) => item.key !== key));
        },
        [commit],
    );

    const uploadItem = useCallback(
        async (transferId: number, item: UploadItem, signal: AbortSignal) => {
            patch(item.key, { status: 'uploading', error: undefined });

            let fileId = item.remoteId;

            if (!fileId) {
                const created = await requestJson<TransferFile>(
                    'post',
                    files.store.url(transferId),
                    {
                        name: item.file.name,
                        size: item.file.size,
                        mime_type: item.file.type || null,
                    },
                    signal,
                );
                fileId = created.id;
                patch(item.key, { remoteId: fileId });

                if (created.complete) {
                    patch(item.key, {
                        status: 'done',
                        uploaded: item.file.size,
                    });

                    return;
                }
            }

            const url = chunks.store.url({
                transfer: transferId,
                file: fileId,
            });
            let offset = 0;
            let retries = 0;

            // Resume: ask the server how much it already has.
            if (item.remoteId) {
                const state = await requestJson<TransferFile>(
                    'get',
                    files.show.url({ transfer: transferId, file: fileId }),
                    undefined,
                    signal,
                );
                offset = state.received_bytes;
            }

            while (offset < item.file.size) {
                const chunk = item.file.slice(offset, offset + chunkSize);
                const base = offset;

                try {
                    const result = await uploadChunk(
                        url,
                        chunk,
                        offset,
                        (loaded) =>
                            patch(item.key, { uploaded: base + loaded }),
                        signal,
                    );

                    if (result.status === 200 || result.status === 409) {
                        offset = result.offset;
                        retries = 0;
                        patch(item.key, { uploaded: offset });

                        if (result.complete) {
                            break;
                        }

                        continue;
                    }

                    if (
                        result.status >= 400 &&
                        result.status < 500 &&
                        result.status !== 408 &&
                        result.status !== 429
                    ) {
                        throw new Error(
                            result.message ??
                                `Upload fehlgeschlagen (${result.status})`,
                        );
                    }

                    throw new Error(`Serverfehler (${result.status})`);
                } catch (error) {
                    if (isAbort(error) || retries >= MAX_RETRIES) {
                        throw error;
                    }

                    retries++;
                    patch(item.key, { uploaded: offset });
                    await sleep(Math.min(1000 * 2 ** retries, 30_000), signal);

                    const state = await requestJson<TransferFile>(
                        'get',
                        files.show.url({ transfer: transferId, file: fileId }),
                        undefined,
                        signal,
                    ).catch(() => null);

                    if (state) {
                        offset = state.received_bytes;
                    }
                }
            }

            patch(item.key, { status: 'done', uploaded: item.file.size });
        },
        [chunkSize, patch],
    );

    /**
     * Upload all files that are not done yet. Resolves when every file
     * is complete, rejects on the first failure.
     */
    const upload = useCallback(
        async (transferId: number) => {
            controller.current = new AbortController();
            const signal = controller.current.signal;
            const queue = itemsRef.current.filter(
                (item) => item.status !== 'done',
            );
            let failed: unknown = null;

            const worker = async () => {
                while (queue.length > 0 && !failed) {
                    const item = queue.shift()!;

                    try {
                        await uploadItem(
                            transferId,
                            itemsRef.current.find((i) => i.key === item.key) ??
                                item,
                            signal,
                        );
                    } catch (error) {
                        if (!isAbort(error)) {
                            patch(item.key, {
                                status: 'error',
                                error:
                                    error instanceof Error
                                        ? error.message
                                        : 'Unbekannter Fehler',
                            });
                        }

                        failed = error;
                        controller.current?.abort();
                    }
                }
            };

            await Promise.all(
                Array.from({ length: PARALLEL_FILES }, () => worker()),
            );

            if (failed) {
                commit(
                    itemsRef.current.map((item) =>
                        item.status === 'uploading'
                            ? { ...item, status: 'pending' }
                            : item,
                    ),
                );

                throw failed;
            }
        },
        [commit, patch, uploadItem],
    );

    const abort = useCallback(() => {
        controller.current?.abort();
        commit(
            itemsRef.current.map((item) =>
                item.status === 'uploading'
                    ? { ...item, status: 'pending' }
                    : item,
            ),
        );
    }, [commit]);

    const reset = useCallback(() => {
        controller.current?.abort();
        commit([]);
    }, [commit]);

    const totalBytes = items.reduce((sum, item) => sum + item.file.size, 0);
    const uploadedBytes = items.reduce((sum, item) => sum + item.uploaded, 0);

    return {
        items,
        addFiles,
        removeFile,
        upload,
        abort,
        reset,
        totalBytes,
        uploadedBytes,
    };
}
