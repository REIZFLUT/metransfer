<?php

namespace App\Support;

class Markdown
{
    /**
     * Escape user-provided text so it is shown literally inside Markdown
     * (e.g. file names in mails cannot turn into links or formatting).
     */
    public static function escape(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', $text);
    }
}
