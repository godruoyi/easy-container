<?php

namespace Tests\Support;

class ThreeBody
{
    public $book;

    public function __construct(BookInterface $book)
    {
        $this->book = $book;
    }

    public function getName()
    {
        return $this->book->name();
    }
}
