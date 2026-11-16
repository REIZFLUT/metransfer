import { X } from 'lucide-react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Input } from '@/components/ui/input';

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

type Props = {
    id?: string;
    value: string[];
    onChange: (value: string[]) => void;
    disabled?: boolean;
    placeholder?: string;
};

export default function EmailChipsInput({
    id,
    value,
    onChange,
    disabled,
    placeholder = 'name@beispiel.de',
}: Props) {
    const [draft, setDraft] = useState('');
    const [invalid, setInvalid] = useState(false);

    const commitDraft = (): boolean => {
        const parts = draft
            .split(/[\s,;]+/)
            .map((part) => part.trim().toLowerCase())
            .filter(Boolean);

        if (parts.length === 0) {
            return false;
        }

        const valid = parts.filter((part) => EMAIL.test(part));

        if (valid.length !== parts.length) {
            setInvalid(true);

            return false;
        }

        onChange(Array.from(new Set([...value, ...valid])));
        setDraft('');
        setInvalid(false);

        return true;
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (['Enter', ',', ';', ' ', 'Tab'].includes(event.key)) {
            if (draft.trim() === '') {
                return;
            }

            if (commitDraft() || event.key !== 'Tab') {
                event.preventDefault();
            }
        } else if (event.key === 'Backspace' && draft === '' && value.length) {
            onChange(value.slice(0, -1));
        }
    };

    return (
        <div className="space-y-2">
            {value.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {value.map((email) => (
                        <span
                            key={email}
                            className="inline-flex items-center gap-1 rounded-full bg-secondary py-0.5 pr-1 pl-2.5 text-xs font-medium text-secondary-foreground"
                        >
                            {email}
                            <button
                                type="button"
                                disabled={disabled}
                                onClick={() =>
                                    onChange(value.filter((e) => e !== email))
                                }
                                className="rounded-full p-0.5 hover:bg-foreground/10"
                                aria-label={`${email} entfernen`}
                            >
                                <X className="size-3" />
                            </button>
                        </span>
                    ))}
                </div>
            )}
            <Input
                id={id}
                type="text"
                inputMode="email"
                autoComplete="off"
                value={draft}
                disabled={disabled}
                aria-invalid={invalid || undefined}
                placeholder={placeholder}
                onChange={(event) => {
                    setDraft(event.target.value);
                    setInvalid(false);
                }}
                onKeyDown={onKeyDown}
                onBlur={() => commitDraft()}
            />
            {invalid && (
                <p className="text-sm text-red-600 dark:text-red-400">
                    Bitte eine gültige E-Mail-Adresse eingeben.
                </p>
            )}
        </div>
    );
}
