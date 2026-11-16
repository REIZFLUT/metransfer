import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

export function CopyButton({
    value,
    className,
    label = 'Link kopieren',
    variant = 'outline',
    size = 'sm',
}: {
    value: string;
    className?: string;
    label?: string | null;
    variant?: 'outline' | 'ghost' | 'default' | 'secondary';
    size?: 'sm' | 'icon' | 'default';
}) {
    const [, copy] = useClipboard();
    const [copied, setCopied] = useState(false);

    const onClick = async () => {
        if (await copy(value)) {
            setCopied(true);
            toast.success('Link in die Zwischenablage kopiert.');
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const Icon = copied ? Check : Copy;

    return (
        <Button
            type="button"
            variant={variant}
            size={size}
            onClick={onClick}
            className={className}
            aria-label={label ?? 'Link kopieren'}
        >
            <Icon className="size-4" />
            {label}
        </Button>
    );
}

export default function CopyLink({
    url,
    className,
}: {
    url: string;
    className?: string;
}) {
    return (
        <div className={cn('flex gap-2', className)}>
            <Input
                readOnly
                value={url}
                onFocus={(event) => event.currentTarget.select()}
                className="font-mono text-xs"
            />
            <CopyButton value={url} label="Kopieren" />
        </div>
    );
}
