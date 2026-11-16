<x-mail::message>
# {{ \App\Support\Markdown::escape($transfer->displayTitle()) }}

**{{ $sender->name }}** hat dir {{ $files->count() === 1 ? 'eine Datei' : $files->count().' Dateien' }} ({{ \Illuminate\Support\Number::fileSize($files->sum('size'), 1) }}) geschickt.

@if ($transfer->message)
<x-mail::panel>
{!! nl2br(e($transfer->message)) !!}
</x-mail::panel>
@endif

<x-mail::button :url="$url">
Dateien herunterladen
</x-mail::button>

<x-mail::table>
| Datei | Größe |
|:------|------:|
@foreach ($files->take(15) as $file)
| {{ \App\Support\Markdown::escape($file->original_name) }} | {{ \Illuminate\Support\Number::fileSize($file->size, 1) }} |
@endforeach
@if ($files->count() > 15)
| … und {{ $files->count() - 15 }} weitere | |
@endif
</x-mail::table>

@if ($transfer->expires_at)
Der Link ist gültig bis **{{ $transfer->expires_at->timezone(config('app.timezone'))->translatedFormat('j. F Y, H:i') }} Uhr**.
@endif
@if ($transfer->isPasswordProtected())
Der Download ist passwortgeschützt – das Passwort erhältst du separat von {{ $sender->name }}.
@endif

Viele Grüße<br>
{{ config('app.name') }}
</x-mail::message>
