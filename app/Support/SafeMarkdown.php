<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Markdown for text that an AI model wrote. Its answers repeat data from
 * customers, forms and fetched pages, so raw HTML in it is untrusted: it is
 * stripped (not escaped into visible tags) and unsafe link schemes such as
 * javascript: are dropped before the result is echoed unescaped.
 */
class SafeMarkdown
{
    public static function toHtml(?string $markdown): string
    {
        return Str::markdown($markdown ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
