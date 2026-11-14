<?php

namespace App\Support;

/**
 * Moves leading articles to the end of a title for alphabetical sorting.
 *
 *   "The Matrix"        -> "Matrix, The"
 *   "A Beautiful Mind"  -> "Beautiful Mind, A"
 *   "An Education"      -> "Education, An"
 *   "Inception"         -> "Inception"
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
}
