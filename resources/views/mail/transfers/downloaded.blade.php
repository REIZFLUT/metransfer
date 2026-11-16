<x-mail::message>
# Neuer Download

Dein Transfer **{{ \App\Support\Markdown::escape($transfer->displayTitle()) }}** wurde gerade heruntergeladen{{ $fileName ? ' (Datei: '.\App\Support\Markdown::escape($fileName).')' : ' (alle Dateien als ZIP)' }}.

Bisher gab es insgesamt **{{ $transfer->download_count }}** Download(s).

<x-mail::button :url="$url">
Transfer ansehen
</x-mail::button>

Weitere Benachrichtigungen für diesen Transfer werden für {{ config('transfers.notification_cooldown_minutes') }} Minuten gebündelt.

{{ config('app.name') }}
</x-mail::message>
