export type TransferFile = {
    id: number;
    name: string;
    size: number;
    mime_type: string | null;
    received_bytes: number;
    complete: boolean;
};

export type TransferRecipient = {
    id: number;
    email: string;
    sent_at: string | null;
};

export type TransferDownload = {
    id: number;
    file_name: string | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
};

export type Transfer = {
    id: number;
    title: string | null;
    display_title: string;
    message: string | null;
    status: 'draft' | 'ready';
    url: string;
    has_password: boolean;
    notify_on_download: boolean;
    download_count: number;
    expires_at: string | null;
    is_expired: boolean;
    published_at: string | null;
    created_at: string | null;
    files_count: number;
    total_size: number;
    files?: TransferFile[];
    recipients?: TransferRecipient[];
    downloads?: TransferDownload[];
};
