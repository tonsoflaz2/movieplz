<?php

namespace Tests\Unit\Support;

use App\Support\SortTitle;
use PHPUnit\Framework\TestCase;

class SortTitleTest extends TestCase
{
    public function test_moves_leading_the(): void
    {
        $this->assertSame('Matrix, The', SortTitle::from('The Matrix'));
        $this->assertSame('Godfather, The', SortTitle::from('The Godfather'));
    }

    public function test_moves_leading_a(): void
    {
        $this->assertSame('Beautiful Mind, A', SortTitle::from('A Beautiful Mind'));
    }

    public function test_moves_leading_an(): void
    {
        $this->assertSame('Education, An', SortTitle::from('An Education'));
    }

    public function test_leaves_non_articled_titles_alone(): void
    {
        $this->assertSame('Inception', SortTitle::from('Inception'));
        $this->assertSame('Mad Max: Fury Road', SortTitle::from('Mad Max: Fury Road'));
    }

    public function test_matches_article_case_insensitively_but_normalizes_output_casing(): void
    {
        // "the Matrix" is recognized as starting with the article "The",
        // even though the input is lowercase — and the output uses the
        // canonical capital casing for sort consistency.
        $this->assertSame('Matrix, The', SortTitle::from('the Matrix'));
    }

    public function test_empty_input_returns_empty(): void
    {
        $this->assertSame('', SortTitle::from(''));
        $this->assertSame('', SortTitle::from('   '));
    }

    public function test_does_not_touch_words_that_merely_start_with_an_article(): void
    {
        // "Theater" starts with "The" but isn't the article + space.
        $this->assertSame('Theater', SortTitle::from('Theater'));
        // "Another Round" starts with "An" but not "An ".
        $this->assertSame('Round, An', SortTitle::from('An Round'));
        $this->assertSame('Another Round', SortTitle::from('Another Round'));
    }
}
