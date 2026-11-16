import {
    File,
    FileArchive,
    FileAudio,
    FileCode,
    FileImage,
    FileSpreadsheet,
    FileText,
    FileVideo,
} from 'lucide-react';
import type { LucideProps } from 'lucide-react';

export default function FileIcon({
    name,
    mime,
    ...props
}: { name: string; mime?: string | null } & LucideProps) {
    const ext = name.split('.').pop()?.toLowerCase() ?? '';
    const type = mime ?? '';

    if (type.startsWith('image/')) return <FileImage {...props} />;
    if (type.startsWith('video/')) return <FileVideo {...props} />;
    if (type.startsWith('audio/')) return <FileAudio {...props} />;
    if (['zip', 'rar', '7z', 'gz', 'tar', 'bz2', 'xz'].includes(ext))
        return <FileArchive {...props} />;
    if (['xls', 'xlsx', 'csv', 'ods', 'numbers'].includes(ext))
        return <FileSpreadsheet {...props} />;
    if (['js', 'ts', 'tsx', 'php', 'json', 'html', 'css', 'py'].includes(ext))
        return <FileCode {...props} />;
    if (
        ['pdf', 'doc', 'docx', 'txt', 'md', 'rtf', 'odt', 'pages'].includes(ext)
    )
        return <FileText {...props} />;

    return <File {...props} />;
}
