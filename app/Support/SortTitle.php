<?php

namespace App\Support;

/**
 * A title's two forms: the one people read ("The Matrix") and the one the
 * library sorts by ("Matrix, The").
 *
 * from() moves a leading article to the end, for sorting:
 *   "The Matrix"        -> "Matrix, The"
 *   "A Beautiful Mind"  -> "Beautiful Mind, A"
 *   "An Education"      -> "Education, An"
 *   "Inception"         -> "Inception"
 *
 * display() puts one written at the end back in front, for reading, as
 * catalog-style sheets list them. A title with a subtitle does this for
 * each part on either side of a colon (or a spaced " - "):
 *   "Matrix, The"                         -> "The Matrix"
 *   "Hobbit, The: An Unexpected Journey"  -> "The Hobbit: An Unexpected Journey"
 *   "Batman: Dark Knight, The"            -> "Batman: The Dark Knight"
 *   "Good, the Bad and the Ugly, The"     -> "The Good, the Bad and the Ugly"
 *   "Paris, Texas"                        -> "Paris, Texas"
 */
class SortTitle
{
    private const ARTICLES = ['The', 'A', 'An'];

    public static function from(string $title): string
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            return '';
        }

        foreach (self::ARTICLES as $article) {
            $prefix = $article.' ';
            if (stripos($trimmed, $prefix) === 0) {
                $rest = substr($trimmed, strlen($prefix));
                return $rest.', '.$article;
            }
        }

        return $trimmed;
    }

    public static function display(string $title): string
    {
        // Parts and the separators between them: ":" and " - ".
        $pieces = preg_split('/(\s*:\s*|\s+-\s+)/', trim($title), -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($pieces as $i => $piece) {
            if ($i % 2 === 0 && preg_match('/^(.*\S)\s*,\s*(the|a|an)$/i', $piece, $m)) {
                $pieces[$i] = ucfirst(strtolower($m[2])).' '.$m[1];
            }
        }

        return implode('', $pieces);
    }
}
